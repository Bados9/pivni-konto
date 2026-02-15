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
    public function __construct(
        ManagerRegistry $registry,
        private DrinkingDayService $drinkingDayService,
    ) {
        parent::__construct($registry, BeerEntry::class);
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

    public function countByUserInPeriod(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('SUM(e.quantity)')
            ->where('e.user = :user')
            ->andWhere('e.consumedAt >= :from')
            ->andWhere('e.consumedAt < :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * Get leaderboard for a group - shows all members' personal beer consumption
     * (not just beers assigned to the group)
     */
    public function getLeaderboard(Group $group, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Get all beers from users who are members of the group
        return $this->createQueryBuilder('e')
            ->select('IDENTITY(e.user) as userId, u.name as userName, SUM(e.quantity) as totalBeers, SUM(e.volumeMl * e.quantity) as totalVolume')
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
        $results = $this->getEntityManager()->createQueryBuilder()
            ->select('u.id as userId, u.name as userName, COALESCE(SUM(e.quantity), 0) as totalBeers, COALESCE(SUM(e.volumeMl * e.quantity), 0) as totalVolume')
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
            'totalBeers' => (int) $row['totalBeers'],
            'totalVolume' => (int) $row['totalVolume'],
        ], $results);
    }

    /**
     * Get total lifetime statistics for a user
     */
    public function getTotalStatsByUser(User $user): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity) as totalBeers, SUM(e.volumeMl * e.quantity) as totalVolume')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalBeers' => (int) ($result['totalBeers'] ?? 0),
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
            ->select('e.consumedAt, e.quantity')
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
            $dailyCounts[$drinkingDate] = ($dailyCounts[$drinkingDate] ?? 0) + $entry['quantity'];
        }

        // Sort by date and format result
        ksort($dailyCounts);

        $result = [];
        foreach ($dailyCounts as $date => $count) {
            $result[] = ['date' => $date, 'count' => (int) $count];
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
            ->select("COALESCE(b.name, e.customBeerName) as name, SUM(e.quantity) as count")
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
            'count' => (int) $r['count'],
        ], $results);
    }

    /**
     * Get top breweries by consumption count for user
     * @return array<array{name: string, count: int}>
     */
    public function getTopBreweriesByUser(User $user, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('e')
            ->select("COALESCE(b.brewery, 'Neznámý pivovar') as name, SUM(e.quantity) as count")
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
            'count' => (int) $r['count'],
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
     * Get average beers per day (days with at least one beer)
     * Uses drinking day logic (day boundary at 5:00 AM)
     */
    public function getAveragePerDayByUser(User $user): float
    {
        $entries = $this->createQueryBuilder('e')
            ->select('e.consumedAt, e.quantity')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $uniqueDays = [];
        $total = 0;
        foreach ($entries as $entry) {
            $drinkingDate = $this->drinkingDayService->getDrinkingDate($entry['consumedAt']);
            $uniqueDays[$drinkingDate] = true;
            $total += $entry['quantity'];
        }

        $days = count($uniqueDays);

        if ($days === 0) {
            return 0.0;
        }

        return round($total / $days, 1);
    }

    /**
     * Get aggregated stats for achievements calculation
     * Returns all data needed for achievements in a single query batch
     */
    public function getAchievementStatsByUser(User $user): array
    {
        // Basic totals
        $totals = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity) as totalBeers, SUM(e.volumeMl * e.quantity) as totalVolume')
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
            ->select('SUM(e.quantity) as count')
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
            ->select('e.consumedAt, e.quantity')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Calculate time-based stats in PHP using drinking day logic
        $weekendBeers = 0;
        $earlyBird = false;
        $nightOwl = false;
        $dailyCounts = [];

        foreach ($allEntries as $entry) {
            $consumedAt = $entry['consumedAt'];
            $quantity = $entry['quantity'];
            $drinkingDate = $this->drinkingDayService->getDrinkingDate($consumedAt);
            $hour = (int) $consumedAt->format('H');

            // Weekend check uses drinking date
            $drinkingDayOfWeek = (int) (new \DateTimeImmutable($drinkingDate))->format('N');
            if ($drinkingDayOfWeek >= 6) {
                $weekendBeers += $quantity;
            }

            // Early bird (before 10:00 but after 5:00 - drinking day start)
            if ($hour >= 5 && $hour < 10) {
                $earlyBird = true;
            }

            // Night owl (after midnight, before 5:00)
            if ($hour >= 0 && $hour < 5) {
                $nightOwl = true;
            }

            // Daily counts using drinking date
            $dailyCounts[$drinkingDate] = ($dailyCounts[$drinkingDate] ?? 0) + $quantity;
        }

        // Calculate max daily, consecutive days and days with X+ beers from daily counts
        $maxDaily = 0;
        $consecutiveDays = 0;
        $daysWith5Beers = 0;
        $daysWith10Beers = 0;

        if (!empty($dailyCounts)) {
            $maxDaily = max($dailyCounts);

            // Count days with 5+ and 10+ beers
            foreach ($dailyCounts as $count) {
                if ($count >= 5) {
                    $daysWith5Beers++;
                }
                if ($count >= 10) {
                    $daysWith10Beers++;
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
            'total_beers' => (int) ($totals['totalBeers'] ?? 0),
            'total_volume_ml' => (int) ($totals['totalVolume'] ?? 0),
            'unique_beers' => (int) $uniqueBeers,
            'unique_breweries' => (int) $uniqueBreweries,
            'large_beers' => (int) ($sizeStats['largeBeers'] ?? 0),
            'small_beers' => (int) ($sizeStats['smallBeers'] ?? 0),
            'weekend_beers' => $weekendBeers,
            'max_loyal' => (int) ($maxLoyal['count'] ?? 0),
            'max_daily' => $maxDaily,
            'consecutive_days' => $consecutiveDays,
            'early_bird' => $earlyBird,
            'night_owl' => $nightOwl,
            'days_with_5_beers' => $daysWith5Beers,
            'days_with_10_beers' => $daysWith10Beers,
        ];
    }
}
