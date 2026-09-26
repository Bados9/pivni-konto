<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\User;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use Psr\Log\LoggerInterface;

class OvertakeNotificationService
{
    public function __construct(
        private BeerEntryRepository $entryRepository,
        private GroupMemberRepository $memberRepository,
        private DrinkingDayService $drinkingDayService,
        private WebPushService $webPushService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Push to the member(s) who led today's group ranking until this entry
     * knocked them off the top. Only a strict overtake counts - ties stay quiet.
     */
    public function notifyIfOvertaken(BeerEntry $entry): void
    {
        try {
            $this->doNotify($entry);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send overtake notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function doNotify(BeerEntry $entry): void
    {
        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();

        // a backdated entry doesn't change today's live ranking moment
        if ($entry->getConsumedAt() < $dayStart || $entry->getConsumedAt() >= $dayEnd) {
            return;
        }

        foreach ($this->memberRepository->findBy(['user' => $entry->getUser()]) as $membership) {
            $this->notifyGroup($membership->getGroup(), $entry, $dayStart, $dayEnd);
        }
    }

    private function notifyGroup(
        Group $group,
        BeerEntry $entry,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): void {
        $drinkerId = $entry->getUser()->getId()->toRfc4122();

        $scoresBefore = $this->entryRepository->getMemberScoresInPeriod($group, $dayStart, $dayEnd, $entry);
        if ($scoresBefore === []) {
            return;
        }

        $maxBefore = max($scoresBefore);
        if ($maxBefore <= 0.0) {
            return;
        }

        // the drinker already (co-)led - there was nobody above them to overtake
        if (($scoresBefore[$drinkerId] ?? 0.0) >= $maxBefore) {
            return;
        }

        $scoresAfter = $this->entryRepository->getMemberScoresInPeriod($group, $dayStart, $dayEnd);
        $drinkerScore = $scoresAfter[$drinkerId] ?? 0.0;

        // a tie is not an overtake
        if ($drinkerScore <= $maxBefore) {
            return;
        }

        $overtakenLeaders = $this->findOvertakenLeaders($group, $scoresBefore, $maxBefore, $drinkerId);
        if ($overtakenLeaders === []) {
            return;
        }

        $this->webPushService->sendToUsers($overtakenLeaders, [
            'title' => $group->getName(),
            'body' => sprintf(
                '😱 %s tě právě přeskočil/a v denním pořadí (%s : %s)!',
                $entry->getUser()->getName(),
                ScoreFormatter::score($drinkerScore),
                ScoreFormatter::score($maxBefore),
            ),
            'url' => '/groups',
            'tag' => 'overtake-' . $group->getId()->toRfc4122(),
        ], 'overtake');
    }

    /**
     * @param array<string, float> $scoresBefore
     *
     * @return User[]
     */
    private function findOvertakenLeaders(Group $group, array $scoresBefore, float $maxBefore, string $drinkerId): array
    {
        $leaders = [];

        foreach ($this->memberRepository->findBy(['group' => $group]) as $member) {
            $memberId = $member->getUser()->getId()->toRfc4122();

            if ($memberId === $drinkerId) {
                continue;
            }

            if (($scoresBefore[$memberId] ?? 0.0) < $maxBefore) {
                continue;
            }

            $leaders[] = $member->getUser();
        }

        return $leaders;
    }
}
