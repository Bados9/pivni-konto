<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\UserAchievementRepository;
use App\Service\AchievementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AchievementServiceTest extends TestCase
{
    private AchievementService $service;
    private MockObject&BeerEntryRepository $entryRepository;
    private MockObject&GroupMemberRepository $memberRepository;
    private MockObject&UserAchievementRepository $achievementRepository;
    private MockObject&EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entryRepository = $this->createMock(BeerEntryRepository::class);
        $this->memberRepository = $this->createMock(GroupMemberRepository::class);
        $this->achievementRepository = $this->createMock(UserAchievementRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->achievementRepository->method('countByUserAndAchievement')->willReturn(0);
        $this->achievementRepository->method('getMaxConsecutiveDays')->willReturn(0);

        $this->service = new AchievementService(
            $this->entryRepository,
            $this->memberRepository,
            $this->achievementRepository,
            $this->entityManager,
        );
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        $user->setPassword('hashed_password');
        return $user;
    }

    private function getBaseStats(): array
    {
        return [
            'total_beers' => 0,
            'total_volume_ml' => 0,
            'unique_beers' => 0,
            'unique_breweries' => 0,
            'early_bird_days' => 0,
            'night_owl_days' => 0,
            'new_year_days' => 0,
            'tasting_days' => 0,
            'max_daily_variety' => 0,
            'weekend_beers' => 0,
            'small_beers' => 0,
            'max_daily' => 0,
            'max_loyal' => 0,
            'consecutive_days' => 0,
            'days_with_10_beers' => 0,
            'days_with_15_beers' => 0,
        ];
    }

    public function testFirstBeerAchievementUnlocked(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->checkAndUnlockAchievements($user);

        $this->assertCount(1, $result);
        $this->assertEquals('first_beer', $result[0]['id']);
        $this->assertEquals('První doušek', $result[0]['name']);
    }

    public function testMultipleMilestoneAchievementsUnlocked(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 100;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('first_beer', $ids);
        $this->assertContains('beer_50', $ids);
        $this->assertContains('beer_100', $ids);
        $this->assertNotContains('beer_500', $ids);
        $this->assertNotContains('beer_1000', $ids);
    }

    public function testVolumeAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['total_volume_ml'] = 50000;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('volume_10l', $ids);
        $this->assertContains('volume_50l', $ids);
        $this->assertNotContains('volume_100l', $ids);
    }

    public function testVarietyAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['unique_beers'] = 15;
        $stats['unique_breweries'] = 5;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('variety_5', $ids);
        $this->assertContains('variety_15', $ids);
        $this->assertNotContains('variety_30', $ids);
        $this->assertContains('breweries_5', $ids);
    }

    public function testTimeBasedAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['early_bird_days'] = 1;
        $stats['weekend_beers'] = 30;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('early_bird', $ids);
        $this->assertContains('weekend_warrior', $ids);
    }

    public function testEarlyBirdIsRepeatablePerDay(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['early_bird_days'] = 5;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([
            'early_bird' => 2,
            'first_beer' => 1,
        ]);

        // 3 new rows (5-2) persisted
        $this->entityManager->expects($this->exactly(3))->method('persist');

        $result = $this->service->checkAndUnlockAchievements($user);

        $earlyBird = array_values(array_filter($result, fn($a) => $a['id'] === 'early_bird'));
        $this->assertCount(1, $earlyBird);
        $this->assertEquals(5, $earlyBird[0]['timesUnlocked']);
    }

    public function testNightOwlAndTasterAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['night_owl_days'] = 2;
        $stats['tasting_days'] = 1;
        $stats['max_daily_variety'] = 5;
        $stats['new_year_days'] = 1;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $nightOwl = array_values(array_filter($result, fn($a) => $a['id'] === 'night_owl'));
        $this->assertCount(1, $nightOwl);
        $this->assertEquals(2, $nightOwl[0]['timesUnlocked']);

        $ids = array_column($result, 'id');
        $this->assertContains('taster_day', $ids);
        $this->assertContains('new_year', $ids);
    }

    public function testStreakAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['consecutive_days'] = 14;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('weekly_streak', $ids);
        $this->assertContains('streak_14', $ids);
        $this->assertNotContains('streak_30', $ids);
    }

    public function testDailyRepeatableAchievement(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['days_with_10_beers'] = 3;
        $stats['max_daily'] = 10;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);
        $this->entityManager->expects($this->exactly(4))->method('persist');

        $result = $this->service->checkAndUnlockAchievements($user);

        $daily10 = array_values(array_filter($result, fn($a) => $a['id'] === 'daily_10'));
        $this->assertCount(1, $daily10);
        $this->assertEquals(3, $daily10[0]['timesUnlocked']);
    }

    public function testRepeatableAchievementIncrement(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['days_with_10_beers'] = 5;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([
            'daily_10' => 3,
            'first_beer' => 1,
        ]);

        // 2 new rows (5-3) persisted
        $this->entityManager->expects($this->exactly(2))->method('persist');

        $result = $this->service->checkAndUnlockAchievements($user);

        $daily10 = array_values(array_filter($result, fn($a) => $a['id'] === 'daily_10'));
        $this->assertCount(1, $daily10);
        $this->assertEquals(5, $daily10[0]['timesUnlocked']);
    }

    public function testNoNewAchievementsWhenAlreadyUnlocked(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([
            'first_beer' => 1,
        ]);

        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->service->checkAndUnlockAchievements($user);

        $this->assertEmpty($result);
    }

    public function testSpecialAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['small_beers'] = 10;
        $stats['max_loyal'] = 100;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('small_but_mighty', $ids);
        $this->assertContains('loyal_fan', $ids);
    }

    public function testWeeklyStreakAchievement(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['consecutive_days'] = 7;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('weekly_streak', $ids);
    }

    public function testGetUserAchievementsReturnsAllWithProgress(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 30;
        $stats['total_volume_ml'] = 5000;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([
            'first_beer' => 1,
        ]);

        $result = $this->service->getUserAchievements($user);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $firstBeer = array_values(array_filter($result, fn($a) => $a['id'] === 'first_beer'))[0];
        $this->assertTrue($firstBeer['unlocked']);
        $this->assertEquals(1, $firstBeer['progress']);
        $this->assertEquals(1, $firstBeer['target']);
        $this->assertEquals(100, $firstBeer['percentage']);

        $beer50 = array_values(array_filter($result, fn($a) => $a['id'] === 'beer_50'))[0];
        $this->assertFalse($beer50['unlocked']);
        $this->assertEquals(30, $beer50['progress']);
        $this->assertEquals(50, $beer50['target']);
        $this->assertEquals(60, $beer50['percentage']);
    }

    public function testGetAchievementsSummary(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([
            'first_beer' => 1,
        ]);

        $result = $this->service->getAchievementsSummary($user);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('unlocked', $result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('recent', $result);
        $this->assertEquals(35, $result['total']);
        $this->assertEquals(1, $result['unlocked']);
    }

    public function testDaily15RepeatableAchievement(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['days_with_10_beers'] = 2;
        $stats['days_with_15_beers'] = 2;
        $stats['max_daily'] = 15;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedWithCounts')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $daily15 = array_values(array_filter($result, fn($a) => $a['id'] === 'daily_15'));
        $this->assertCount(1, $daily15);
        $this->assertEquals(2, $daily15[0]['timesUnlocked']);
    }
}
