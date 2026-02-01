<?php

namespace App\Tests\Functional\Controller;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Tests\Functional\Api\ApiTestCase;

class StatsControllerTest extends ApiTestCase
{
    public function testMyStatsRequiresAuth(): void
    {
        $this->apiRequest('GET', '/api/stats/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMyStatsReturnsCorrectStructure(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        $this->assertArrayHasKey('today', $data);
        $this->assertArrayHasKey('thisWeek', $data);
        $this->assertArrayHasKey('thisMonth', $data);
        $this->assertArrayHasKey('thisYear', $data);
        $this->assertArrayHasKey('totalBeers', $data);
        $this->assertArrayHasKey('totalVolume', $data);
        $this->assertArrayHasKey('todayEntries', $data);
        $this->assertArrayHasKey('dailyCounts', $data);
        $this->assertArrayHasKey('topBeers', $data);
        $this->assertArrayHasKey('topBreweries', $data);
        $this->assertArrayHasKey('currentStreak', $data);
        $this->assertArrayHasKey('averagePerDay', $data);
    }

    public function testMyStatsCountsEntriesCorrectly(): void
    {
        $user = $this->createUser();

        // Add a couple of entries for today
        for ($i = 0; $i < 3; $i++) {
            $entry = new BeerEntry();
            $entry->setUser($user);
            $entry->setVolumeMl(500);
            $entry->setQuantity(1);
            $this->entityManager->persist($entry);
        }
        $this->entityManager->flush();

        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        $this->assertEquals(3, $data['today']);
        $this->assertEquals(3, $data['totalBeers']);
        $this->assertEquals(1500, $data['totalVolume']); // 3 * 500ml
    }

    public function testMyStatsTodayEntriesReturned(): void
    {
        $user = $this->createUser();

        $entry = new BeerEntry();
        $entry->setUser($user);
        $entry->setVolumeMl(500);
        $entry->setQuantity(1);
        $entry->setCustomBeerName('Test Beer');
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();

        $this->assertCount(1, $data['todayEntries']);
        $this->assertEquals('Test Beer', $data['todayEntries'][0]['beerName']);
        $this->assertEquals(500, $data['todayEntries'][0]['volumeMl']);
    }

    public function testUserStatsRequiresAuth(): void
    {
        $this->apiRequest('GET', '/api/stats/user/00000000-0000-0000-0000-000000000001');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUserStatsRequiresSharedGroup(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $this->loginAs($user1);

        $this->apiRequest('GET', '/api/stats/user/' . $user2->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserStatsAllowsSharedGroupMembers(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        // Create a shared group
        $group = new Group();
        $group->setName('Shared Group');
        $group->setCreatedBy($user1);
        $this->entityManager->persist($group);

        $member1 = new GroupMember();
        $member1->setUser($user1);
        $member1->setGroup($group);
        $member1->setRole('admin');
        $this->entityManager->persist($member1);

        $member2 = new GroupMember();
        $member2->setUser($user2);
        $member2->setGroup($group);
        $member2->setRole('member');
        $this->entityManager->persist($member2);

        $this->entityManager->flush();

        $this->loginAs($user1);

        $this->apiRequest('GET', '/api/stats/user/' . $user2->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertEquals($user2->getId()->toRfc4122(), $data['userId']);
        $this->assertEquals($user2->getName(), $data['userName']);
    }

    public function testUserStatsUserNotFound(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/user/00000000-0000-0000-0000-000000000001');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUserStatsInvalidUuid(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/user/invalid-uuid');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testLeaderboardRequiresAuth(): void
    {
        $this->apiRequest('GET', '/api/stats/leaderboard/00000000-0000-0000-0000-000000000001');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLeaderboardRequiresMembership(): void
    {
        $owner = $this->createUser('owner@example.com');
        $nonMember = $this->createUser('nonmember@example.com');

        $group = new Group();
        $group->setName('Private Group');
        $group->setCreatedBy($owner);
        $this->entityManager->persist($group);

        $member = new GroupMember();
        $member->setUser($owner);
        $member->setGroup($group);
        $member->setRole('admin');
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        $this->loginAs($nonMember);

        $this->apiRequest('GET', '/api/stats/leaderboard/' . $group->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testLeaderboardSuccess(): void
    {
        $user = $this->createUser();

        $group = new Group();
        $group->setName('Leaderboard Group');
        $group->setCreatedBy($user);
        $this->entityManager->persist($group);

        $member = new GroupMember();
        $member->setUser($user);
        $member->setGroup($group);
        $member->setRole('admin');
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/leaderboard/' . $group->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('group', $data);
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('leaderboard', $data);
        $this->assertEquals('Leaderboard Group', $data['group']['name']);
        $this->assertEquals('week', $data['period']); // default period
    }

    public function testLeaderboardPeriodParameter(): void
    {
        $user = $this->createUser();

        $group = new Group();
        $group->setName('Test Group');
        $group->setCreatedBy($user);
        $this->entityManager->persist($group);

        $member = new GroupMember();
        $member->setUser($user);
        $member->setGroup($group);
        $member->setRole('admin');
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        $this->loginAs($user);

        // Test month period
        $this->apiRequest('GET', '/api/stats/leaderboard/' . $group->getId()->toRfc4122() . '?period=month');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();
        $this->assertEquals('month', $data['period']);

        // Test year period
        $this->apiRequest('GET', '/api/stats/leaderboard/' . $group->getId()->toRfc4122() . '?period=year');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();
        $this->assertEquals('year', $data['period']);
    }

    public function testLeaderboardGroupNotFound(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/leaderboard/00000000-0000-0000-0000-000000000001');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testLeaderboardInvalidUuid(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/stats/leaderboard/invalid-uuid');

        $this->assertResponseStatusCodeSame(400);
    }
}
