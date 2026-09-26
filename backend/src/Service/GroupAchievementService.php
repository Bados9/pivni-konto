<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class GroupAchievementService
{
    public function __construct(
        private BeerEntryRepository $entryRepository,
        private UserAchievementRepository $achievementRepository,
        private GroupRepository $groupRepository,
        private EntityManagerInterface $em,
        private DrinkingDayService $drinkingDayService,
        private GroupAwardNotifier $awardNotifier,
    ) {
    }

    /**
     * Evaluate and persist group achievements for a specific date.
     * Daily (drinker_of_day) runs every day.
     * Weekly (drinker_of_week) runs when forDate is Sunday (end of drinking week).
     * Monthly (drinker_of_month) runs when forDate is the last day of the month.
     *
     * With $notify the winner gets an in-app notification and a web push
     * (disable for backfills to avoid flooding users with historic awards).
     */
    public function evaluateGroupAchievements(\DateTimeImmutable $forDate, bool $notify = true): int
    {
        $groups = $this->groupRepository->findAll();
        $totalSaved = 0;

        foreach ($groups as $group) {
            $totalSaved += $this->evaluateGroup($group, $forDate, $notify);
        }

        $this->em->flush();

        return $totalSaved;
    }

    /**
     * Re-evaluate a past date's awards against CURRENT entry data.
     * Backdated or deleted entries can change who actually won: wrong holders
     * lose the award, the rightful winner gains it. Corrections create a bell
     * notification for the affected users, but never a push.
     *
     * @return array{int, int} rows added, rows removed
     */
    public function reconcileGroupAchievements(\DateTimeImmutable $forDate, bool $dryRun = false): array
    {
        $winnersByType = array_fill_keys($this->evaluatedTypes($forDate), []);

        foreach ($this->groupRepository->findAll() as $group) {
            foreach ($this->computeAwards($group, $forDate) as $type => $awardData) {
                $winnersByType[$type][$awardData['userId']] = true;
            }
        }

        $added = 0;
        $removed = 0;

        foreach ($winnersByType as $type => $winnerIds) {
            [$typeAdded, $typeRemoved] = $this->reconcileType($type, $winnerIds, $forDate, $dryRun);
            $added += $typeAdded;
            $removed += $typeRemoved;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return [$added, $removed];
    }

    /**
     * @param array<string, true> $winnerIds rfc4122 user ids of the rightful winners
     *
     * @return array{int, int} rows added, rows removed
     */
    private function reconcileType(string $type, array $winnerIds, \DateTimeImmutable $forDate, bool $dryRun): array
    {
        $added = 0;
        $removed = 0;
        $holderIds = [];
        $revokedNotified = [];

        foreach ($this->achievementRepository->findByAchievementOnDate($type, $forDate) as $row) {
            $userId = $row->getUser()->getId()->toRfc4122();
            $isWrongHolder = !isset($winnerIds[$userId]);
            $isDuplicate = isset($holderIds[$userId]);

            if ($isWrongHolder || $isDuplicate) {
                $removed++;
                if ($dryRun) {
                    continue;
                }

                $this->em->remove($row);

                // duplicates of the rightful holder vanish silently
                if ($isWrongHolder && !isset($revokedNotified[$userId])) {
                    $revokedNotified[$userId] = true;
                    $this->awardNotifier->notifyAwardRevoked($row->getUser(), $type, $forDate);
                }
                continue;
            }

            $holderIds[$userId] = true;
        }

        foreach (array_keys($winnerIds) as $userId) {
            if (isset($holderIds[$userId])) {
                continue;
            }

            $added++;
            if ($dryRun) {
                continue;
            }

            $user = $this->em->getReference(User::class, Uuid::fromString($userId));

            $achievement = new UserAchievement();
            $achievement->setUser($user);
            $achievement->setAchievementId($type);
            $achievement->setUnlockedAt($forDate->setTime(12, 0));
            $this->em->persist($achievement);

            $this->awardNotifier->notifyAwardGrantedRetroactively($user, $type, $forDate);
        }

        return [$added, $removed];
    }

    /**
     * Which award types close on the given date.
     *
     * @return string[]
     */
    private function evaluatedTypes(\DateTimeImmutable $forDate): array
    {
        $types = ['drinker_of_day'];

        if ((int) $forDate->format('N') === 7) {
            $types[] = 'drinker_of_week';
        }

        if ($forDate->format('j') === $forDate->format('t')) {
            $types[] = 'drinker_of_month';
        }

        return $types;
    }

    private function evaluateGroup(Group $group, \DateTimeImmutable $forDate, bool $notify): int
    {
        $awards = $this->computeAwards($group, $forDate);
        $saved = 0;

        foreach ($awards as $type => $awardData) {
            $user = $this->em->getReference(User::class, Uuid::fromString($awardData['userId']));

            if ($this->achievementRepository->hasAchievementOnDate($user, $type, $forDate)) {
                continue;
            }

            $achievement = new UserAchievement();
            $achievement->setUser($user);
            $achievement->setAchievementId($type);
            $achievement->setUnlockedAt($forDate->setTime(12, 0));

            $this->em->persist($achievement);
            $saved++;

            if ($notify) {
                $this->awardNotifier->notifyWinner($user, $group, $type, $forDate);
            }
        }

        return $saved;
    }

    private function computeAwards(Group $group, \DateTimeImmutable $forDate): array
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

        return $this->entryRepository->getGroupAwards(
            $group, $dayStart, $dayEnd, $weekStart, $weekEnd, $monthStart, $monthEnd
        );
    }
}
