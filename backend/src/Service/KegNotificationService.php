<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use Psr\Log\LoggerInterface;

class KegNotificationService
{
    /**
     * Checked from the biggest down - one entry crossing both sends only the bigger one.
     */
    private const KEG_THRESHOLDS_ML = [
        50000 => 'velký sud (50 l)',
        30000 => 'malý sud (30 l)',
    ];

    public function __construct(
        private BeerEntryRepository $entryRepository,
        private GroupMemberRepository $memberRepository,
        private DrinkingDayService $drinkingDayService,
        private WebPushService $webPushService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Celebration push to the whole group when today's combined volume
     * crosses a keg-sized milestone. Fires once per threshold per day.
     */
    public function notifyIfKegReached(BeerEntry $entry): void
    {
        try {
            $this->doNotify($entry);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send keg notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function doNotify(BeerEntry $entry): void
    {
        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();

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
        $volumeAfter = $this->entryRepository->getMemberVolumeInPeriod($group, $dayStart, $dayEnd);
        $volumeBefore = $volumeAfter - $entry->getVolumeMl() * $entry->getQuantity();

        foreach (self::KEG_THRESHOLDS_ML as $threshold => $label) {
            if ($volumeBefore < $threshold && $volumeAfter >= $threshold) {
                $this->announceKeg($group, $label, $threshold);
                return;
            }
        }
    }

    private function announceKeg(Group $group, string $label, int $threshold): void
    {
        $members = array_map(
            fn ($member) => $member->getUser(),
            $this->memberRepository->findBy(['group' => $group]),
        );

        $this->webPushService->sendToUsers($members, [
            'title' => $group->getName(),
            'body' => sprintf('🛢️ Parta dnes společně vypila %s! Na zdraví! 🍻', $label),
            'url' => '/groups',
            'tag' => sprintf('keg-%s-%d', $group->getId()->toRfc4122(), $threshold),
        ], 'keg');
    }
}
