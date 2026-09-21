<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AnnouncementControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $email, array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test User');
        $user->setPassword('irrelevant_hash');
        $user->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function notificationRepository(): NotificationRepository
    {
        return static::getContainer()->get(NotificationRepository::class);
    }

    public function testAnnounceRequiresAdmin(): void
    {
        $regularUser = $this->createUser('user_' . uniqid() . '@example.com');

        $this->client->loginUser($regularUser, 'admin');
        $this->client->request('GET', '/admin/announce');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testFormRendersForAdmin(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);

        $this->client->loginUser($admin, 'admin');
        $crawler = $this->client->request('GET', '/admin/announce');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="title"]');
        $this->assertSelectorExists('textarea[name="message"]');
        $this->assertSelectorExists('select[name="recipients[]"]');
        $this->assertNotEmpty($crawler->filter('input[name="_token"]')->attr('value'));
    }

    public function testPostCreatesAnnouncementForAllUsers(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $member = $this->createUser('member_' . uniqid() . '@example.com');

        $this->client->loginUser($admin, 'admin');
        $crawler = $this->client->request('GET', '/admin/announce');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/announce', [
            '_token' => $token,
            'title' => '🎉 Novinka',
            'message' => 'Testovací oznámení',
            'url' => '/profile',
        ]);

        $this->assertResponseRedirects();

        $this->assertSame(1, $this->notificationRepository()->countUnread($member));
        $this->assertSame(1, $this->notificationRepository()->countUnread($admin));

        $notification = $this->notificationRepository()->findLatestByUser($member, 1)[0];
        $this->assertSame('announcement', $notification->getType());
        $this->assertSame('🎉 Novinka', $notification->getTitle());
        $this->assertSame('/profile', $notification->getData()['url']);
    }

    public function testPostWithSelectedRecipientsTargetsOnlyThem(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('target_' . uniqid() . '@example.com');
        $bystander = $this->createUser('bystander_' . uniqid() . '@example.com');

        $this->client->loginUser($admin, 'admin');
        $crawler = $this->client->request('GET', '/admin/announce');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/announce', [
            '_token' => $token,
            'title' => 'Cílené',
            'message' => 'Jen pro vybrané',
            'recipients' => [$target->getId()->toRfc4122()],
        ]);

        $this->assertResponseRedirects();
        $this->assertSame(1, $this->notificationRepository()->countUnread($target));
        $this->assertSame(0, $this->notificationRepository()->countUnread($bystander));
    }

    public function testPostRejectsInvalidCsrf(): void
    {
        $admin = $this->createUser('admin_' . uniqid() . '@example.com', ['ROLE_ADMIN']);
        $member = $this->createUser('member_' . uniqid() . '@example.com');

        $this->client->loginUser($admin, 'admin');
        $this->client->request('POST', '/admin/announce', [
            '_token' => 'invalid',
            'title' => 'X',
            'message' => 'Y',
        ]);

        $this->assertResponseRedirects();
        $this->assertSame(0, $this->notificationRepository()->countUnread($member));
    }
}
