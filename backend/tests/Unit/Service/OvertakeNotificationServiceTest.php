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
use App\Service\OvertakeNotificationService;
use App\Service\WebPushService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class OvertakeNotificationServiceTest extends TestCase
{
    private OvertakeNotificationService $service;
    private MockObject&BeerEntryRepository $entryRepository;
    private MockObject&GroupMemberRepository $memberRepository;
    private MockObject&WebPushService $webPushService;

    private User $drinker;
    private User $leader;
    private Group $group;

    protected function setUp(): void
    {
        $this->entryRepository = $this->createMock(BeerEntryRepository::class);
        $this->memberRepository = $this->createMock(GroupMemberRepository::class);
        $this->webPushService = $this->createMock(WebPushService::class);

        $this->service = new OvertakeNotificationService(
            $this->entryRepository,
            $this->memberRepository,
            new DrinkingDayService(),
            $this->webPushService,
            new NullLogger(),
        );

        $this->drinker = $this->createUser('Piják');
        $this->leader = $this->createUser('Lídr');

        $this->group = new Group();
        $this->group->setName('Pivní parta');
        $this->group->setCreatedBy($this->drinker);

        $this->memberRepository->method('findBy')->willReturnCallback(function (array $criteria) {
            if (isset($criteria['user'])) {
                return [$this->member($this->drinker)];
            }

            return [$this->member($this->drinker), $this->member($this->leader)];
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

    private function createEntry(\DateTimeImmutable $consumedAt): BeerEntry
    {
        $entry = new BeerEntry();
        $entry->setUser($this->drinker);
        $entry->setVolumeMl(500);
        $entry->setQuantity(1);
        $entry->setConsumedAt($consumedAt);

        return $entry;
    }

    /**
     * @param array<string, float> $before scores without the new entry
     * @param array<string, float> $after  scores including the new entry
     */
    private function mockScores(array $before, array $after): void
    {
        $this->entryRepository->method('getMemberScoresInPeriod')
            ->willReturnCallback(
                fn ($group, $from, $to, $exclude = null) => $exclude !== null ? $before : $after,
            );
    }

    private function scores(float $drinkerScore, float $leaderScore): array
    {
        return [
            $this->drinker->getId()->toRfc4122() => $drinkerScore,
            $this->leader->getId()->toRfc4122() => $leaderScore,
        ];
    }

    public function testNotifiesOvertakenLeader(): void
    {
        $this->mockScores($this->scores(3.0, 4.0), $this->scores(5.0, 4.0));

        $this->webPushService->expects($this->once())
            ->method('sendToUsers')
            ->with(
                [$this->leader],
                $this->callback(fn (array $payload) => str_contains($payload['body'], 'Piják')
                    && str_contains($payload['body'], '5 : 4')
                    && $payload['title'] === 'Pivní parta'),
                'overtake',
            );

        $this->service->notifyIfOvertaken($this->createEntry(new \DateTimeImmutable()));
    }

    public function testNoPushWhenResultIsTie(): void
    {
        $this->mockScores($this->scores(3.0, 4.0), $this->scores(4.0, 4.0));

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfOvertaken($this->createEntry(new \DateTimeImmutable()));
    }

    public function testNoPushWhenDrinkerAlreadyLed(): void
    {
        $this->mockScores($this->scores(5.0, 4.0), $this->scores(6.0, 4.0));

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfOvertaken($this->createEntry(new \DateTimeImmutable()));
    }

    public function testNoPushWhenNobodyDrankBefore(): void
    {
        $this->mockScores([], $this->scores(1.0, 0.0));

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfOvertaken($this->createEntry(new \DateTimeImmutable()));
    }

    public function testNoPushForBackdatedEntry(): void
    {
        $this->mockScores($this->scores(3.0, 4.0), $this->scores(5.0, 4.0));

        $this->webPushService->expects($this->never())->method('sendToUsers');

        $this->service->notifyIfOvertaken($this->createEntry(new \DateTimeImmutable('-2 days')));
    }
}
