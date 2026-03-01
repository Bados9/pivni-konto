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
            'name' => 'Kýbl',
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
        'variety_15' => [
            'name' => 'Pivní znalec',
            'description' => 'Vyzkoušej 15 různých piv',
            'icon' => '🎓',
            'category' => 'variety',
        ],
        'variety_30' => [
            'name' => 'Pivní sommeliér',
            'description' => 'Vyzkoušej 30 různých piv',
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
        'weekend_warrior' => [
            'name' => 'Víkendový válečník',
            'description' => 'Vypij 30 piv během víkendů',
            'icon' => '⚔️',
            'category' => 'time',
        ],

        // Výkony
        'daily_10' => [
            'name' => 'Bezedný',
            'description' => 'Vypij 10 piv za jeden den',
            'icon' => '🕳️',
            'category' => 'performance',
            'repeatable' => true,
        ],
        'daily_15' => [
            'name' => 'Už brzdi',
            'description' => 'Vypij 15 piv za jeden den',
            'icon' => '🛑',
            'category' => 'performance',
            'repeatable' => true,
        ],
        'weekly_streak' => [
            'name' => 'Perfektní týden',
            'description' => 'Pij pivo každý den celý týden',
            'icon' => '🔥',
            'category' => 'performance',
        ],

        // Speciální
        'small_but_mighty' => [
            'name' => 'Malý, ale šikovný',
            'description' => 'Vypij 10 malých piv (0.3l)',
            'icon' => '🐜',
            'category' => 'special',
        ],
        'loyal_fan' => [
            'name' => 'Věrný fanoušek',
            'description' => 'Vypij 100 piv stejné značky',
            'icon' => '💕',
            'category' => 'special',
        ],
        'loyal_fan_500' => [
            'name' => 'Ambasador',
            'description' => 'Vypij 500 piv stejné značky',
            'icon' => '🫡',
            'category' => 'special',
        ],

        // Skupinové - ocenění
        'drinker_of_day' => [
            'name' => 'Pijan dne',
            'description' => 'Vypij nejvíc piv ve skupině za den',
            'icon' => '🍻',
            'category' => 'group',
            'repeatable' => true,
        ],
        'drinker_of_week' => [
            'name' => 'Pijan týdne',
            'description' => 'Vypij nejvíc piv ve skupině za týden',
            'icon' => '📅',
            'category' => 'group',
            'repeatable' => true,
        ],
        'drinker_of_month' => [
            'name' => 'Pijan měsíce',
            'description' => 'Vypij nejvíc piv ve skupině za měsíc',
            'icon' => '🏅',
            'category' => 'group',
            'repeatable' => true,
        ],

        // Skupinové - milníky
        'regular_drinker' => [
            'name' => 'Pravidelný pijan',
            'description' => 'Staň se pijakem dne 10×',
            'icon' => '🎖️',
            'category' => 'group',
        ],
        'unbeatable' => [
            'name' => 'Neporazitelný',
            'description' => 'Buď pijakem dne 3 dny po sobě',
            'icon' => '💎',
            'category' => 'group',
        ],
        'monthly_champion' => [
            'name' => 'Měsíční šampion',
            'description' => 'Staň se pijakem měsíce 3×',
            'icon' => '🌟',
            'category' => 'group',
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
     * @return array<array{id: string, name: string, icon: string, timesUnlocked?: int}>
     */
    public function checkAndUnlockAchievements(User $user): array
    {
        $stats = $this->calculateUserStats($user);
        $unlockedCounts = $this->achievementRepository->getUnlockedWithCounts($user);
        $newlyUnlocked = [];

        foreach ($this->achievementDefinitions as $id => $definition) {
            $isRepeatable = $definition['repeatable'] ?? false;
            $currentCount = $unlockedCounts[$id] ?? 0;
            $alreadyHas = $currentCount > 0;

            // Non-repeatable achievements - skip if already unlocked
            if ($alreadyHas && !$isRepeatable) {
                continue;
            }

            // For repeatable achievements, check if user earned more unlocks
            if ($isRepeatable) {
                $targetCount = $this->getRepeatableCount($id, $stats);

                if ($targetCount <= $currentCount) {
                    continue;
                }

                // Create new rows for the difference
                $newCount = $targetCount - $currentCount;
                for ($i = 0; $i < $newCount; $i++) {
                    $achievement = new UserAchievement();
                    $achievement->setUser($user);
                    $achievement->setAchievementId($id);
                    $this->entityManager->persist($achievement);
                }

                $newlyUnlocked[] = [
                    'id' => $id,
                    'name' => $definition['name'],
                    'icon' => $definition['icon'],
                    'timesUnlocked' => $targetCount,
                ];
                continue;
            }

            // Non-repeatable achievement - check if should unlock
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
     * Get total count of times a repeatable achievement should be unlocked
     */
    private function getRepeatableCount(string $id, array $stats): int
    {
        return match ($id) {
            'daily_10' => $stats['days_with_10_beers'] ?? 0,
            'daily_15' => $stats['days_with_15_beers'] ?? 0,
            'drinker_of_day' => $stats['drinker_of_day_count'],
            'drinker_of_week' => $stats['drinker_of_week_count'],
            'drinker_of_month' => $stats['drinker_of_month_count'],
            default => 0,
        };
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
        $unlockedCounts = $this->achievementRepository->getUnlockedWithCounts($user);
        $achievements = [];

        foreach ($this->achievementDefinitions as $id => $definition) {
            $isRepeatable = $definition['repeatable'] ?? false;
            $timesUnlocked = $unlockedCounts[$id] ?? 0;
            $unlocked = $timesUnlocked > 0;
            $progress = $this->getAchievementProgress($id, $stats);

            $achievements[] = [
                'id' => $id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'category' => $definition['category'],
                'unlocked' => $unlocked,
                'repeatable' => $isRepeatable,
                'timesUnlocked' => $timesUnlocked,
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

        // Group award stats (from UserAchievement rows with drinker_of_day achievementId)
        $drinkerOfDayCount = $this->achievementRepository->countByUserAndAchievement($user, 'drinker_of_day');
        $drinkerOfDayConsecutive = $this->achievementRepository->getMaxConsecutiveDays($user, 'drinker_of_day');
        $drinkerOfWeekCount = $this->achievementRepository->countByUserAndAchievement($user, 'drinker_of_week');
        $drinkerOfMonthCount = $this->achievementRepository->countByUserAndAchievement($user, 'drinker_of_month');

        return [
            'total_beers' => $dbStats['total_beers'],
            'total_volume_ml' => $dbStats['total_volume_ml'],
            'unique_beers' => $dbStats['unique_beers'],
            'unique_breweries' => $dbStats['unique_breweries'],
            'early_bird' => $dbStats['early_bird'],
            'weekend_beers' => $dbStats['weekend_beers'],
            'small_beers' => $dbStats['small_beers'],
            'max_daily' => $dbStats['max_daily'],
            'max_loyal' => $dbStats['max_loyal'],
            'consecutive_days' => $dbStats['consecutive_days'],
            'days_with_10_beers' => $dbStats['days_with_10_beers'],
            'days_with_15_beers' => $dbStats['days_with_15_beers'],
            'group_count' => count($memberships),
            'is_founder' => $isFounder,
            'drinker_of_day_count' => $drinkerOfDayCount,
            'drinker_of_day_consecutive' => $drinkerOfDayConsecutive,
            'drinker_of_week_count' => $drinkerOfWeekCount,
            'drinker_of_month_count' => $drinkerOfMonthCount,
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
            'variety_15' => $stats['unique_beers'] >= 15,
            'variety_30' => $stats['unique_beers'] >= 30,
            'breweries_5' => $stats['unique_breweries'] >= 5,

            'early_bird' => $stats['early_bird'],
            'weekend_warrior' => $stats['weekend_beers'] >= 30,

            'daily_10' => $stats['max_daily'] >= 10,
            'daily_15' => $stats['max_daily'] >= 15,
            'weekly_streak' => $stats['consecutive_days'] >= 7,

            'small_but_mighty' => $stats['small_beers'] >= 10,
            'loyal_fan' => $stats['max_loyal'] >= 100,
            'loyal_fan_500' => $stats['max_loyal'] >= 500,

            'regular_drinker' => $stats['drinker_of_day_count'] >= 10,
            'unbeatable' => $stats['drinker_of_day_consecutive'] >= 3,
            'monthly_champion' => $stats['drinker_of_month_count'] >= 3,

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
            'variety_15' => ['current' => min($stats['unique_beers'], 15), 'target' => 15],
            'variety_30' => ['current' => min($stats['unique_beers'], 30), 'target' => 30],
            'breweries_5' => ['current' => min($stats['unique_breweries'], 5), 'target' => 5],

            'early_bird' => ['current' => $stats['early_bird'] ? 1 : 0, 'target' => 1],
            'weekend_warrior' => ['current' => min($stats['weekend_beers'], 30), 'target' => 30],

            'daily_10' => ['current' => min($stats['max_daily'], 10), 'target' => 10],
            'daily_15' => ['current' => min($stats['max_daily'], 15), 'target' => 15],
            'weekly_streak' => ['current' => min($stats['consecutive_days'], 7), 'target' => 7],

            'small_but_mighty' => ['current' => min($stats['small_beers'], 10), 'target' => 10],
            'loyal_fan' => ['current' => min($stats['max_loyal'], 100), 'target' => 100],
            'loyal_fan_500' => ['current' => min($stats['max_loyal'], 500), 'target' => 500],

            'drinker_of_day' => ['current' => min($stats['drinker_of_day_count'], 1), 'target' => 1],
            'drinker_of_week' => ['current' => min($stats['drinker_of_week_count'], 1), 'target' => 1],
            'drinker_of_month' => ['current' => min($stats['drinker_of_month_count'], 1), 'target' => 1],

            'regular_drinker' => ['current' => min($stats['drinker_of_day_count'], 10), 'target' => 10],
            'unbeatable' => ['current' => min($stats['drinker_of_day_consecutive'], 3), 'target' => 3],
            'monthly_champion' => ['current' => min($stats['drinker_of_month_count'], 3), 'target' => 3],

            default => ['current' => 0, 'target' => 1],
        };
    }
}
