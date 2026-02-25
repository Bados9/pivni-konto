<?php

namespace App\Command;

use App\Service\GroupAchievementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:evaluate-group-achievements',
    description: 'Evaluate and persist group achievements (daily, weekly on Sundays, monthly on last day of month)',
)]
class EvaluateDailyGroupAwardsCommand extends Command
{
    public function __construct(
        private GroupAchievementService $groupAchievementService,
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
        $forDate = $dateStr !== null
            ? new \DateTimeImmutable($dateStr)
            : new \DateTimeImmutable('yesterday');

        $types = ['drinker_of_day'];
        if ((int) $forDate->format('N') === 7) {
            $types[] = 'drinker_of_week';
        }
        if ($forDate->format('j') === $forDate->format('t')) {
            $types[] = 'drinker_of_month';
        }

        $io->info(sprintf(
            'Evaluating group achievements for %s (types: %s)',
            $forDate->format('Y-m-d'),
            implode(', ', $types)
        ));

        $totalSaved = $this->groupAchievementService->evaluateGroupAchievements($forDate);

        $io->success(sprintf('Saved %d group achievements for %s', $totalSaved, $forDate->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
