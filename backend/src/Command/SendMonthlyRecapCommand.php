<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RecapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-monthly-recap',
    description: 'Send recap notifications for the previous month (run on the 1st)',
)]
class SendMonthlyRecapCommand extends Command
{
    public function __construct(
        private RecapService $recapService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sent = $this->recapService->sendMonthlyRecaps();
        $io->success(sprintf('%d monthly recap(s) sent', $sent));

        return Command::SUCCESS;
    }
}
