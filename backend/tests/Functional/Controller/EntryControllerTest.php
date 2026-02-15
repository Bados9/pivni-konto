<?php

namespace App\Tests\Functional\Controller;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Tests\Functional\Api\ApiTestCase;

class EntryControllerTest extends ApiTestCase
{
    public function testQuickAddRequiresAuth(): void
    {
        $this->apiRequest('POST', '/api/entries/quick-add', [
            'volumeMl' => 500,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testQuickAddCreatesEntry(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('POST', '/api/entries/quick-add', [
            'volumeMl' => 500,
            'quantity' => 1,
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(500, $data['volumeMl']);
        $this->assertEquals(1, $data['quantity']);
        $this->assertArrayHasKey('consumedAt', $data);
    }

    public function testQuickAddWithCustomBeerName(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('POST', '/api/entries/quick-add', [
            'volumeMl' => 330,
            'quantity' => 2,
            'customBeerName' => 'Pilsner Urquell',
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertEquals('Pilsner Urquell', $data['beerName']);
        $this->assertEquals(330, $data['volumeMl']);
        $this->assertEquals(2, $data['quantity']);
    }

    public function testQuickAddWithCustomConsumedAt(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $customTime = '2026-01-30T20:00:00+00:00';

        $this->apiRequest('POST', '/api/entries/quick-add', [
            'volumeMl' => 500,
            'consumedAt' => $customTime,
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertStringContainsString('2026-01-30', $data['consumedAt']);
    }

    public function testQuickAddTriggersAchievementCheck(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        // First beer should unlock "first_beer" achievement
        $this->apiRequest('POST', '/api/entries/quick-add', [
            'volumeMl' => 500,
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('newAchievements', $data);
        $this->assertIsArray($data['newAchievements']);

        // Check if first_beer achievement was unlocked
        $achievementIds = array_column($data['newAchievements'], 'id');
        $this->assertContains('first_beer', $achievementIds);
    }

    public function testQuickAddWithGroup(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        // Create a group and add user as member
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

        $this->apiRequest('POST', '/api/entries/quick-add', [
            'volumeMl' => 500,
            'groupId' => $group->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('group', $data);
        $this->assertEquals('Test Group', $data['group']['name']);
    }

    public function testDeleteEntrySuccess(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        // Create an entry
        $entry = new BeerEntry();
        $entry->setUser($user);
        $entry->setVolumeMl(500);
        $entry->setQuantity(1);
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $entryId = $entry->getId()->toRfc4122();

        $this->apiRequest('DELETE', '/api/entries/' . $entryId);

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertStringContainsString('smazán', $data['message']);
    }

    public function testDeleteEntryRequiresAuth(): void
    {
        $this->apiRequest('DELETE', '/api/entries/00000000-0000-0000-0000-000000000001');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteEntryNotFound(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('DELETE', '/api/entries/00000000-0000-0000-0000-000000000001');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeleteEntryForbiddenForOtherUser(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');

        // Create entry owned by first user
        $entry = new BeerEntry();
        $entry->setUser($owner);
        $entry->setVolumeMl(500);
        $entry->setQuantity(1);
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $entryId = $entry->getId()->toRfc4122();

        // Try to delete as different user
        $this->loginAs($other);
        $this->apiRequest('DELETE', '/api/entries/' . $entryId);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteEntryInvalidUuid(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('DELETE', '/api/entries/invalid-uuid');

        $this->assertResponseStatusCodeSame(400);
    }
}
