<?php

namespace App\Tests\Functional\Controller;

use App\Entity\BeerEntry;
use App\Entity\UserAchievement;
use App\Tests\Functional\Api\ApiTestCase;

class AchievementControllerTest extends ApiTestCase
{
    public function testGetMyAchievementsRequiresAuth(): void
    {
        $this->apiRequest('GET', '/api/achievements/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetMyAchievementsReturnsAllAchievements(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/achievements/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('achievements', $data);
        $this->assertArrayHasKey('summary', $data);

        // Check that all 21 achievements are returned
        $this->assertCount(21, $data['achievements']);

        // Check achievement structure
        $firstAchievement = $data['achievements'][0];
        $this->assertArrayHasKey('id', $firstAchievement);
        $this->assertArrayHasKey('name', $firstAchievement);
        $this->assertArrayHasKey('description', $firstAchievement);
        $this->assertArrayHasKey('icon', $firstAchievement);
        $this->assertArrayHasKey('category', $firstAchievement);
        $this->assertArrayHasKey('unlocked', $firstAchievement);
        $this->assertArrayHasKey('progress', $firstAchievement);
        $this->assertArrayHasKey('target', $firstAchievement);
        $this->assertArrayHasKey('percentage', $firstAchievement);
    }

    public function testGetMyAchievementsShowsUnlockedCorrectly(): void
    {
        $user = $this->createUser();

        // Add an entry to unlock first_beer
        $entry = new BeerEntry();
        $entry->setUser($user);
        $entry->setVolumeMl(500);
        $entry->setQuantity(1);
        $this->entityManager->persist($entry);

        // Create the achievement record
        $achievement = new UserAchievement();
        $achievement->setUser($user);
        $achievement->setAchievementId('first_beer');
        $this->entityManager->persist($achievement);
        $this->entityManager->flush();

        $this->loginAs($user);

        $this->apiRequest('GET', '/api/achievements/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        // Find first_beer achievement
        $firstBeer = array_values(array_filter(
            $data['achievements'],
            fn($a) => $a['id'] === 'first_beer'
        ))[0];

        $this->assertTrue($firstBeer['unlocked']);
        $this->assertEquals(100, $firstBeer['percentage']);
    }

    public function testGetMyAchievementsGroupedByCategory(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/achievements/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        $categories = array_unique(array_column($data['achievements'], 'category'));

        $this->assertContains('milestones', $categories);
        $this->assertContains('volume', $categories);
        $this->assertContains('variety', $categories);
        $this->assertContains('time', $categories);
        $this->assertContains('performance', $categories);
        $this->assertContains('special', $categories);
    }

    public function testGetSummaryRequiresAuth(): void
    {
        $this->apiRequest('GET', '/api/achievements/summary');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetSummaryReturnsCorrectStructure(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/achievements/summary');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('unlocked', $data);
        $this->assertArrayHasKey('percentage', $data);
        $this->assertArrayHasKey('recent', $data);

        $this->assertEquals(21, $data['total']);
        $this->assertIsArray($data['recent']);
    }

    public function testGetSummaryShowsUnlockedCount(): void
    {
        $user = $this->createUser();

        // Unlock 2 achievements
        $achievement1 = new UserAchievement();
        $achievement1->setUser($user);
        $achievement1->setAchievementId('first_beer');
        $this->entityManager->persist($achievement1);

        $achievement2 = new UserAchievement();
        $achievement2->setUser($user);
        $achievement2->setAchievementId('early_bird');
        $this->entityManager->persist($achievement2);

        $this->entityManager->flush();

        $this->loginAs($user);

        $this->apiRequest('GET', '/api/achievements/summary');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        $this->assertEquals(2, $data['unlocked']);
        $this->assertEquals(round((2 / 21) * 100), $data['percentage']);
    }

    public function testMarathonAchievementShowsUnlocked(): void
    {
        $user = $this->createUser();

        $achievement = new UserAchievement();
        $achievement->setUser($user);
        $achievement->setAchievementId('marathon');
        $this->entityManager->persist($achievement);
        $this->entityManager->flush();

        $this->loginAs($user);

        $this->apiRequest('GET', '/api/achievements/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        $marathon = array_values(array_filter(
            $data['achievements'],
            fn($a) => $a['id'] === 'marathon'
        ))[0];

        $this->assertTrue($marathon['unlocked']);
    }
}
