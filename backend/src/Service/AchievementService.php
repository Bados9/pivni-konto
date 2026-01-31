<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserAchievement;
use App\Enum\GroupRole;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;

class AchievementService
{
    private array $achievementDefinitions = [
        // Milníky
        'first_beer' => [
            'name' => 'První doušek',
            'description' => 'Zaznamenej své první pivo',
            'icon' => '🍼',
            'category' => 'milestones',
        ],
        'beer_50' => [
            'name' => 'Začátečník',
            'description' => 'Vypij 50 piv',
            'icon' => '🔰',
            'category' => 'milestones',
        ],
        'beer_100' => [
            'name' => 'Pokročilý',
            'description' => 'Vypij 100 piv',
            'icon' => '⭐',
            'category' => 'milestones',
        ],
        'beer_500' => [
            'name' => 'Pivní veterán',
            'description' => 'Vypij 500 piv',
            'icon' => '🎖️',
            'category' => 'milestones',
        ],
        'beer_1000' => [
            'name' => 'Pivní legenda',
            'description' => 'Vypij 1000 piv',
            'icon' => '👑',
            'category' => 'milestones',
        ],

        // Objem
        'volume_10l' => [
            'name' => 'Dekalitrovka',
            'description' => 'Vypij celkem 10 litrů',
            'icon' => '🪣',
            'category' => 'volume',
        ],
        'volume_50l' => [
            'name' => 'Sud',
            'description' => 'Vypij celkem 50 litrů (jeden sud)',
            'icon' => '🛢️',
            'category' => 'volume',
        ],
        'volume_100l' => [
            'name' => 'Hektolitr',
            'description' => 'Vypij celkem 100 litrů',
            'icon' => '🏊',
            'category' => 'volume',
        ],

        // Rozmanitost
        'variety_5' => [
            'name' => 'Ochutnávač',
            'description' => 'Vyzkoušej 5 různých piv',
            'icon' => '🔍',
            'category' => 'variety',
        ],
        'variety_10' => [
            'name' => 'Pivní znalec',
            'description' => 'Vyzkoušej 10 různých piv',
            'icon' => '🎓',
            'category' => 'variety',
        ],
        'variety_25' => [
            'name' => 'Pivní sommeliér',
            'description' => 'Vyzkoušej 25 různých piv',
            'icon' => '🏆',
            'category' => 'variety',
        ],
        'breweries_5' => [
            'name' => 'Turista',
            'description' => 'Ochutnej piva z 5 různých pivovarů',
            'icon' => '🗺️',
            'category' => 'variety',
        ],

        // Čas
        'early_bird' => [
            'name' => 'Ranní ptáče',
            'description' => 'Vypij pivo před 10:00',
            'icon' => '🌅',
            'category' => 'time',
        ],
        'night_owl' => [
            'name' => 'Noční sova',
            'description' => 'Vypij pivo po půlnoci',
            'icon' => '🦉',
            'category' => 'time',
        ],
        'weekend_warrior' => [
            'name' => 'Víkendový válečník',
            'description' => 'Vypij 10 piv během víkendů',
            'icon' => '⚔️',
            'category' => 'time',
        ],

        // Výkony
        'marathon' => [
            'name' => 'Maratonec',
            'description' => 'Vypij 5 piv za jeden den',
            'icon' => '🏃',
            'category' => 'performance',
        ],
        'ultra_marathon' => [
            'name' => 'Ultra maratonec',
            'description' => 'Vypij 10 piv za jeden den',
            'icon' => '🦸',
            'category' => 'performance',
        ],
        'weekly_streak' => [
            'name' => 'Týdenní série',
            'description' => 'Pij pivo každý den celý týden',
            'icon' => '🔥',
            'category' => 'performance',
        ],

        // Speciální
        'size_matters' => [
            'name' => 'Na velikosti záleží',
            'description' => 'Vypij 10 velkých piv (0.5l)',
            'icon' => '📏',
            'category' => 'special',
        ],
        'small_but_mighty' => [
            'name' => 'Malý, ale šikovný',
            'description' => 'Vypij 10 malých piv (0.3l)',
            'icon' => '🐜',
            'category' => 'special',
        ],
        'loyal_fan' => [
            'name' => 'Věrný fanoušek',
            'description' => 'Vypij 10 piv stejné značky',
            'icon' => '💕',
            'category' => 'special',
        ],
    ];

    public function __construct(
        private BeerEntryRepository $entryRepository,
        private GroupMemberRepository $memberRepository,
        private UserAchievementRepository $achievementRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Check all achievements and unlock new ones.
     * Returns array of newly unlocked achievements.
     *
     * @return array<array{id: string, name: string, icon: string}>
     */
    public function checkAndUnlockAchievements(User $user): array
    {
        $stats = $this->calculateUserStats($user);
        $alreadyUnlocked = $this->achievementRepository->getUnlockedIds($user);
        $newlyUnlocked = [];

        foreach ($this->achievementDefinitions as $id => $definition) {
            if (in_array($id, $alreadyUnlocked, true)) {
                continue;
            }

            $shouldUnlock = $this->isAchievementUnlocked($id, $stats);
            if (!$shouldUnlock) {
                continue;
            }

            $achievement = new UserAchievement();
            $achievement->setUser($user);
            $achievement->setAchievementId($id);

            $this->entityManager->persist($achievement);

            $newlyUnlocked[] = [
                'id' => $id,
                'name' => $definition['name'],
                'icon' => $definition['icon'],
            ];
        }

        if (!empty($newlyUnlocked)) {
            $this->entityManager->flush();
        }

        return $newlyUnlocked;
    }

    /**
     * @return string[]
     */
    public function getUnlockedAchievementIds(User $user): array
    {
        return $this->achievementRepository->getUnlockedIds($user);
    }

    public function getUserAchievements(User $user): array
    {
        $stats = $this->calculateUserStats($user);
        $unlockedIds = $this->achievementRepository->getUnlockedIds($user);
        $achievements = [];

        foreach ($this->achievementDefinitions as $id => $definition) {
            $unlocked = in_array($id, $unlockedIds, true);
            $progress = $this->getAchievementProgress($id, $stats);

            $achievements[] = [
                'id' => $id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'category' => $definition['category'],
                'unlocked' => $unlocked,
                'progress' => $progress['current'],
                'target' => $progress['target'],
                'percentage' => $progress['target'] > 0
                    ? min(100, round(($progress['current'] / $progress['target']) * 100))
                    : 100,
            ];
        }

        return $achievements;
    }

    public function getUnlockedAchievements(User $user): array
    {
        return array_filter(
            $this->getUserAchievements($user),
            fn($a) => $a['unlocked']
        );
    }

    public function getAchievementsSummary(User $user): array
    {
        $all = $this->getUserAchievements($user);
        $unlocked = array_filter($all, fn($a) => $a['unlocked']);

        return [
            'total' => count($all),
            'unlocked' => count($unlocked),
            'percentage' => round((count($unlocked) / count($all)) * 100),
            'recent' => array_slice(array_values($unlocked), 0, 3),
        ];
    }

    /**
     * Calculate user stats using optimized aggregated queries
     * Avoids loading all entries into memory
     */
    private function calculateUserStats(User $user): array
    {
        // Get aggregated stats from repository (uses optimized queries)
        $dbStats = $this->entryRepository->getAchievementStatsByUser($user);

        // Get membership info
        $memberships = $this->memberRepository->findBy(['user' => $user]);
        $isFounder = false;
        foreach ($memberships as $membership) {
            if ($membership->getRole() === GroupRole::ADMIN->value) {
                $isFounder = true;
                break;
            }
        }

        return [
            'total_beers' => $dbStats['total_beers'],
            'total_volume_ml' => $dbStats['total_volume_ml'],
            'unique_beers' => $dbStats['unique_beers'],
            'unique_breweries' => $dbStats['unique_breweries'],
            'early_bird' => $dbStats['early_bird'],
            'night_owl' => $dbStats['night_owl'],
            'weekend_beers' => $dbStats['weekend_beers'],
            'large_beers' => $dbStats['large_beers'],
            'small_beers' => $dbStats['small_beers'],
            'max_daily' => $dbStats['max_daily'],
            'max_loyal' => $dbStats['max_loyal'],
            'consecutive_days' => $dbStats['consecutive_days'],
            'group_count' => count($memberships),
            'is_founder' => $isFounder,
        ];
    }

    private function isAchievementUnlocked(string $id, array $stats): bool
    {
        return match ($id) {
            'first_beer' => $stats['total_beers'] >= 1,
            'beer_50' => $stats['total_beers'] >= 50,
            'beer_100' => $stats['total_beers'] >= 100,
            'beer_500' => $stats['total_beers'] >= 500,
            'beer_1000' => $stats['total_beers'] >= 1000,

            'volume_10l' => $stats['total_volume_ml'] >= 10000,
            'volume_50l' => $stats['total_volume_ml'] >= 50000,
            'volume_100l' => $stats['total_volume_ml'] >= 100000,

            'variety_5' => $stats['unique_beers'] >= 5,
            'variety_10' => $stats['unique_beers'] >= 10,
            'variety_25' => $stats['unique_beers'] >= 25,
            'breweries_5' => $stats['unique_breweries'] >= 5,

            'early_bird' => $stats['early_bird'],
            'night_owl' => $stats['night_owl'],
            'weekend_warrior' => $stats['weekend_beers'] >= 10,

            'marathon' => $stats['max_daily'] >= 5,
            'ultra_marathon' => $stats['max_daily'] >= 10,
            'weekly_streak' => $stats['consecutive_days'] >= 7,

            'size_matters' => $stats['large_beers'] >= 10,
            'small_but_mighty' => $stats['small_beers'] >= 10,
            'loyal_fan' => $stats['max_loyal'] >= 10,

            default => false,
        };
    }

    private function getAchievementProgress(string $id, array $stats): array
    {
        return match ($id) {
            'first_beer' => ['current' => min($stats['total_beers'], 1), 'target' => 1],
            'beer_50' => ['current' => min($stats['total_beers'], 50), 'target' => 50],
            'beer_100' => ['current' => min($stats['total_beers'], 100), 'target' => 100],
            'beer_500' => ['current' => min($stats['total_beers'], 500), 'target' => 500],
            'beer_1000' => ['current' => min($stats['total_beers'], 1000), 'target' => 1000],

            'volume_10l' => ['current' => min($stats['total_volume_ml'], 10000) / 1000, 'target' => 10],
            'volume_50l' => ['current' => min($stats['total_volume_ml'], 50000) / 1000, 'target' => 50],
            'volume_100l' => ['current' => min($stats['total_volume_ml'], 100000) / 1000, 'target' => 100],

            'variety_5' => ['current' => min($stats['unique_beers'], 5), 'target' => 5],
            'variety_10' => ['current' => min($stats['unique_beers'], 10), 'target' => 10],
            'variety_25' => ['current' => min($stats['unique_beers'], 25), 'target' => 25],
            'breweries_5' => ['current' => min($stats['unique_breweries'], 5), 'target' => 5],

            'early_bird' => ['current' => $stats['early_bird'] ? 1 : 0, 'target' => 1],
            'night_owl' => ['current' => $stats['night_owl'] ? 1 : 0, 'target' => 1],
            'weekend_warrior' => ['current' => min($stats['weekend_beers'], 10), 'target' => 10],

            'marathon' => ['current' => min($stats['max_daily'], 5), 'target' => 5],
            'ultra_marathon' => ['current' => min($stats['max_daily'], 10), 'target' => 10],
            'weekly_streak' => ['current' => min($stats['consecutive_days'], 7), 'target' => 7],

            'size_matters' => ['current' => min($stats['large_beers'], 10), 'target' => 10],
            'small_but_mighty' => ['current' => min($stats['small_beers'], 10), 'target' => 10],
            'loyal_fan' => ['current' => min($stats['max_loyal'], 10), 'target' => 10],

            default => ['current' => 0, 'target' => 1],
        };
    }
}
