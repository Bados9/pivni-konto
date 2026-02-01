<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UserTest extends TestCase
{
    public function testNewUserHasUuidV7(): void
    {
        $user = new User();

        $this->assertInstanceOf(Uuid::class, $user->getId());
        $this->assertTrue(Uuid::isValid($user->getId()->toRfc4122()));
    }

    public function testNewUserHasCreatedAt(): void
    {
        $user = new User();

        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testSetAndGetName(): void
    {
        $user = new User();
        $user->setName('Jan Novák');

        $this->assertEquals('Jan Novák', $user->getName());
    }

    public function testSetAndGetEmail(): void
    {
        $user = new User();
        $user->setEmail('jan@example.com');

        $this->assertEquals('jan@example.com', $user->getEmail());
    }

    public function testUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('jan@example.com');

        $this->assertEquals('jan@example.com', $user->getUserIdentifier());
    }

    public function testDefaultRolesIncludesRoleUser(): void
    {
        $user = new User();

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testSetAndGetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles); // Always includes ROLE_USER
    }

    public function testSetAndGetPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        $this->assertEquals('hashed_password', $user->getPassword());
    }

    public function testSetAndGetAvatar(): void
    {
        $user = new User();

        $this->assertNull($user->getAvatar());

        $user->setAvatar('https://example.com/avatar.jpg');

        $this->assertEquals('https://example.com/avatar.jpg', $user->getAvatar());
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();

        $this->assertNull($user->eraseCredentials());
    }

    public function testGroupMembershipsInitializedAsEmpty(): void
    {
        $user = new User();

        $this->assertCount(0, $user->getGroupMemberships());
    }

    public function testBeerEntriesInitializedAsEmpty(): void
    {
        $user = new User();

        $this->assertCount(0, $user->getBeerEntries());
    }

    public function testFluentInterface(): void
    {
        $user = new User();

        $result = $user
            ->setName('Test')
            ->setEmail('test@example.com')
            ->setPassword('password')
            ->setRoles(['ROLE_USER'])
            ->setAvatar('avatar.jpg');

        $this->assertSame($user, $result);
    }
}
