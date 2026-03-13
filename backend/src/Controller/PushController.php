<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/push')]
class PushController extends AbstractController
{
    public function __construct(
        private PushSubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
        private WebPushService $webPushService,
        private string $vapidPublicKey,
    ) {
    }

    #[Route('/vapid-key', name: 'push_vapid_key', methods: ['GET'])]
    public function vapidKey(): JsonResponse
    {
        return $this->json(['publicKey' => $this->vapidPublicKey]);
    }

    #[Route('/subscribe', name: 'push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $endpoint = $data['endpoint'] ?? null;
        $keys = $data['keys'] ?? [];
        $p256dh = $keys['p256dh'] ?? null;
        $auth = $keys['auth'] ?? null;

        if (!$endpoint || !$p256dh || !$auth) {
            return $this->json(['error' => 'Neplatná data'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->subscriptionRepository->findByEndpoint($user, $endpoint);

        if ($existing !== null) {
            $existing->setAuthKey($p256dh);
            $existing->setAuthToken($auth);
            $this->entityManager->flush();

            return $this->json(['message' => 'Subscription updated']);
        }

        $subscription = new PushSubscription();
        $subscription->setUser($user);
        $subscription->setEndpoint($endpoint);
        $subscription->setAuthKey($p256dh);
        $subscription->setAuthToken($auth);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $this->json(['message' => 'Subscribed'], Response::HTTP_CREATED);
    }

    #[Route('/unsubscribe', name: 'push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $endpoint = $data['endpoint'] ?? null;
        if (!$endpoint) {
            return $this->json(['error' => 'Endpoint je povinný'], Response::HTTP_BAD_REQUEST);
        }

        $subscription = $this->subscriptionRepository->findByEndpoint($user, $endpoint);
        if ($subscription !== null) {
            $this->entityManager->remove($subscription);
            $this->entityManager->flush();
        }

        return $this->json(['message' => 'Unsubscribed']);
    }

    #[Route('/test', name: 'push_test', methods: ['POST'])]
    public function test(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->webPushService->sendToUser($user, [
            'title' => 'Pivní Konto',
            'body' => 'Notifikace fungují! 🍺',
            'url' => '/profile',
        ]);

        return $this->json(['message' => 'Test notification sent']);
    }
}
