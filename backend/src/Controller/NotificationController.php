<?php

namespace App\Controller;

use App\Controller\Trait\UuidValidationTrait;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    use UuidValidationTrait;

    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
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

    #[Route('/{id}/read', name: 'notifications_mark_read', methods: ['POST'])]
    public function markRead(string $id): JsonResponse
    {
        $uuid = $this->parseUuid($id);
        if ($uuid === null) {
            return $this->invalidUuidResponse();
        }

        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->find($uuid);
        if ($notification === null || $notification->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return $this->json(['error' => 'Notifikace nenalezena'], Response::HTTP_NOT_FOUND);
        }

        if ($notification->getReadAt() === null) {
            $notification->setReadAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $this->json(['success' => true]);
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
