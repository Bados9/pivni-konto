<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BeerEntry;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;

class FirstBeerNotificationService
{
    public function __construct(
        private BeerEntryRepository $entryRepository,
        private GroupMemberRepository $memberRepository,
        private DrinkingDayService $drinkingDayService,
        private WebPushService $webPushService,
    ) {
    }

    public function notifyIfFirstBeerInGroup(BeerEntry $entry): void
    {
        $group = $entry->getGroup();
        if ($group === null) {
            return;
        }

        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();

        $existingCount = $this->entryRepository->countGroupEntriesInPeriod(
            $group,
            $dayStart,
            $dayEnd,
            $entry,
        );

        if ($existingCount > 0) {
            return;
        }

        $members = $this->memberRepository->findBy(['group' => $group]);
        $usersToNotify = [];
        foreach ($members as $member) {
            if ($member->getUser()->getId()->toRfc4122() === $entry->getUser()->getId()->toRfc4122()) {
                continue;
            }
            $usersToNotify[] = $member->getUser();
        }

        if (empty($usersToNotify)) {
            return;
        }

        $this->webPushService->sendToUsers($usersToNotify, [
            'title' => $group->getName(),
            'body' => $entry->getUser()->getName() . ' dnes otevřel/a první pivo! 🍺',
            'url' => '/groups',
            'tag' => 'first-beer-' . $group->getId()->toRfc4122(),
        ]);
    }
}
