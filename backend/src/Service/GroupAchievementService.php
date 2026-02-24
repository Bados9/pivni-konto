<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\UserAchievement;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;

class GroupAchievementService
{
    private const AWARD_DEFINITIONS = [
        'drinker_of_day' => ['icon' => "\u{1F37A}", 'label' => 'Pijan dne'],
        'drinker_of_week' => ['icon' => "\u{1F3C6}", 'label' => 'Pijan týdne'],
        'leader' => ['icon' => "\u{1F451}", 'label' => 'Lídr'],
        'night_rider' => ['icon' => "\u{1F319}", 'label' => 'Noční jezdec'],
        'endurance' => ['icon' => "\u{1F525}", 'label' => 'Vytrvalec'],
        'tankista' => ['icon' => "\u{1F6E2}\u{FE0F}", 'label' => 'Tankista'],
    ];

    public function __construct(
        private BeerEntryRepository $entryRepository,
        private UserAchievementRepository $achievementRepository,
        private GroupRepository $groupRepository,
        private EntityManagerInterface $em,
        private DrinkingDayService $drinkingDayService,
    ) {
    }

    /**
     * Evaluate and persist awards for a specific date (typically yesterday)
     */
    public function evaluateDailyAwards(\DateTimeImmutable $forDate): int
    {
        $groups = $this->groupRepository->findAll();
        $totalSaved = 0;

        foreach ($groups as $group) {
            $totalSaved += $this->evaluateGroupAwards($group, $forDate);
        }

        $this->em->flush();

        return $totalSaved;
    }

    /**
     * Evaluate awards for a single group on a specific date
     */
    public function evaluateGroupAwards(Group $group, \DateTimeImmutable $forDate): int
    {
        $dayStart = $this->drinkingDayService->getDrinkingDayStart($forDate->setTime(12, 0));
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd($forDate->setTime(12, 0));
        $weekStart = new \DateTimeImmutable($forDate->format('o-\\WW') . ' monday 05:00');

        $awards = $this->entryRepository->getGroupAwards($group, $dayStart, $dayEnd, $weekStart);
        $saved = 0;

        foreach ($awards as $type => $awardData) {
            $user = $this->em->getReference('App\Entity\User', $awardData['userId']);

            // Dedup: check if user already has this achievement for this date
            if ($this->achievementRepository->hasAchievementOnDate($user, $type, $forDate)) {
                continue;
            }

            $achievement = new UserAchievement();
            $achievement->setUser($user);
            $achievement->setAchievementId($type);
            $achievement->setUnlockedAt($forDate->setTime(12, 0));

            $this->em->persist($achievement);
            $saved++;
        }

        return $saved;
    }

    /**
     * Get award definitions with icons/labels (for frontend)
     */
    public static function getAwardDefinitions(): array
    {
        return self::AWARD_DEFINITIONS;
    }
}
