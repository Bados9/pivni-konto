<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class GroupAwardNotifier
{
    public function __construct(
        private AchievementService $achievementService,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * In-app notification only. The web push is deliberately deferred:
     * awards are evaluated at 5:01, app:push-group-awards sends the push at 10:00.
     */
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
    }

    /**
     * The weekly reconcile granted an award the user should have won. Bell only, no push.
     */
    public function notifyAwardGrantedRetroactively(User $user, string $achievementId, \DateTimeImmutable $forDate): void
    {
        $this->notifyAwardCorrection(
            $user,
            $achievementId,
            $forDate,
            'Po přepočtu ti byl dodatečně přiznán titul %s za %s.',
        );
    }

    /**
     * The weekly reconcile removed an award the user should not have won. Bell only, no push.
     */
    public function notifyAwardRevoked(User $user, string $achievementId, \DateTimeImmutable $forDate): void
    {
        $this->notifyAwardCorrection(
            $user,
            $achievementId,
            $forDate,
            'Titul %s za %s ti byl po přepočtu odebrán – zpětné záznamy změnily výsledek.',
        );
    }

    private function notifyAwardCorrection(
        User $user,
        string $achievementId,
        \DateTimeImmutable $forDate,
        string $messageTemplate,
    ): void {
        $definition = $this->achievementService->getDefinition($achievementId);
        if ($definition === null) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('achievement_update');
        $notification->setTitle(sprintf('%s %s', $definition['icon'], $definition['name']));
        $notification->setMessage(sprintf($messageTemplate, $definition['name'], $forDate->format('j. n. Y')));
        $notification->setData([
            'achievementId' => $achievementId,
            'date' => $forDate->format('Y-m-d'),
        ]);

        $this->em->persist($notification);
    }
}
