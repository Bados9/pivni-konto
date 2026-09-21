<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AnnouncementSender
{
    public function __construct(
        private EntityManagerInterface $em,
        private WebPushService $webPushService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Create an in-app announcement (bell + one-time popup) for the given users,
     * optionally also sent as a web push to their subscribed devices.
     *
     * @param User[] $users
     *
     * @return int number of notifications created
     */
    public function send(array $users, string $title, string $message, string $url = '/', bool $push = false): int
    {
        foreach ($users as $user) {
            $notification = new Notification();
            $notification->setUser($user);
            $notification->setType('announcement');
            $notification->setTitle($title);
            $notification->setMessage($message);
            $notification->setData(['url' => $url]);
            $this->em->persist($notification);
        }

        $this->em->flush();

        if ($push) {
            $this->sendPush($users, $title, $message, $url);
        }

        return count($users);
    }

    /**
     * @param User[] $users
     */
    private function sendPush(array $users, string $title, string $message, string $url): void
    {
        try {
            $this->webPushService->sendToUsers($users, [
                'title' => $title,
                'body' => $message,
                'url' => $url,
                'tag' => 'announcement-' . time(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Announcement web push failed', ['error' => $e->getMessage()]);
        }
    }
}
