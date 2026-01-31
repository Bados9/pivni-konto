<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginRateLimiterListener
{
    public function __construct(
        private RateLimiterFactory $loginLimiter,
        private RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: CheckPassportEvent::class, priority: 2048)]
    public function onCheckPassportEvent(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $limiter = $this->loginLimiter->create($request->getClientIp());
        $limit = $limiter->consume(0);

        if (!$limit->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException(
                (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60)
            );
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailureEvent(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $limiter = $this->loginLimiter->create($request->getClientIp());
        $limiter->consume(1);
    }
}
