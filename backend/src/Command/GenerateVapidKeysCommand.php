<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Generate a VAPID key pair for Web Push notifications',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keys = VAPID::createVapidKeys();

        $output->writeln('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $output->writeln('VAPID_PRIVATE_KEY=' . $keys['privateKey']);

        $io->note([
            'Add these two lines to backend/.env.local (on the server) and restart the php and cron containers.',
            'Rotating the private key invalidates all stored push subscriptions - users must re-enable notifications.',
        ]);

        return Command::SUCCESS;
    }
}
