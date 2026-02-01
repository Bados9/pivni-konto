<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
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
            'early_bird' => false,
            'night_owl' => false,
            'weekend_beers' => 0,
            'large_beers' => 0,
            'small_beers' => 0,
            'max_daily' => 0,
            'max_loyal' => 0,
            'consecutive_days' => 0,
        ];
    }

    public function testFirstBeerAchievementUnlocked(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);
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
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

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
        $stats['total_volume_ml'] = 50000; // 50 liters

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

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
        $stats['unique_beers'] = 10;
        $stats['unique_breweries'] = 5;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('variety_5', $ids);
        $this->assertContains('variety_10', $ids);
        $this->assertNotContains('variety_25', $ids);
        $this->assertContains('breweries_5', $ids);
    }

    public function testTimeBasedAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['early_bird'] = true;
        $stats['night_owl'] = true;
        $stats['weekend_beers'] = 20;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('early_bird', $ids);
        $this->assertContains('night_owl', $ids);
        $this->assertContains('weekend_warrior', $ids);
    }

    public function testMarathonAchievement(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['max_daily'] = 5;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('marathon', $ids);
    }

    public function testNoNewAchievementsWhenAlreadyUnlocked(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn(['first_beer']);

        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->service->checkAndUnlockAchievements($user);

        $this->assertEmpty($result);
    }

    public function testSpecialAchievements(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['large_beers'] = 10;
        $stats['small_beers'] = 10;
        $stats['max_loyal'] = 10;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('size_matters', $ids);
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
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

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
        $this->achievementRepository->method('getUnlockedIds')->willReturn(['first_beer']);

        $result = $this->service->getUserAchievements($user);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Check first_beer is unlocked
        $firstBeer = array_values(array_filter($result, fn($a) => $a['id'] === 'first_beer'))[0];
        $this->assertTrue($firstBeer['unlocked']);
        $this->assertEquals(1, $firstBeer['progress']);
        $this->assertEquals(1, $firstBeer['target']);
        $this->assertEquals(100, $firstBeer['percentage']);

        // Check beer_50 progress (not yet unlocked)
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
        $this->achievementRepository->method('getUnlockedIds')->willReturn(['first_beer']);

        $result = $this->service->getAchievementsSummary($user);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('unlocked', $result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('recent', $result);
        $this->assertEquals(1, $result['unlocked']);
    }

    public function testUltraMarathonAchievement(): void
    {
        $user = $this->createUser();
        $stats = $this->getBaseStats();
        $stats['total_beers'] = 1;
        $stats['max_daily'] = 10;

        $this->entryRepository->method('getAchievementStatsByUser')->willReturn($stats);
        $this->memberRepository->method('findBy')->willReturn([]);
        $this->achievementRepository->method('getUnlockedIds')->willReturn([]);

        $result = $this->service->checkAndUnlockAchievements($user);

        $ids = array_column($result, 'id');
        $this->assertContains('ultra_marathon', $ids);
        $this->assertContains('marathon', $ids);
    }
}
