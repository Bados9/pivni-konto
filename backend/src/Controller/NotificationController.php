<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
    ) {
    }

    #[Route('', name: 'notifications_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $limit = min(50, max(1, $request->query->getInt('limit', 20)));
        $notifications = $this->notificationRepository->findLatestByUser($user, $limit);

        return $this->json([
            'notifications' => array_map(
                fn (Notification $notification) => $this->serialize($notification),
                $notifications,
            ),
            'unreadCount' => $this->notificationRepository->countUnread($user),
        ]);
    }

    #[Route('/unread-count', name: 'notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['count' => $this->notificationRepository->countUnread($user)]);
    }

    #[Route('/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->notificationRepository->markAllRead($user);

        return $this->json(['success' => true]);
    }

    private function serialize(Notification $notification): array
    {
        return [
            'id' => $notification->getId()->toRfc4122(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'data' => $notification->getData(),
            'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'read' => $notification->getReadAt() !== null,
        ];
    }
}
