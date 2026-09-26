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
use App\Service\FirstBeerNotificationService;
use App\Service\WebPushService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FirstBeerNotificationServiceTest extends TestCase
{
    private FirstBeerNotificationService $service;
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

        $this->service = new FirstBeerNotificationService(
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
    }

    private function createUser(string $name): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail(uniqid() . '@example.com');
        $user->setPassword('hash');

        return $user;
    }

    private function createEntry(\DateTimeImmutable $consumedAt): BeerEntry
    {
        $entry = new BeerEntry();
        $entry->setUser($this->drinker);
        $entry->setVolumeMl(500);
        $entry->setQuantity(1);
        $entry->setConsumedAt($consumedAt);

        return $entry;
    }

    private function member(User $user): GroupMember
    {
        $member = new GroupMember();
        $member->setUser($user);
        $member->setGroup($this->group);
        $member->setRole('member');

        return $member;
    }

    private function mockMemberships(): void
    {
        $this->memberRepository->method('findBy')->willReturnCallback(function (array $criteria) {
            if (isset($criteria['user'])) {
                return [$this->member($this->drinker)];
            }

            return [$this->member($this->drinker), $this->member($this->buddy)];
        });
    }

    public function testNotifiesOtherMembersOnFirstBeerOfDay(): void
    {
        $this->mockMemberships();
        $this->entryRepository->method('countMemberEntriesInPeriod')->willReturn(0);

        $this->webPushService->expects($this->once())
            ->method('sendToUsers')
            ->with(
                [$this->buddy],
                $this->callback(fn (array $payload) => str_contains($payload['body'], 'Piják')
                    && $payload['title'] === 'Pivní parta'),
                'first_beer',
            );

        $this->service->notifyIfFirstBeerInGroup($this->createEntry(new \DateTimeImmutable()));
    }

    public function testNoPushWhenGroupAlreadyDrankToday(): void
    {
        $this->mockMemberships();
        $this->entryRepository->method('countMemberEntriesInPeriod')->willReturn(3);

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfFirstBeerInGroup($this->createEntry(new \DateTimeImmutable()));
    }

    public function testNoPushForBackdatedEntry(): void
    {
        $this->mockMemberships();
        $this->entryRepository->method('countMemberEntriesInPeriod')->willReturn(0);

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfFirstBeerInGroup($this->createEntry(new \DateTimeImmutable('-2 days')));
    }

    public function testNoPushWhenDrinkerHasNoGroups(): void
    {
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->entryRepository->method('countMemberEntriesInPeriod')->willReturn(0);

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfFirstBeerInGroup($this->createEntry(new \DateTimeImmutable()));
    }
}
