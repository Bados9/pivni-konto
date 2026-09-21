<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\AnnouncementSender;
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
        private AnnouncementSender $announcementSender,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('title', InputArgument::REQUIRED, 'Announcement title (shown as heading)');
        $this->addArgument('message', InputArgument::REQUIRED, 'Announcement text');
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'In-app URL the notification points to', '/');
        $this->addOption('push', null, InputOption::VALUE_NONE, 'Also send a web push to subscribed users');
        $this->addOption('user', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only send to these user emails (default: everyone)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->resolveRecipients($input->getOption('user'), $io);
        if ($users === null) {
            return Command::FAILURE;
        }

        $count = $this->announcementSender->send(
            $users,
            $input->getArgument('title'),
            $input->getArgument('message'),
            $input->getOption('url'),
            (bool) $input->getOption('push'),
        );

        $io->success(sprintf('Announcement created for %d user(s).', $count));

        return Command::SUCCESS;
    }

    /**
     * @param string[] $emails
     *
     * @return \App\Entity\User[]|null null when an email does not exist
     */
    private function resolveRecipients(array $emails, SymfonyStyle $io): ?array
    {
        if ($emails === []) {
            return $this->userRepository->findAll();
        }

        $users = [];
        foreach ($emails as $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user === null) {
                $io->error(sprintf('User "%s" not found.', $email));

                return null;
            }
            $users[] = $user;
        }

        return $users;
    }
}
