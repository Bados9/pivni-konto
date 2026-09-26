<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\BeerEntryRepository;
use App\Repository\UserRepository;
use App\Service\DrinkingDayService;
use App\Service\WebPushService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-streak-risk',
    description: 'Evening reminder push to users whose drinking streak (2+ days) would end today without a beer',
)]
class NotifyStreakRiskCommand extends Command
{
    private const MIN_STREAK = 2;

    public function __construct(
        private UserRepository $userRepository,
        private BeerEntryRepository $entryRepository,
        private DrinkingDayService $drinkingDayService,
        private WebPushService $webPushService,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();
        $sent = 0;

        foreach ($this->userRepository->findAll() as $user) {
            if (!$user->isNotificationEnabled('streak')) {
                continue;
            }

            if ($this->entryRepository->countByUserInPeriod($user, $dayStart, $dayEnd) > 0) {
                continue;
            }

            $streak = $this->entryRepository->getStreakEndingYesterday($user);
            if ($streak < self::MIN_STREAK) {
                continue;
            }

            $this->push($user, $streak);
            $sent++;
        }

        $io->success(sprintf('%d streak reminder(s) sent', $sent));

        return Command::SUCCESS;
    }

    private function push(User $user, int $streak): void
    {
        try {
            $this->webPushService->sendToUser($user, [
                'title' => sprintf('🔥 Série %d dní v ohrožení!', $streak),
                'body' => 'Dnes tu od tebe ještě žádné pivo nemáme. Nenech svou sérii večer padnout! 🍺',
                'url' => '/',
                'tag' => 'streak-risk',
            ], 'streak');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send streak reminder', [
                'user' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
