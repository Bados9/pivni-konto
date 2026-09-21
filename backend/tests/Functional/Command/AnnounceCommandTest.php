<?php

namespace App\Tests\Functional\Command;

use App\Repository\NotificationRepository;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AnnounceCommandTest extends ApiTestCase
{
    public function testCreatesAnnouncementForAllUsers(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:announce'));
        $tester->execute([
            'title' => '🎉 Novinka',
            'message' => 'Přibyly push notifikace!',
            '--url' => '/profile',
        ]);

        $tester->assertCommandIsSuccessful();

        /** @var NotificationRepository $repository */
        $repository = static::getContainer()->get(NotificationRepository::class);

        foreach ([$user1, $user2] as $user) {
            $this->assertSame(1, $repository->countUnread($user));
            $notification = $repository->findLatestByUser($user, 1)[0];
            $this->assertSame('announcement', $notification->getType());
            $this->assertSame('🎉 Novinka', $notification->getTitle());
            $this->assertSame('/profile', $notification->getData()['url']);
        }
    }

    public function testTargetsSingleUserByEmail(): void
    {
        $target = $this->createUser();
        $bystander = $this->createUser();

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:announce'));
        $tester->execute([
            'title' => 'Jen pro tebe',
            'message' => 'Cílená zpráva',
            '--user' => [$target->getEmail()],
        ]);

        $tester->assertCommandIsSuccessful();

        /** @var NotificationRepository $repository */
        $repository = static::getContainer()->get(NotificationRepository::class);
        $this->assertSame(1, $repository->countUnread($target));
        $this->assertSame(0, $repository->countUnread($bystander));
    }

    public function testFailsOnUnknownEmail(): void
    {
        $this->createUser();

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:announce'));
        $status = $tester->execute([
            'title' => 'X',
            'message' => 'Y',
            '--user' => ['neexistuje@nikde.cz'],
        ]);

        $this->assertSame(1, $status);
    }
}
