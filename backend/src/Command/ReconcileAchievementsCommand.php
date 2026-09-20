<?php

namespace App\Command;

use App\Entity\User;
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
    description: 'Recompute achievements from entry data for all users: unlock missing, remove undeserved, fix repeatable counts',
)]
class ReconcileAchievementsCommand extends Command
{
    private SymfonyStyle $io;
    private bool $dryRun = false;

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
        $this->addArgument('achievementId', InputArgument::OPTIONAL, 'Reconcile only this achievement (default: all)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->dryRun = (bool) $input->getOption('dry-run');

        $achievementId = $input->getArgument('achievementId');
        if ($achievementId !== null && !$this->validateAchievementId($achievementId)) {
            return Command::FAILURE;
        }

        $added = 0;
        $removed = 0;

        foreach ($this->userRepository->findAll() as $user) {
            [$userAdded, $userRemoved] = $this->reconcileUser($user, $achievementId);
            $added += $userAdded;
            $removed += $userRemoved;
        }

        if (!$this->dryRun) {
            $this->em->flush();
        }

        $this->io->success(sprintf(
            '%s%s: %d row(s) added, %d row(s) removed',
            $this->dryRun ? '[dry-run] ' : '',
            $achievementId ?? 'all achievements',
            $added,
            $removed,
        ));

        return Command::SUCCESS;
    }

    private function validateAchievementId(string $achievementId): bool
    {
        if ($this->achievementService->getDefinition($achievementId) === null) {
            $this->io->error(sprintf('Unknown achievement "%s".', $achievementId));

            return false;
        }

        if (in_array($achievementId, AchievementService::CRON_SOURCED, true)) {
            $this->io->error(sprintf(
                '"%s" is granted by the group awards cron - its rows are the source of truth and cannot be reconciled from entries.',
                $achievementId,
            ));

            return false;
        }

        return true;
    }

    /**
     * @return array{int, int} rows added, rows removed
     */
    private function reconcileUser(User $user, ?string $onlyAchievementId): array
    {
        $desired = $this->achievementService->getDesiredCounts($user);
        if ($onlyAchievementId !== null) {
            $desired = [$onlyAchievementId => $desired[$onlyAchievementId] ?? 0];
        }

        $current = $this->achievementRepository->getUnlockedWithCounts($user);
        $added = 0;
        $removed = 0;

        foreach ($desired as $id => $target) {
            $count = $current[$id] ?? 0;

            if ($target > $count) {
                $this->addRows($user, $id, $target - $count);
                $added += $target - $count;
                continue;
            }

            if ($target < $count) {
                $this->removeNewestRows($user, $id, $count - $target);
                $removed += $count - $target;
            }
        }

        return [$added, $removed];
    }

    private function addRows(User $user, string $achievementId, int $count): void
    {
        $this->io->writeln(sprintf('+ %s: %s ×%d', $user->getEmail(), $achievementId, $count));

        if ($this->dryRun) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $achievement = new UserAchievement();
            $achievement->setUser($user);
            $achievement->setAchievementId($achievementId);
            $this->em->persist($achievement);
        }
    }

    private function removeNewestRows(User $user, string $achievementId, int $count): void
    {
        $this->io->writeln(sprintf('- %s: %s ×%d', $user->getEmail(), $achievementId, $count));

        if ($this->dryRun) {
            return;
        }

        // keep the oldest rows so the original unlock date survives
        $rows = $this->achievementRepository->findBy(
            ['user' => $user, 'achievementId' => $achievementId],
            ['unlockedAt' => 'DESC'],
            $count,
        );

        foreach ($rows as $row) {
            $this->em->remove($row);
        }
    }
}
