<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GroupAwardNotifier
{
    public function __construct(
        private AchievementService $achievementService,
        private WebPushService $webPushService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function notifyWinner(User $user, Group $group, string $achievementId, \DateTimeImmutable $forDate): void
    {
        $definition = $this->achievementService->getDefinition($achievementId);
        if ($definition === null) {
            return;
        }

        $title = sprintf('%s %s', $definition['icon'], $definition['name']);
        $message = sprintf('Získal/a jsi ocenění %s ve skupině %s!', $definition['name'], $group->getName());

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('group_award');
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setData([
            'achievementId' => $achievementId,
            'groupId' => $group->getId()->toRfc4122(),
            'groupName' => $group->getName(),
            'date' => $forDate->format('Y-m-d'),
        ]);

        $this->em->persist($notification);

        $this->sendPush($user, $group, $achievementId, $title, $message);
    }

    private function sendPush(User $user, Group $group, string $achievementId, string $title, string $message): void
    {
        try {
            $this->webPushService->sendToUser($user, [
                'title' => $title,
                'body' => $message,
                'url' => '/profile',
                'tag' => sprintf('group-award-%s-%s', $achievementId, $group->getId()->toRfc4122()),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send group award push notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
