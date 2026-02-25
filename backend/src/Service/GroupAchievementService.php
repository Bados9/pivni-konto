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
    private const ACHIEVEMENT_DEFINITIONS = [
        'drinker_of_day' => ['icon' => "\u{1F37A}", 'label' => 'Pijan dne'],
        'drinker_of_week' => ['icon' => "\u{1F3C6}", 'label' => 'Pijan týdne'],
        'drinker_of_month' => ['icon' => "\u{1F31F}", 'label' => 'Pijan měsíce'],
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
     * Evaluate and persist group achievements for a specific date.
     * Daily (drinker_of_day) runs every day.
     * Weekly (drinker_of_week) runs when forDate is Sunday (end of drinking week).
     * Monthly (drinker_of_month) runs when forDate is the last day of the month.
     */
    public function evaluateGroupAchievements(\DateTimeImmutable $forDate): int
    {
        $groups = $this->groupRepository->findAll();
        $totalSaved = 0;

        foreach ($groups as $group) {
            $totalSaved += $this->evaluateGroup($group, $forDate);
        }

        $this->em->flush();

        return $totalSaved;
    }

    private function evaluateGroup(Group $group, \DateTimeImmutable $forDate): int
    {
        $dayStart = $this->drinkingDayService->getDrinkingDayStart($forDate->setTime(12, 0));
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd($forDate->setTime(12, 0));

        $weekStart = null;
        $weekEnd = null;
        $monthStart = null;
        $monthEnd = null;

        // Weekly: evaluate when forDate is Sunday (completed drinking week Mon-Sun)
        if ((int) $forDate->format('N') === 7) {
            $monday = $forDate->modify('last monday');
            $weekStart = new \DateTimeImmutable($monday->format('Y-m-d') . ' 05:00');
            $weekEnd = $weekStart->modify('+7 days');
        }

        // Monthly: evaluate on last day of month
        if ($forDate->format('j') === $forDate->format('t')) {
            $monthStart = new \DateTimeImmutable($forDate->format('Y-m-01') . ' 05:00');
            $nextMonth = $forDate->modify('first day of next month');
            $monthEnd = new \DateTimeImmutable($nextMonth->format('Y-m-d') . ' 05:00');
        }

        $awards = $this->entryRepository->getGroupAwards(
            $group, $dayStart, $dayEnd, $weekStart, $weekEnd, $monthStart, $monthEnd
        );

        $saved = 0;

        foreach ($awards as $type => $awardData) {
            $user = $this->em->getReference('App\Entity\User', $awardData['userId']);

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

    public static function getAchievementDefinitions(): array
    {
        return self::ACHIEVEMENT_DEFINITIONS;
    }
}
