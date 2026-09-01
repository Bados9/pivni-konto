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
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Backfill start date (Y-m-d, inclusive)');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Backfill end date (Y-m-d, inclusive, defaults to yesterday)');
        $this->addOption('no-notifications', null, InputOption::VALUE_NONE, 'Do not notify winners (use for backfills)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dates = $this->resolveDates($input, $io);
        if ($dates === null) {
            return Command::FAILURE;
        }

        $notify = !$input->getOption('no-notifications');
        $totalSaved = 0;

        foreach ($dates as $forDate) {
            $saved = $this->groupAchievementService->evaluateGroupAchievements($forDate, $notify);
            $totalSaved += $saved;
            $io->writeln(sprintf('%s: saved %d group achievements', $forDate->format('Y-m-d'), $saved));
        }

        $io->success(sprintf('Saved %d group achievements for %d day(s)', $totalSaved, count($dates)));

        return Command::SUCCESS;
    }

    /**
     * @return \DateTimeImmutable[]|null
     */
    private function resolveDates(InputInterface $input, SymfonyStyle $io): ?array
    {
        $fromStr = $input->getOption('from');
        $dateStr = $input->getOption('date');

        if ($fromStr === null) {
            $forDate = $dateStr !== null
                ? new \DateTimeImmutable($dateStr)
                : new \DateTimeImmutable('yesterday');

            return [$forDate];
        }

        if ($dateStr !== null) {
            $io->error('Options --date and --from cannot be combined.');

            return null;
        }

        $from = new \DateTimeImmutable($fromStr);
        $toStr = $input->getOption('to');
        $to = $toStr !== null
            ? new \DateTimeImmutable($toStr)
            : new \DateTimeImmutable('yesterday');

        if ($from > $to) {
            $io->error(sprintf('--from (%s) must not be after --to (%s).', $from->format('Y-m-d'), $to->format('Y-m-d')));

            return null;
        }

        $dates = [];
        for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
            $dates[] = $date;
        }

        return $dates;
    }
}
