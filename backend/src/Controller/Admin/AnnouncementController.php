<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Service\AnnouncementSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnnouncementController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AnnouncementSender $announcementSender,
    ) {
    }

    #[Route('/admin/announce', name: 'admin_announce', methods: ['GET', 'POST'])]
    public function announce(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handlePost($request);
        }

        return $this->render('admin/announcement/send.html.twig', [
            'users' => $this->userRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    private function handlePost(Request $request): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('admin_announce', $token)) {
            $this->addFlash('danger', 'Neplatný CSRF token.');

            return new RedirectResponse($request->getUri());
        }

        $title = trim((string) $request->request->get('title', ''));
        $message = trim((string) $request->request->get('message', ''));
        if ($title === '' || $message === '') {
            $this->addFlash('danger', 'Titulek i text jsou povinné.');

            return new RedirectResponse($request->getUri());
        }

        $url = trim((string) $request->request->get('url', '')) ?: '/';
        $push = $request->request->getBoolean('push');

        $recipients = $this->resolveRecipients($request->request->all('recipients'));
        if ($recipients === []) {
            $this->addFlash('danger', 'Žádný platný příjemce.');

            return new RedirectResponse($request->getUri());
        }

        $count = $this->announcementSender->send($recipients, $title, $message, $url, $push);

        $this->addFlash('success', sprintf(
            'Oznámení odesláno %d uživatelům%s.',
            $count,
            $push ? ' (včetně push notifikace)' : '',
        ));

        return new RedirectResponse($request->getUri());
    }

    /**
     * @param string[] $selectedIds empty selection means everyone
     *
     * @return \App\Entity\User[]
     */
    private function resolveRecipients(array $selectedIds): array
    {
        if ($selectedIds === []) {
            return $this->userRepository->findAll();
        }

        return $this->userRepository->findBy(['id' => $selectedIds]);
    }
}
