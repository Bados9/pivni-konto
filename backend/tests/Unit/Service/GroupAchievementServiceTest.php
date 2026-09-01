<?php

namespace App\Tests\Unit\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupRepository;
use App\Repository\UserAchievementRepository;
use App\Service\DrinkingDayService;
use App\Service\GroupAchievementService;
use App\Service\GroupAwardNotifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GroupAchievementServiceTest extends TestCase
{
    private const WINNER_UUID = '01912345-0000-7000-8000-000000000001';

    private GroupAchievementService $service;
    private MockObject&BeerEntryRepository $entryRepository;
    private MockObject&UserAchievementRepository $achievementRepository;
    private MockObject&GroupRepository $groupRepository;
    private MockObject&EntityManagerInterface $em;
    private MockObject&GroupAwardNotifier $awardNotifier;
    private Group $group;

    protected function setUp(): void
    {
        $this->entryRepository = $this->createMock(BeerEntryRepository::class);
        $this->achievementRepository = $this->createMock(UserAchievementRepository::class);
        $this->groupRepository = $this->createMock(GroupRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->awardNotifier = $this->createMock(GroupAwardNotifier::class);

        $this->service = new GroupAchievementService(
            $this->entryRepository,
            $this->achievementRepository,
            $this->groupRepository,
            $this->em,
            new DrinkingDayService(),
            $this->awardNotifier,
        );

        $this->group = new Group();
        $this->group->setName('Test Group');
        $this->groupRepository->method('findAll')->willReturn([$this->group]);
    }

    private function winnerAward(): array
    {
        return [
            'userId' => self::WINNER_UUID,
            'userName' => 'Winner',
            'value' => 5.0,
        ];
    }

    public function testRegularDayEvaluatesOnlyDailyPeriod(): void
    {
        // Tuesday, not last day of month
        $forDate = new \DateTimeImmutable('2026-08-25');

        $this->entryRepository->expects($this->once())
            ->method('getGroupAwards')
            ->with(
                $this->group,
                new \DateTimeImmutable('2026-08-25 05:00'),
                new \DateTimeImmutable('2026-08-26 05:00'),
                null,
                null,
                null,
                null,
            )
            ->willReturn([]);

        $saved = $this->service->evaluateGroupAchievements($forDate);

        $this->assertSame(0, $saved);
    }

    public function testSundayEvaluatesWeeklyPeriod(): void
    {
        $forDate = new \DateTimeImmutable('2026-08-30');

        $this->entryRepository->expects($this->once())
            ->method('getGroupAwards')
            ->with(
                $this->group,
                new \DateTimeImmutable('2026-08-30 05:00'),
                new \DateTimeImmutable('2026-08-31 05:00'),
                new \DateTimeImmutable('2026-08-24 05:00'),
                new \DateTimeImmutable('2026-08-31 05:00'),
                null,
                null,
            )
            ->willReturn([]);

        $this->service->evaluateGroupAchievements($forDate);
    }

    public function testLastDayOfMonthEvaluatesMonthlyPeriod(): void
    {
        $forDate = new \DateTimeImmutable('2026-08-31');

        $this->entryRepository->expects($this->once())
            ->method('getGroupAwards')
            ->with(
                $this->group,
                new \DateTimeImmutable('2026-08-31 05:00'),
                new \DateTimeImmutable('2026-09-01 05:00'),
                null,
                null,
                new \DateTimeImmutable('2026-08-01 05:00'),
                new \DateTimeImmutable('2026-09-01 05:00'),
            )
            ->willReturn([]);

        $this->service->evaluateGroupAchievements($forDate);
    }

    public function testPersistsAwardForWinner(): void
    {
        $forDate = new \DateTimeImmutable('2026-08-25');
        $winner = new User();

        $this->entryRepository->method('getGroupAwards')
            ->willReturn(['drinker_of_day' => $this->winnerAward()]);
        $this->achievementRepository->method('hasAchievementOnDate')->willReturn(false);
        $this->em->method('getReference')->willReturn($winner);

        $persisted = null;
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (UserAchievement $achievement) use (&$persisted) {
                $persisted = $achievement;
            });
        $this->em->expects($this->once())->method('flush');

        $saved = $this->service->evaluateGroupAchievements($forDate);

        $this->assertSame(1, $saved);
        $this->assertSame('drinker_of_day', $persisted->getAchievementId());
        $this->assertSame($winner, $persisted->getUser());
        $this->assertEquals(new \DateTimeImmutable('2026-08-25 12:00'), $persisted->getUnlockedAt());
    }

    public function testWinnerIsNotifiedByDefault(): void
    {
        $forDate = new \DateTimeImmutable('2026-08-25');
        $winner = new User();

        $this->entryRepository->method('getGroupAwards')
            ->willReturn(['drinker_of_day' => $this->winnerAward()]);
        $this->achievementRepository->method('hasAchievementOnDate')->willReturn(false);
        $this->em->method('getReference')->willReturn($winner);

        $this->awardNotifier->expects($this->once())
            ->method('notifyWinner')
            ->with($winner, $this->group, 'drinker_of_day', $forDate);

        $this->service->evaluateGroupAchievements($forDate);
    }

    public function testWinnerIsNotNotifiedWhenNotifyDisabled(): void
    {
        $forDate = new \DateTimeImmutable('2026-08-25');

        $this->entryRepository->method('getGroupAwards')
            ->willReturn(['drinker_of_day' => $this->winnerAward()]);
        $this->achievementRepository->method('hasAchievementOnDate')->willReturn(false);
        $this->em->method('getReference')->willReturn(new User());

        $this->awardNotifier->expects($this->never())->method('notifyWinner');

        $saved = $this->service->evaluateGroupAchievements($forDate, notify: false);

        $this->assertSame(1, $saved);
    }

    public function testSkipsAwardAlreadyGrantedOnDate(): void
    {
        $forDate = new \DateTimeImmutable('2026-08-25');

        $this->entryRepository->method('getGroupAwards')
            ->willReturn(['drinker_of_day' => $this->winnerAward()]);
        $this->achievementRepository->method('hasAchievementOnDate')->willReturn(true);
        $this->em->method('getReference')->willReturn(new User());

        $this->em->expects($this->never())->method('persist');

        $saved = $this->service->evaluateGroupAchievements($forDate);

        $this->assertSame(0, $saved);
    }
}
