<?php

namespace App\Command;

use App\Entity\UserAchievement;
use App\Repository\UserAchievementRepository;
use App\Repository\UserRepository;
use App\Service\AchievementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reconcile-achievements',
    description: 'Re-evaluate a non-repeatable achievement for all users: unlock where earned, remove where the condition is not met',
)]
class ReconcileAchievementsCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserAchievementRepository $achievementRepository,
        private AchievementService $achievementService,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('achievementId', InputArgument::REQUIRED, 'Achievement id to reconcile (e.g. weekend_warrior)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $achievementId = $input->getArgument('achievementId');
        $dryRun = (bool) $input->getOption('dry-run');

        $definition = $this->achievementService->getDefinition($achievementId);
        if ($definition === null) {
            $io->error(sprintf('Unknown achievement "%s".', $achievementId));

            return Command::FAILURE;
        }

        if ($definition['repeatable'] ?? false) {
            $io->error('Only non-repeatable achievements can be reconciled (repeatable counts are topped up automatically).');

            return Command::FAILURE;
        }

        $added = 0;
        $removed = 0;

        foreach ($this->userRepository->findAll() as $user) {
            $earned = $this->achievementService->isEarnedByUser($user, $achievementId);
            $rows = $this->achievementRepository->findBy(['user' => $user, 'achievementId' => $achievementId]);

            if ($earned && $rows === []) {
                $io->writeln(sprintf('+ %s: unlocking', $user->getEmail()));
                $added++;

                if ($dryRun) {
                    continue;
                }

                $achievement = new UserAchievement();
                $achievement->setUser($user);
                $achievement->setAchievementId($achievementId);
                $this->em->persist($achievement);
                continue;
            }

            if (!$earned && $rows !== []) {
                $io->writeln(sprintf('- %s: removing %d row(s)', $user->getEmail(), count($rows)));
                $removed++;

                if ($dryRun) {
                    continue;
                }

                foreach ($rows as $row) {
                    $this->em->remove($row);
                }
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s"%s": %d unlocked, %d removed',
            $dryRun ? '[dry-run] ' : '',
            $achievementId,
            $added,
            $removed,
        ));

        return Command::SUCCESS;
    }
}
