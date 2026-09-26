<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

class WebPushService
{
    private ?WebPush $webPush = null;

    public function __construct(
        private PushSubscriptionRepository $subscriptionRepository,
        private LoggerInterface $logger,
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
    ) {
    }

    /**
     * With $category only users who kept that notification category enabled
     * receive the push (null = not user-configurable, always send).
     *
     * @param User[] $users
     * @param array{title: string, body: string, url?: string, tag?: string} $payload
     */
    public function sendToUsers(array $users, array $payload, ?string $category = null): void
    {
        $recipients = $this->filterByPreference($users, $category);
        if (empty($recipients)) {
            return;
        }

        $subscriptions = $this->subscriptionRepository->findByUsers($recipients);
        if (empty($subscriptions)) {
            return;
        }

        $this->sendToSubscriptions($subscriptions, $payload);
    }

    public function sendToUser(User $user, array $payload, ?string $category = null): void
    {
        $this->sendToUsers([$user], $payload, $category);
    }

    /**
     * @param User[] $users
     *
     * @return User[]
     */
    private function filterByPreference(array $users, ?string $category): array
    {
        if ($category === null) {
            return $users;
        }

        return array_values(array_filter(
            $users,
            fn (User $user) => $user->isNotificationEnabled($category),
        ));
    }

    /**
     * @param PushSubscription[] $subscriptions
     */
    private function sendToSubscriptions(array $subscriptions, array $payload): void
    {
        $webPush = $this->getWebPush();
        $jsonPayload = json_encode($payload);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->getEndpoint(),
                    'publicKey' => $subscription->getAuthKey(),
                    'authToken' => $subscription->getAuthToken(),
                ]),
                $jsonPayload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $endpoint = $report->getEndpoint();
            $this->logger->warning('Push notification failed', [
                'endpoint' => $endpoint,
                'reason' => $report->getReason(),
            ]);

            if ($report->isSubscriptionExpired()) {
                $this->subscriptionRepository->removeByEndpoint($endpoint);
            }
        }
    }

    private function getWebPush(): WebPush
    {
        if ($this->webPush !== null) {
            return $this->webPush;
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => 'mailto:info@pivnikonto.cz',
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);

        $this->webPush->setReuseVAPIDHeaders(true);

        return $this->webPush;
    }
}
