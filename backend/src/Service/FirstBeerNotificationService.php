<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use Psr\Log\LoggerInterface;

class FirstBeerNotificationService
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
     * Entries are personal (no group attribution) - the drinker's memberships
     * define which groups get the "first beer of the day" push.
     */
    public function notifyIfFirstBeerInGroup(BeerEntry $entry): void
    {
        try {
            $this->doNotify($entry);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send first beer notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function doNotify(BeerEntry $entry): void
    {
        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();

        // a backdated entry is not "today's first beer"
        if ($entry->getConsumedAt() < $dayStart || $entry->getConsumedAt() >= $dayEnd) {
            return;
        }

        $memberships = $this->memberRepository->findBy(['user' => $entry->getUser()]);

        foreach ($memberships as $membership) {
            $this->notifyGroupIfFirst($membership->getGroup(), $entry, $dayStart, $dayEnd);
        }
    }

    private function notifyGroupIfFirst(
        Group $group,
        BeerEntry $entry,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): void {
        $existingCount = $this->entryRepository->countMemberEntriesInPeriod($group, $dayStart, $dayEnd, $entry);
        if ($existingCount > 0) {
            return;
        }

        $usersToNotify = [];
        foreach ($this->memberRepository->findBy(['group' => $group]) as $member) {
            if ($member->getUser()->getId()->toRfc4122() === $entry->getUser()->getId()->toRfc4122()) {
                continue;
            }
            $usersToNotify[] = $member->getUser();
        }

        if ($usersToNotify === []) {
            return;
        }

        $this->webPushService->sendToUsers($usersToNotify, [
            'title' => $group->getName(),
            'body' => $entry->getUser()->getName() . ' dnes otevřel/a první pivo! 🍺',
            'url' => '/groups',
            'tag' => 'first-beer-' . $group->getId()->toRfc4122(),
        ], 'first_beer');
    }
}
