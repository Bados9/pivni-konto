<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RefreshToken;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationSuccessListener
{
    private const REFRESH_TOKEN_TTL = 2592000; // 30 days

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $username = $user->getUserIdentifier();

        $this->refreshTokenRepository->deleteByUsername($username);

        $refreshToken = new RefreshToken();
        $refreshToken->setToken(bin2hex(random_bytes(64)));
        $refreshToken->setUsername($username);
        $refreshToken->setExpiresAt(new \DateTime('+' . self::REFRESH_TOKEN_TTL . ' seconds'));

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        $data = $event->getData();
        $data['refreshToken'] = $refreshToken->getToken();
        $event->setData($data);
    }
}
