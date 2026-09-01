<?php

namespace App\Repository;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\User;
use App\Service\DrinkingDayService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BeerEntry>
 */
class BeerEntryRepository extends ServiceEntityRepository
{
    /**
     * Small beer volume threshold in ml.
     * Beers with volumeMl <= this value count as 0.5 points.
     */
    private const SMALL_BEER_THRESHOLD = 330;

    /**
     * Minimum members with entries in a period for a group award to be granted.
     */
    private const MIN_ACTIVE_DRINKERS = 5;

    public function __construct(
        ManagerRegistry $registry,
        private DrinkingDayService $drinkingDayService,
    ) {
        parent::__construct($registry, BeerEntry::class);
    }

    /**
     * DQL expression for scoring: small beer (<=330ml) = 0.5 points, otherwise 1 point per quantity.
     */
    private function getScoreExpression(string $alias = 'e'): string
    {
        return "SUM(CASE WHEN {$alias}.volumeMl <= " . self::SMALL_BEER_THRESHOLD . " THEN {$alias}.quantity * 0.5 ELSE {$alias}.quantity * 1.0 END)";
    }

    /**
     * @return BeerEntry[]
     */
    public function findTodayByUser(User $user): array
    {
        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();

        return $this->createQueryBuilder('e')
            ->where('e.user = :user')
            ->andWhere('e.consumedAt >= :dayStart')
            ->andWhere('e.consumedAt < :dayEnd')
            ->setParameter('user', $user)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->orderBy('e.consumedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByUserInPeriod(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        return (float) ($this->createQueryBuilder('e')
            ->select($this->getScoreExpression())
            ->where('e.user = :user')
            ->andWhere('e.consumedAt >= :from')
            ->andWhere('e.consumedAt < :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    /**
     * Get leaderboard for a group - shows all members' personal beer consumption
     * (not just beers assigned to the group)
     */
    public function getLeaderboard(Group $group, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Get all beers from users who are members of the group
        return $this->createQueryBuilder('e')
            ->select('IDENTITY(e.user) as userId, u.name as userName, ' . $this->getScoreExpression() . ' as totalBeers, SUM(e.volumeMl * e.quantity) as totalVolume')
            ->innerJoin('e.user', 'u')
            ->innerJoin('App\Entity\GroupMember', 'gm', 'WITH', 'gm.user = e.user AND gm.group = :group')
            ->where('e.consumedAt >= :from')
            ->andWhere('e.consumedAt < :to')
            ->setParameter('group', $group)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('e.user, u.name')
            ->orderBy('totalBeers', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all members of a group with their stats (including those with 0 beers)
     * Uses single query with LEFT JOIN to avoid N+1 problem
     */
    public function getLeaderboardWithAllMembers(Group $group, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $scoreExpr = "COALESCE(SUM(CASE WHEN e.volumeMl <= " . self::SMALL_BEER_THRESHOLD . " THEN e.quantity * 0.5 ELSE e.quantity * 1.0 END), 0)";

        $results = $this->getEntityManager()->createQueryBuilder()
            ->select("u.id as userId, u.name as userName, {$scoreExpr} as totalBeers, COALESCE(SUM(e.volumeMl * e.quantity), 0) as totalVolume")
            ->from('App\Entity\GroupMember', 'gm')
            ->innerJoin('gm.user', 'u')
            ->leftJoin('App\Entity\BeerEntry', 'e', 'WITH', 'e.user = u AND e.consumedAt >= :from AND e.consumedAt < :to')
            ->where('gm.group = :group')
            ->setParameter('group', $group)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('u.id, u.name')
            ->orderBy('totalBeers', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn($row) => [
            'userId' => $row['userId']->toRfc4122(),
            'userName' => $row['userName'],
            'totalBeers' => (float) $row['totalBeers'],
            'totalVolume' => (int) $row['totalVolume'],
        ], $results);
    }

    /**
     * Get total lifetime statistics for a user
     */
    public function getTotalStatsByUser(User $user): array
    {
        $result = $this->createQueryBuilder('e')
            ->select($this->getScoreExpression() . ' as totalBeers, SUM(e.volumeMl * e.quantity) as totalVolume')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalBeers' => (float) ($result['totalBeers'] ?? 0),
            'totalVolume' => (int) ($result['totalVolume'] ?? 0),
        ];
    }

    /**
     * Get daily beer counts for user over a period
     * Uses drinking day logic (day boundary at 5:00 AM)
     * @return array<array{date: string, count: int}>
     */
    public function getDailyCountsByUser(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = $this->createQueryBuilder('e')
            ->select('e.consumedAt, e.quantity, e.volumeMl')
            ->where('e.user = :user')
            ->andWhere('e.consumedAt >= :from')
            ->andWhere('e.consumedAt < :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        // Group by drinking date in PHP
        $dailyCounts = [];
        foreach ($entries as $entry) {
            $drinkingDate = $this->drinkingDayService->getDrinkingDate($entry['consumedAt']);
            $score = $entry['volumeMl'] <= self::SMALL_BEER_THRESHOLD
                ? $entry['quantity'] * 0.5
                : $entry['quantity'];
            $dailyCounts[$drinkingDate] = ($dailyCounts[$drinkingDate] ?? 0) + $score;
        }

        // Sort by date and format result
        ksort($dailyCounts);

        $result = [];
        foreach ($dailyCounts as $date => $count) {
            $result[] = ['date' => $date, 'count' => (float) $count];
        }

        return $result;
    }

    /**
     * Get top beers by consumption count for user
     * @return array<array{name: string, count: int}>
     */
    public function getTopBeersByUser(User $user, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('e')
            ->select("COALESCE(b.name, e.customBeerName) as name, " . $this->getScoreExpression() . " as count")
            ->leftJoin('e.beer', 'b')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->groupBy('name')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn($r) => [
            'name' => $r['name'] ?? 'Neznámé pivo',
            'count' => (float) $r['count'],
        ], $results);
    }

    /**
     * Get top breweries by consumption count for user
     * @return array<array{name: string, count: int}>
     */
    public function getTopBreweriesByUser(User $user, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('e')
            ->select("COALESCE(b.brewery, 'Neznámý pivovar') as name, " . $this->getScoreExpression() . " as count")
            ->leftJoin('e.beer', 'b')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->groupBy('name')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn($r) => [
            'name' => $r['name'],
            'count' => (float) $r['count'],
        ], $results);
    }

    /**
     * Get current drinking streak (consecutive days)
     * Uses single query to fetch all dates and calculates streak in PHP
     * Uses "drinking day" logic (day boundary at 5:00 AM)
     */
    public function getCurrentStreakByUser(User $user): int
    {
        $entries = $this->createQueryBuilder('e')
            ->select('e.consumedAt')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.consumedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Extract unique drinking dates
        $dates = [];
        foreach ($entries as $entry) {
            $drinkingDate = $this->drinkingDayService->getDrinkingDate($entry['consumedAt']);
            $dates[$drinkingDate] = true;
        }
        $dates = array_keys($dates);
        rsort($dates);

        $streak = 0;
        $todayDrinkingDate = $this->drinkingDayService->getDrinkingDate(new \DateTimeImmutable());

        foreach ($dates as $dateStr) {
            $expectedDate = (new \DateTimeImmutable($todayDrinkingDate))->modify("-{$streak} days")->format('Y-m-d');

            if ($dateStr !== $expectedDate) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * Get average beers per day (across all days since first entry)
     * Uses drinking day logic (day boundary at 5:00 AM)
     */
    public function getAveragePerDayByUser(User $user): float
    {
        $entries = $this->createQueryBuilder('e')
            ->select('e.consumedAt, e.quantity, e.volumeMl')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.consumedAt', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($entries)) {
            return 0.0;
        }

        $total = 0;
        $firstDate = null;
        foreach ($entries as $entry) {
            $drinkingDate = $this->drinkingDayService->getDrinkingDate($entry['consumedAt']);
            if ($firstDate === null) {
                $firstDate = $drinkingDate;
            }
            $score = $entry['volumeMl'] <= self::SMALL_BEER_THRESHOLD
                ? $entry['quantity'] * 0.5
                : $entry['quantity'];
            $total += $score;
        }

        $today = $this->drinkingDayService->getDrinkingDate(new \DateTimeImmutable());
        $days = (new \DateTimeImmutable($firstDate))->diff(new \DateTimeImmutable($today))->days + 1;

        return round($total / $days, 1);
    }

    public function countGroupEntriesInPeriod(
        Group $group,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?BeerEntry $exclude = null,
    ): int {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.group = :group')
            ->andWhere('e.consumedAt >= :from')
            ->andWhere('e.consumedAt < :to')
            ->setParameter('group', $group)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($exclude !== null) {
            $qb->andWhere('e.id != :excludeId')
                ->setParameter('excludeId', $exclude->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get group awards for specified periods.
     * Returns award type => [userId, userName, value] for each award.
     *
     * Every award requires at least MIN_ACTIVE_DRINKERS members with entries in its period.
     * drinker_of_week and drinker_of_month are only evaluated when their boundaries are provided.
     */
    public function getGroupAwards(
        Group $group,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
        ?\DateTimeImmutable $weekStart = null,
        ?\DateTimeImmutable $weekEnd = null,
        ?\DateTimeImmutable $monthStart = null,
        ?\DateTimeImmutable $monthEnd = null,
    ): array {
        $periods = [
            'drinker_of_day' => [$dayStart, $dayEnd],
            'drinker_of_week' => [$weekStart, $weekEnd],
            'drinker_of_month' => [$monthStart, $monthEnd],
        ];

        $awards = [];

        foreach ($periods as $type => [$start, $end]) {
            if ($start === null || $end === null) {
                continue;
            }

            // Only beers drunk after the group was founded count towards its awards
            $start = max($start, $group->getCreatedAt());
            if ($start >= $end) {
                continue;
            }

            $winner = $this->findGroupPeriodWinner($group, $start, $end);
            if ($winner === null) {
                continue;
            }

            $awards[$type] = $winner;
        }

        return $awards;
    }

    /**
     * Find the member with the strictly highest score in the period.
     * A tie for first place means nobody gets the title.
     *
     * @return array{userId: string, userName: string, value: float}|null
     */
    private function findGroupPeriodWinner(Group $group, \DateTimeImmutable $start, \DateTimeImmutable $end): ?array
    {
        if ($this->countActiveDrinkers($group, $start, $end) < self::MIN_ACTIVE_DRINKERS) {
            return null;
        }

        $scoreExpr = 'COALESCE(' . $this->getScoreExpression() . ', 0)';

        $top = $this->getEntityManager()->createQueryBuilder()
            ->select("IDENTITY(gm.user) as userId, u.name as userName, {$scoreExpr} as totalBeers")
            ->from('App\Entity\GroupMember', 'gm')
            ->innerJoin('gm.user', 'u')
            ->leftJoin('App\Entity\BeerEntry', 'e', 'WITH', 'e.user = u AND e.consumedAt >= :start AND e.consumedAt < :end')
            ->where('gm.group = :group')
            ->setParameter('group', $group)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('gm.user, u.name')
            ->orderBy('totalBeers', 'DESC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        $winner = $top[0] ?? null;
        if ($winner === null || (float) $winner['totalBeers'] <= 0) {
            return null;
        }

        if (isset($top[1]) && (float) $top[1]['totalBeers'] >= (float) $winner['totalBeers']) {
            return null;
        }

        return [
            'userId' => $winner['userId'],
            'userName' => $winner['userName'],
            'value' => (float) $winner['totalBeers'],
        ];
    }

    private function countActiveDrinkers(Group $group, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT IDENTITY(gm.user))')
            ->from('App\Entity\GroupMember', 'gm')
            ->innerJoin('App\Entity\BeerEntry', 'e', 'WITH', 'e.user = gm.user AND e.consumedAt >= :start AND e.consumedAt < :end')
            ->where('gm.group = :group')
            ->setParameter('group', $group)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get aggregated stats for achievements calculation
     * Returns all data needed for achievements in a single query batch
     */
    public function getAchievementStatsByUser(User $user): array
    {
        // Basic totals
        $totals = $this->createQueryBuilder('e')
            ->select($this->getScoreExpression() . ' as totalBeers, SUM(e.volumeMl * e.quantity) as totalVolume')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        // Unique beers count
        $uniqueBeers = $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.beer) as count')
            ->where('e.user = :user')
            ->andWhere('e.beer IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Unique breweries count
        $uniqueBreweries = $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT b.brewery) as count')
            ->leftJoin('e.beer', 'b')
            ->where('e.user = :user')
            ->andWhere('b.brewery IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Size-based counts
        $sizeStats = $this->createQueryBuilder('e')
            ->select('SUM(CASE WHEN e.volumeMl >= 500 THEN e.quantity ELSE 0 END) as largeBeers')
            ->addSelect('SUM(CASE WHEN e.volumeMl <= 330 THEN e.quantity ELSE 0 END) as smallBeers')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        // Max beers per single beer type (loyalty)
        $maxLoyal = $this->createQueryBuilder('e')
            ->select($this->getScoreExpression() . ' as count')
            ->where('e.user = :user')
            ->andWhere('e.beer IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('e.beer')
            ->orderBy('count', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Fetch all entries for time-based calculations (weekend, daily, early/night)
        $allEntries = $this->createQueryBuilder('e')
            ->select('e.consumedAt, e.quantity, e.volumeMl, IDENTITY(e.beer) as beerId, e.customBeerName')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Calculate time-based stats in PHP using drinking day logic
        $weekendBeers = 0;
        $earlyBirdDays = [];
        $nightOwlDays = [];
        $newYearDays = [];
        $dailyCounts = [];
        $dailyBeerVariety = [];

        foreach ($allEntries as $entry) {
            $consumedAt = $entry['consumedAt'];
            $quantity = $entry['quantity'];
            $volumeMl = $entry['volumeMl'];
            $drinkingDate = $this->drinkingDayService->getDrinkingDate($consumedAt);
            $hour = (int) $consumedAt->format('H');

            $score = $volumeMl <= self::SMALL_BEER_THRESHOLD
                ? $quantity * 0.5
                : $quantity;

            // Weekend check uses drinking date
            $drinkingDayOfWeek = (int) (new \DateTimeImmutable($drinkingDate))->format('N');
            if ($drinkingDayOfWeek >= 6) {
                $weekendBeers += $score;
            }

            // Early bird (between 5:00 and 10:00), counted per drinking day
            if ($hour >= 5 && $hour < 10) {
                $earlyBirdDays[$drinkingDate] = true;
            }

            // Night owl (between 22:00 and 2:00), counted per drinking day
            if ($hour >= 22 || $hour < 2) {
                $nightOwlDays[$drinkingDate] = true;
            }

            // New Year's Eve, counted per year
            if (str_ends_with($drinkingDate, '-12-31')) {
                $newYearDays[substr($drinkingDate, 0, 4)] = true;
            }

            // Distinct beers per drinking day
            $beerKey = $entry['beerId'] ?? 'custom:' . ($entry['customBeerName'] ?? '');
            $dailyBeerVariety[$drinkingDate][$beerKey] = true;

            // Daily counts using drinking date
            $dailyCounts[$drinkingDate] = ($dailyCounts[$drinkingDate] ?? 0) + $score;
        }

        $maxDailyVariety = 0;
        $tastingDays = 0;
        foreach ($dailyBeerVariety as $beersOfDay) {
            $variety = count($beersOfDay);
            $maxDailyVariety = max($maxDailyVariety, $variety);
            if ($variety >= 5) {
                $tastingDays++;
            }
        }

        // Calculate max daily, consecutive days and days with X+ beers from daily counts
        $maxDaily = 0;
        $consecutiveDays = 0;
        $daysWith10Beers = 0;
        $daysWith15Beers = 0;

        if (!empty($dailyCounts)) {
            $maxDaily = max($dailyCounts);

            // Count days with 5+ and 10+ beers
            foreach ($dailyCounts as $count) {
                if ($count >= 10) {
                    $daysWith10Beers++;
                }
                if ($count >= 15) {
                    $daysWith15Beers++;
                }
            }

            // Calculate streak
            $dates = array_keys($dailyCounts);
            sort($dates);
            $maxStreak = 1;
            $currentStreak = 1;

            for ($i = 1; $i < count($dates); $i++) {
                $prev = new \DateTimeImmutable($dates[$i - 1]);
                $curr = new \DateTimeImmutable($dates[$i]);
                $diff = $curr->diff($prev)->days;

                if ($diff === 1) {
                    $currentStreak++;
                    $maxStreak = max($maxStreak, $currentStreak);
                }
                if ($diff !== 1) {
                    $currentStreak = 1;
                }
            }

            $consecutiveDays = $maxStreak;
        }

        return [
            'total_beers' => (float) ($totals['totalBeers'] ?? 0),
            'total_volume_ml' => (int) ($totals['totalVolume'] ?? 0),
            'unique_beers' => (int) $uniqueBeers,
            'unique_breweries' => (int) $uniqueBreweries,
            'large_beers' => (int) ($sizeStats['largeBeers'] ?? 0),
            'small_beers' => (int) ($sizeStats['smallBeers'] ?? 0),
            'weekend_beers' => $weekendBeers,
            'max_loyal' => (float) ($maxLoyal['count'] ?? 0),
            'max_daily' => $maxDaily,
            'consecutive_days' => $consecutiveDays,
            'early_bird_days' => count($earlyBirdDays),
            'night_owl_days' => count($nightOwlDays),
            'new_year_days' => count($newYearDays),
            'tasting_days' => $tastingDays,
            'max_daily_variety' => $maxDailyVariety,
            'days_with_10_beers' => $daysWith10Beers,
            'days_with_15_beers' => $daysWith15Beers,
        ];
    }
}
