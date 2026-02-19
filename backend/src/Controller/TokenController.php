<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RefreshToken;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TokenController extends AbstractController
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/token/refresh', name: 'token_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tokenString = $data['refreshToken'] ?? null;

        if (!$tokenString) {
            return $this->json([
                'error' => 'Refresh token je povinný',
            ], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = $this->refreshTokenRepository->findValidToken($tokenString);

        if (!$refreshToken) {
            return $this->json([
                'error' => 'Neplatný nebo expirovaný refresh token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->userRepository->findByEmail($refreshToken->getUsername());

        if (!$user) {
            $this->entityManager->remove($refreshToken);
            $this->entityManager->flush();
            return $this->json([
                'error' => 'Uživatel nenalezen',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Delete old token (single use)
        $this->entityManager->remove($refreshToken);

        // Create new refresh token
        $newRefreshToken = new RefreshToken();
        $newRefreshToken->setToken(bin2hex(random_bytes(64)));
        $newRefreshToken->setUsername($user->getEmail());
        $newRefreshToken->setExpiresAt(new \DateTime('+30 days'));

        $this->entityManager->persist($newRefreshToken);
        $this->entityManager->flush();

        // Generate new JWT
        $jwt = $this->jwtManager->create($user);

        return $this->json([
            'token' => $jwt,
            'refreshToken' => $newRefreshToken->getToken(),
        ]);
    }
}
