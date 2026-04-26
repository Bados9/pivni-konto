<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserPasswordControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    public function testSetPasswordRequiresAdmin(): void
    {
        $regularUser = $this->createUser('user_' . uniqid() . '@example.com', ['ROLE_USER']);
        $target = $this->createUser('target_' . uniqid() . '@example.com');

        $this->client->loginUser($regularUser, 'admin');
        $this->client->request('GET', $this->setPasswordUrl($target));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSetPasswordFormRendersForAdmin(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('target_' . uniqid() . '@example.com');

        $this->client->loginUser($admin, 'admin');
        $crawler = $this->client->request('GET', $this->setPasswordUrl($target));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Změna hesla');
        $this->assertSelectorExists('input[name="new_password"]');
        $this->assertSelectorExists('input[name="new_password_repeat"]');
        $this->assertNotEmpty($crawler->filter('input[name="_token"]')->attr('value'));
    }

    public function testSetPasswordSuccess(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('target_' . uniqid() . '@example.com');
        $oldHash = $target->getPassword();
        $targetId = $target->getId()->toRfc4122();

        $this->client->loginUser($admin, 'admin');
        $this->client->request('GET', $this->setPasswordUrl($target));
        $token = $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->setPasswordUrl($target), [
            '_token' => $token,
            'new_password' => 'brand-new-pass',
            'new_password_repeat' => 'brand-new-pass',
        ]);

        $this->assertResponseRedirects();
        $updated = $this->reloadUser($targetId);
        $this->assertNotSame($oldHash, $updated->getPassword());
        $this->assertTrue($this->passwordHasher->isPasswordValid($updated, 'brand-new-pass'));
    }

    public function testSetPasswordRejectsTooShort(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('target_' . uniqid() . '@example.com');
        $oldHash = $target->getPassword();
        $targetId = $target->getId()->toRfc4122();

        $this->client->loginUser($admin, 'admin');
        $this->client->request('GET', $this->setPasswordUrl($target));
        $token = $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->setPasswordUrl($target), [
            '_token' => $token,
            'new_password' => 'abc',
            'new_password_repeat' => 'abc',
        ]);

        $this->assertResponseRedirects();
        $this->assertSame($oldHash, $this->reloadUser($targetId)->getPassword());
    }

    public function testSetPasswordRejectsMismatch(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('target_' . uniqid() . '@example.com');
        $oldHash = $target->getPassword();
        $targetId = $target->getId()->toRfc4122();

        $this->client->loginUser($admin, 'admin');
        $this->client->request('GET', $this->setPasswordUrl($target));
        $token = $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->setPasswordUrl($target), [
            '_token' => $token,
            'new_password' => 'long-enough-1',
            'new_password_repeat' => 'long-enough-2',
        ]);

        $this->assertResponseRedirects();
        $this->assertSame($oldHash, $this->reloadUser($targetId)->getPassword());
    }

    public function testSetPasswordRejectsBadCsrf(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('target_' . uniqid() . '@example.com');
        $oldHash = $target->getPassword();
        $targetId = $target->getId()->toRfc4122();

        $this->client->loginUser($admin, 'admin');
        $this->client->request('POST', $this->setPasswordUrl($target), [
            '_token' => 'invalid-token',
            'new_password' => 'brand-new-pass',
            'new_password_repeat' => 'brand-new-pass',
        ]);

        $this->assertResponseRedirects();
        $this->assertSame($oldHash, $this->reloadUser($targetId)->getPassword());
    }

    private function reloadUser(string $id): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->getRepository(User::class)->find($id);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function setPasswordUrl(User $user): string
    {
        return sprintf('/admin/user/%s/set-password', $user->getId()->toRfc4122());
    }

    private function createUser(string $email, array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test ' . $email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'initial-pass'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
