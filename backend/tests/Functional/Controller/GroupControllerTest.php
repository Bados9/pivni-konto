<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Tests\Functional\Api\ApiTestCase;

class GroupControllerTest extends ApiTestCase
{
    public function testMyGroupsRequiresAuth(): void
    {
        $this->apiRequest('GET', '/api/groups/my');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMyGroupsReturnsEmptyForNewUser(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/groups/my');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testMyGroupsReturnsUserGroups(): void
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

        $this->apiRequest('GET', '/api/groups/my');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertCount(1, $data);
        $this->assertEquals('Test Group', $data[0]['name']);
        $this->assertArrayHasKey('inviteCode', $data[0]);
        $this->assertEquals(1, $data[0]['memberCount']);
    }

    public function testCreateGroupRequiresAuth(): void
    {
        $this->apiRequest('POST', '/api/groups/create', ['name' => 'New Group']);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateGroupSuccess(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('POST', '/api/groups/create', [
            'name' => 'My New Group',
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertEquals('My New Group', $data['name']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('inviteCode', $data);
        $this->assertNotEmpty($data['inviteCode']);
    }

    public function testCreateGroupRequiresName(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('POST', '/api/groups/create', []);

        $this->assertResponseStatusCodeSame(400);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    public function testJoinGroupRequiresAuth(): void
    {
        $this->apiRequest('POST', '/api/groups/join', ['code' => 'TESTCODE']);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testJoinGroupSuccess(): void
    {
        $owner = $this->createUser('owner@example.com');
        $joiner = $this->createUser('joiner@example.com');

        $group = new Group();
        $group->setName('Joinable Group');
        $group->setCreatedBy($owner);
        $this->entityManager->persist($group);

        $ownerMember = new GroupMember();
        $ownerMember->setUser($owner);
        $ownerMember->setGroup($group);
        $ownerMember->setRole('admin');
        $this->entityManager->persist($ownerMember);
        $this->entityManager->flush();

        $inviteCode = $group->getInviteCode();

        $this->loginAs($joiner);

        $this->apiRequest('POST', '/api/groups/join', [
            'code' => $inviteCode,
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertStringContainsString('připojili', $data['message']);
        $this->assertEquals('Joinable Group', $data['group']['name']);
    }

    public function testJoinGroupRequiresCode(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('POST', '/api/groups/join', []);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testJoinGroupInvalidCode(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->apiRequest('POST', '/api/groups/join', [
            'code' => 'INVALIDCODE123',
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testJoinGroupAlreadyMember(): void
    {
        $user = $this->createUser();

        $group = new Group();
        $group->setName('Existing Group');
        $group->setCreatedBy($user);
        $this->entityManager->persist($group);

        $member = new GroupMember();
        $member->setUser($user);
        $member->setGroup($group);
        $member->setRole('admin');
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        $inviteCode = $group->getInviteCode();

        $this->loginAs($user);

        $this->apiRequest('POST', '/api/groups/join', [
            'code' => $inviteCode,
        ]);

        $this->assertResponseStatusCodeSame(409);

        $data = $this->getResponseData();
        $this->assertStringContainsString('členem', $data['error']);
    }

    public function testMultipleGroupsForUser(): void
    {
        $user = $this->createUser();

        // Create 3 groups
        for ($i = 1; $i <= 3; $i++) {
            $group = new Group();
            $group->setName("Group $i");
            $group->setCreatedBy($user);
            $this->entityManager->persist($group);

            $member = new GroupMember();
            $member->setUser($user);
            $member->setGroup($group);
            $member->setRole('admin');
            $this->entityManager->persist($member);
        }
        $this->entityManager->flush();

        $this->loginAs($user);

        $this->apiRequest('GET', '/api/groups/my');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertCount(3, $data);
    }
}
