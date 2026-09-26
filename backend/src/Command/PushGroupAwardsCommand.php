<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:push-group-awards',
    description: 'Send the deferred web push for group awards created by the morning evaluation (run at a humane hour)',
)]
class PushGroupAwardsCommand extends Command
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private WebPushService $webPushService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // only fresh awards - old unpushed rows (backfills) stay silent
        $since = new \DateTimeImmutable('-24 hours');
        $notifications = $this->notificationRepository->findUnpushedByType('group_award', $since);

        foreach ($notifications as $notification) {
            $this->push($notification);
            $notification->setPushedAt(new \DateTimeImmutable());
        }

        $this->em->flush();

        $io->success(sprintf('%d group award push(es) sent', count($notifications)));

        return Command::SUCCESS;
    }

    private function push(Notification $notification): void
    {
        $data = $notification->getData() ?? [];

        try {
            $this->webPushService->sendToUser($notification->getUser(), [
                'title' => $notification->getTitle(),
                'body' => $notification->getMessage(),
                'url' => '/profile',
                'tag' => sprintf(
                    'group-award-%s-%s',
                    $data['achievementId'] ?? 'award',
                    $data['groupId'] ?? 'group',
                ),
            ], 'group_award');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send group award push', [
                'notificationId' => $notification->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
