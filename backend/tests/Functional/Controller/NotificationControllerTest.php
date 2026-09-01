<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Tests\Functional\Api\ApiTestCase;

class NotificationControllerTest extends ApiTestCase
{
    private function createNotification(User $user, string $title, ?\DateTimeImmutable $readAt = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('group_award');
        $notification->setTitle($title);
        $notification->setMessage('Test message');
        $notification->setData(['achievementId' => 'drinker_of_day']);
        $notification->setReadAt($readAt);
        $this->entityManager->persist($notification);

        return $notification;
    }

    public function testListRequiresAuthentication(): void
    {
        $this->apiRequest('GET', '/api/notifications');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListReturnsLatestNotificationsWithUnreadCount(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'Unread one');
        $this->createNotification($user, 'Already read', new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->loginAs($user);
        $this->apiRequest('GET', '/api/notifications');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();

        $this->assertCount(2, $data['notifications']);
        $this->assertSame(1, $data['unreadCount']);

        $first = $data['notifications'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('message', $first);
        $this->assertArrayHasKey('data', $first);
        $this->assertArrayHasKey('createdAt', $first);
        $this->assertArrayHasKey('read', $first);
    }

    public function testListDoesNotReturnOtherUsersNotifications(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $this->createNotification($otherUser, 'Not yours');
        $this->entityManager->flush();

        $this->loginAs($user);
        $this->apiRequest('GET', '/api/notifications');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();

        $this->assertCount(0, $data['notifications']);
        $this->assertSame(0, $data['unreadCount']);
    }

    public function testUnreadCount(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'Unread one');
        $this->createNotification($user, 'Unread two');
        $this->createNotification($user, 'Already read', new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->loginAs($user);
        $this->apiRequest('GET', '/api/notifications/unread-count');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(2, $this->getResponseData()['count']);
    }

    public function testReadAllMarksEverythingRead(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'Unread one');
        $this->createNotification($user, 'Unread two');
        $this->entityManager->flush();

        $this->loginAs($user);
        $this->apiRequest('POST', '/api/notifications/read-all');

        $this->assertResponseStatusCodeSame(200);

        $this->apiRequest('GET', '/api/notifications/unread-count');
        $this->assertSame(0, $this->getResponseData()['count']);
    }
}
