<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class UserPasswordController extends AbstractController
{
    private const int MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/admin/user/{entityId}/set-password', name: 'admin_user_set_password', methods: ['GET', 'POST'])]
    public function setPassword(string $entityId, Request $request): Response
    {
        $user = $this->userRepository->find($entityId);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('Uživatel nenalezen.');
        }

        $backUrl = $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $user, $backUrl);
        }

        return $this->render('admin/user/set_password.html.twig', [
            'user' => $user,
            'back_url' => $backUrl,
        ]);
    }

    private function handlePost(Request $request, User $user, string $backUrl): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('set_password_' . $user->getId()->toRfc4122(), $token)) {
            $this->addFlash('danger', 'Neplatný CSRF token.');

            return new RedirectResponse($request->getUri());
        }

        $newPassword = (string) $request->request->get('new_password', '');
        $repeat = (string) $request->request->get('new_password_repeat', '');

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            $this->addFlash('danger', sprintf('Heslo musí mít alespoň %d znaků.', self::MIN_PASSWORD_LENGTH));

            return new RedirectResponse($request->getUri());
        }

        if ($newPassword !== $repeat) {
            $this->addFlash('danger', 'Hesla se neshodují.');

            return new RedirectResponse($request->getUri());
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Heslo uživatele %s bylo změněno.', $user->getEmail()));

        return new RedirectResponse($backUrl);
    }
}
