<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class PasswordResetController extends AbstractController
{
    private const TOKEN_TTL_MINUTES = 60;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $tokenRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private RateLimiterFactory $passwordResetLimiter,
    ) {
    }

    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $limiter = $this->passwordResetLimiter->create($request->getClientIp());
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            return $this->json([
                'error' => 'Příliš mnoho pokusů. Zkuste to později.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json([
                'error' => 'Email je povinný',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Always return success to prevent email enumeration
        $successResponse = $this->json([
            'message' => 'Pokud účet s tímto emailem existuje, odeslali jsme vám odkaz pro obnovení hesla.',
        ]);

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            return $successResponse;
        }

        // Delete existing tokens for this user
        $this->tokenRepository->deleteByUser($user);

        // Create new token
        $resetToken = new PasswordResetToken();
        $resetToken->setToken(bin2hex(random_bytes(64)));
        $resetToken->setUser($user);
        $resetToken->setExpiresAt(new \DateTimeImmutable('+' . self::TOKEN_TTL_MINUTES . ' minutes'));

        $this->entityManager->persist($resetToken);
        $this->entityManager->flush();

        // Send email
        $frontendUrl = $this->getParameter('app.frontend_url');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $resetToken->getToken();

        $emailMessage = (new Email())
            ->from('noreply@pivnikonto.cz')
            ->to($user->getEmail())
            ->subject('Obnovení hesla - Pivní Konto')
            ->html(
                '<h2>Obnovení hesla</h2>' .
                '<p>Ahoj ' . htmlspecialchars($user->getName()) . ',</p>' .
                '<p>Obdrželi jsme žádost o obnovení hesla k vašemu účtu.</p>' .
                '<p><a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;padding:12px 24px;background:#d97706;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Nastavit nové heslo</a></p>' .
                '<p>Odkaz je platný ' . self::TOKEN_TTL_MINUTES . ' minut.</p>' .
                '<p>Pokud jste o obnovení hesla nežádali, tento email ignorujte.</p>' .
                '<p>Na zdraví!<br>Pivní Konto</p>'
            );

        $this->mailer->send($emailMessage);

        return $successResponse;
    }

    #[Route('/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tokenString = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;

        if (!$tokenString || !$newPassword) {
            return $this->json([
                'error' => 'Token a nové heslo jsou povinné',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($newPassword) < 6) {
            return $this->json([
                'error' => 'Heslo musí mít alespoň 6 znaků',
            ], Response::HTTP_BAD_REQUEST);
        }

        $resetToken = $this->tokenRepository->findValidToken($tokenString);

        if (!$resetToken) {
            return $this->json([
                'error' => 'Neplatný nebo expirovaný odkaz pro obnovení hesla',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $resetToken->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

        // Delete all tokens for this user
        $this->tokenRepository->deleteByUser($user);

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Heslo bylo úspěšně změněno',
        ]);
    }
}
