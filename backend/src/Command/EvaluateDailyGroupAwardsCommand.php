<?php

namespace App\Command;

use App\Service\DrinkingDayService;
use App\Service\GroupAchievementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:evaluate-daily-group-awards',
    description: 'Evaluate and persist group awards for yesterday\'s drinking day',
)]
class EvaluateDailyGroupAwardsCommand extends Command
{
    public function __construct(
        private GroupAchievementService $groupAchievementService,
        private DrinkingDayService $drinkingDayService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'Specific date to evaluate (Y-m-d format, defaults to yesterday)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateStr = $input->getOption('date');
        if ($dateStr !== null) {
            $forDate = new \DateTimeImmutable($dateStr);
        }

        if ($dateStr === null) {
            // Yesterday's drinking day
            $forDate = new \DateTimeImmutable('yesterday');
        }

        $io->info(sprintf('Evaluating group awards for date: %s', $forDate->format('Y-m-d')));

        $totalSaved = $this->groupAchievementService->evaluateDailyAwards($forDate);

        $io->success(sprintf('Saved %d group awards for %s', $totalSaved, $forDate->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
