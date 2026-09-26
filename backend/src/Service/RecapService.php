<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Weekly and monthly recap: personal totals plus rank and winner per group.
 * Only users with at least one entry in the period get one.
 */
class RecapService
{
    private const MONTH_NAMES = [
        'leden', 'únor', 'březen', 'duben', 'květen', 'červen',
        'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec',
    ];

    public function __construct(
        private UserRepository $userRepository,
        private GroupMemberRepository $memberRepository,
        private BeerEntryRepository $entryRepository,
        private UserAchievementRepository $achievementRepository,
        private WebPushService $webPushService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Previous drinking week: Monday 5:00 to Monday 5:00. Run on Mondays.
     */
    public function sendWeeklyRecaps(): int
    {
        $monday = new \DateTimeImmutable('last monday');
        $from = new \DateTimeImmutable($monday->format('Y-m-d') . ' 05:00');

        return $this->sendRecaps('weekly', $from, $from->modify('+7 days'));
    }

    /**
     * Previous calendar month (drinking-day boundaries). Run on the 1st.
     */
    public function sendMonthlyRecaps(): int
    {
        $thisMonthStart = new \DateTimeImmutable((new \DateTimeImmutable())->format('Y-m-01') . ' 05:00');

        return $this->sendRecaps('monthly', $thisMonthStart->modify('-1 month'), $thisMonthStart);
    }

    private function sendRecaps(string $period, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $leaderboards = [];
        $sent = 0;

        foreach ($this->userRepository->findAll() as $user) {
            $stats = $this->entryRepository->getUserPeriodStats($user, $from, $to);
            if ($stats['score'] <= 0) {
                continue;
            }

            $lines = [$this->summaryLine($period, $stats)];

            foreach ($this->memberRepository->findBy(['user' => $user]) as $membership) {
                $group = $membership->getGroup();
                $groupId = $group->getId()->toRfc4122();
                $leaderboards[$groupId] ??= $this->entryRepository->getLeaderboard($group, $from, $to);

                $line = $this->groupLine($group->getName(), $leaderboards[$groupId], $user);
                if ($line !== null) {
                    $lines[] = $line;
                }
            }

            if ($period === 'monthly') {
                $achievementCount = $this->achievementRepository->countUnlockedInPeriod($user, $from, $to);
                if ($achievementCount > 0) {
                    $lines[] = sprintf('🏅 Nové achievementy: %d', $achievementCount);
                }
            }

            $this->createNotification($user, $period, $from, $to, $lines);
            $this->sendPush($user, $this->title($period, $from), $lines[0], $period);
            $sent++;
        }

        $this->em->flush();

        return $sent;
    }

    /**
     * @param array{score: float, volume: int} $stats
     */
    private function summaryLine(string $period, array $stats): string
    {
        $label = $period === 'weekly' ? 'Minulý týden' : 'Minulý měsíc';

        return sprintf(
            '%s: %s piv, %s l 🍺',
            $label,
            ScoreFormatter::score($stats['score']),
            ScoreFormatter::litres($stats['volume']),
        );
    }

    private function groupLine(string $groupName, array $leaderboard, User $user): ?string
    {
        $userId = $user->getId()->toRfc4122();
        $winner = $leaderboard[0] ?? null;
        $rank = null;

        foreach ($leaderboard as $index => $row) {
            if ((string) $row['userId'] === $userId) {
                $rank = $index + 1;
                break;
            }
        }

        if ($rank === null || $winner === null) {
            return null;
        }

        if ($rank === 1) {
            return sprintf('🏆 %s: 1. místo – byl/a jsi nejlepší!', $groupName);
        }

        return sprintf(
            '%s: %d. místo (vyhrál/a %s – %s piv)',
            $groupName,
            $rank,
            $winner['userName'],
            ScoreFormatter::score((float) $winner['totalBeers']),
        );
    }

    private function title(string $period, \DateTimeImmutable $from): string
    {
        if ($period === 'weekly') {
            return '📊 Týdenní shrnutí';
        }

        return sprintf('📊 Shrnutí: %s', self::MONTH_NAMES[(int) $from->format('n') - 1]);
    }

    /**
     * @param string[] $lines
     */
    private function createNotification(
        User $user,
        string $period,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        array $lines,
    ): void {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('recap');
        $notification->setTitle($this->title($period, $from));
        $notification->setMessage(mb_substr(implode("\n", $lines), 0, 500));
        $notification->setData([
            'period' => $period,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);

        $this->em->persist($notification);
    }

    private function sendPush(User $user, string $title, string $summary, string $period): void
    {
        try {
            $this->webPushService->sendToUser($user, [
                'title' => $title,
                'body' => $summary . ' Podrobnosti najdeš ve zvonečku.',
                'url' => '/stats',
                'tag' => 'recap-' . $period,
            ], 'recap');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send recap push', [
                'user' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
