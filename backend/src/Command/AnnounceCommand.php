<?php

namespace App\Command;

use App\Entity\Notification;
use App\Repository\UserRepository;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:announce',
    description: 'Send an in-app announcement to all users (bell + one-time popup), optionally as web push',
)]
class AnnounceCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private WebPushService $webPushService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('title', InputArgument::REQUIRED, 'Announcement title (shown as heading)');
        $this->addArgument('message', InputArgument::REQUIRED, 'Announcement text');
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'In-app URL the notification points to', '/');
        $this->addOption('push', null, InputOption::VALUE_NONE, 'Also send a web push to subscribed users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $title = $input->getArgument('title');
        $message = $input->getArgument('message');
        $url = $input->getOption('url');

        $users = $this->userRepository->findAll();

        foreach ($users as $user) {
            $notification = new Notification();
            $notification->setUser($user);
            $notification->setType('announcement');
            $notification->setTitle($title);
            $notification->setMessage($message);
            $notification->setData(['url' => $url]);
            $this->em->persist($notification);
        }

        $this->em->flush();

        if ($input->getOption('push')) {
            $this->sendPush($users, $title, $message, $url, $io);
        }

        $io->success(sprintf('Announcement created for %d user(s).', count($users)));

        return Command::SUCCESS;
    }

    private function sendPush(array $users, string $title, string $message, string $url, SymfonyStyle $io): void
    {
        try {
            $this->webPushService->sendToUsers($users, [
                'title' => $title,
                'body' => $message,
                'url' => $url,
                'tag' => 'announcement-' . time(),
            ]);
        } catch (\Throwable $e) {
            $io->warning('Web push failed: ' . $e->getMessage());
        }
    }
}
