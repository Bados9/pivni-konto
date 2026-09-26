<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\WebPushService;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PushGroupAwardsCommandTest extends ApiTestCase
{
    private function createNotification(
        User $user,
        string $type = 'group_award',
        ?\DateTimeImmutable $createdAt = null,
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle('👑 Pijan dne');
        $notification->setMessage('Získal/a jsi ocenění Pijan dne ve skupině Parta!');
        $notification->setData(['achievementId' => 'drinker_of_day', 'groupId' => 'g1']);

        if ($createdAt !== null) {
            $notification->setCreatedAt($createdAt);
        }

        $this->entityManager->persist($notification);

        return $notification;
    }

    private function runCommand(): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:push-group-awards'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }

    public function testPushesFreshAwardAndMarksIt(): void
    {
        $user = $this->createUser();
        $fresh = $this->createNotification($user);
        $this->entityManager->flush();

        $webPush = $this->createMock(WebPushService::class);
        $webPush->expects($this->once())
            ->method('sendToUser')
            ->with(
                $user,
                $this->callback(fn (array $payload) => $payload['title'] === '👑 Pijan dne'
                    && str_contains($payload['tag'], 'drinker_of_day')),
                'group_award',
            );
        static::getContainer()->set(WebPushService::class, $webPush);

        $this->runCommand();

        $this->assertNotNull($fresh->getPushedAt());
    }

    public function testSecondRunPushesNothing(): void
    {
        $user = $this->createUser();
        $this->createNotification($user);
        $this->entityManager->flush();

        $webPush = $this->createMock(WebPushService::class);
        $webPush->expects($this->once())->method('sendToUser');
        static::getContainer()->set(WebPushService::class, $webPush);

        $this->runCommand();
        $this->runCommand();
    }

    public function testIgnoresOldAndForeignTypeNotifications(): void
    {
        $user = $this->createUser();
        $old = $this->createNotification($user, createdAt: new \DateTimeImmutable('-3 days'));
        $announcement = $this->createNotification($user, type: 'announcement');
        $this->entityManager->flush();

        $webPush = $this->createMock(WebPushService::class);
        $webPush->expects($this->never())->method('sendToUser');
        static::getContainer()->set(WebPushService::class, $webPush);

        $this->runCommand();

        $this->assertNull($old->getPushedAt());
        $this->assertNull($announcement->getPushedAt());
    }
}
