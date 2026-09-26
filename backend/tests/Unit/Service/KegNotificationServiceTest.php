<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use App\Service\DrinkingDayService;
use App\Service\KegNotificationService;
use App\Service\WebPushService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class KegNotificationServiceTest extends TestCase
{
    private KegNotificationService $service;
    private MockObject&BeerEntryRepository $entryRepository;
    private MockObject&GroupMemberRepository $memberRepository;
    private MockObject&WebPushService $webPushService;

    private User $drinker;
    private User $buddy;
    private Group $group;

    protected function setUp(): void
    {
        $this->entryRepository = $this->createMock(BeerEntryRepository::class);
        $this->memberRepository = $this->createMock(GroupMemberRepository::class);
        $this->webPushService = $this->createMock(WebPushService::class);

        $this->service = new KegNotificationService(
            $this->entryRepository,
            $this->memberRepository,
            new DrinkingDayService(),
            $this->webPushService,
            new NullLogger(),
        );

        $this->drinker = $this->createUser('Piják');
        $this->buddy = $this->createUser('Parťák');

        $this->group = new Group();
        $this->group->setName('Pivní parta');
        $this->group->setCreatedBy($this->drinker);

        $this->memberRepository->method('findBy')->willReturnCallback(function (array $criteria) {
            if (isset($criteria['user'])) {
                return [$this->member($this->drinker)];
            }

            return [$this->member($this->drinker), $this->member($this->buddy)];
        });
    }

    private function createUser(string $name): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail(uniqid() . '@example.com');
        $user->setPassword('hash');

        return $user;
    }

    private function member(User $user): GroupMember
    {
        $member = new GroupMember();
        $member->setUser($user);
        $member->setGroup($this->group);
        $member->setRole('member');

        return $member;
    }

    private function createEntry(int $volumeMl, int $quantity = 1, ?\DateTimeImmutable $consumedAt = null): BeerEntry
    {
        $entry = new BeerEntry();
        $entry->setUser($this->drinker);
        $entry->setVolumeMl($volumeMl);
        $entry->setQuantity($quantity);
        $entry->setConsumedAt($consumedAt ?? new \DateTimeImmutable());

        return $entry;
    }

    public function testCrossingSmallKegNotifiesWholeGroup(): void
    {
        // 29 700 ml before + 500 ml entry = 30 200 ml
        $this->entryRepository->method('getMemberVolumeInPeriod')->willReturn(30200);

        $this->webPushService->expects($this->once())
            ->method('sendToUsers')
            ->with(
                [$this->drinker, $this->buddy],
                $this->callback(fn (array $payload) => str_contains($payload['body'], 'malý sud')
                    && $payload['title'] === 'Pivní parta'),
                'keg',
            );

        $this->service->notifyIfKegReached($this->createEntry(500));
    }

    public function testCrossingBothThresholdsSendsOnlyBigKeg(): void
    {
        // 28 000 ml before + 24 × 1000 ml = 52 000 ml: 30 l and 50 l crossed at once
        $this->entryRepository->method('getMemberVolumeInPeriod')->willReturn(52000);

        $this->webPushService->expects($this->once())
            ->method('sendToUsers')
            ->with(
                $this->anything(),
                $this->callback(fn (array $payload) => str_contains($payload['body'], 'velký sud')),
                'keg',
            );

        $this->service->notifyIfKegReached($this->createEntry(1000, 24));
    }

    public function testNoPushWithoutCrossingThreshold(): void
    {
        // 25 000 ml before + 500 ml = 25 500 ml, still under the small keg
        $this->entryRepository->method('getMemberVolumeInPeriod')->willReturn(25500);

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfKegReached($this->createEntry(500));
    }

    public function testNoPushWhenThresholdWasAlreadyCrossed(): void
    {
        // 30 500 ml before + 500 ml = 31 000 ml, small keg was already announced
        $this->entryRepository->method('getMemberVolumeInPeriod')->willReturn(31000);

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfKegReached($this->createEntry(500));
    }

    public function testNoPushForBackdatedEntry(): void
    {
        $this->entryRepository->method('getMemberVolumeInPeriod')->willReturn(30200);

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfKegReached($this->createEntry(500, 1, new \DateTimeImmutable('-2 days')));
    }
}
