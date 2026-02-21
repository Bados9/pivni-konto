<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserAchievement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    /**
     * @return UserAchievement[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['unlockedAt' => 'DESC']);
    }

    public function hasAchievement(User $user, string $achievementId): bool
    {
        return $this->countByUserAndAchievement($user, $achievementId) > 0;
    }

    /**
     * @return string[]
     */
    public function getUnlockedIds(User $user): array
    {
        $results = $this->createQueryBuilder('ua')
            ->select('DISTINCT ua.achievementId')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'achievementId');
    }

    /**
     * @return array<string, int>
     */
    public function getUnlockedWithCounts(User $user): array
    {
        $rows = $this->createQueryBuilder('ua')
            ->select('ua.achievementId, COUNT(ua.id) as cnt')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->groupBy('ua.achievementId')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['achievementId']] = (int) $row['cnt'];
        }

        return $result;
    }

    public function countByUserAndAchievement(User $user, string $achievementId): int
    {
        return (int) $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->where('ua.user = :user')
            ->andWhere('ua.achievementId = :achievementId')
            ->setParameter('user', $user)
            ->setParameter('achievementId', $achievementId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMaxConsecutiveDays(User $user, string $achievementId): int
    {
        $rows = $this->createQueryBuilder('ua')
            ->select('ua.unlockedAt')
            ->where('ua.user = :user')
            ->andWhere('ua.achievementId = :achievementId')
            ->setParameter('user', $user)
            ->setParameter('achievementId', $achievementId)
            ->orderBy('ua.unlockedAt', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($rows)) {
            return 0;
        }

        // Extract unique dates
        $dates = [];
        foreach ($rows as $row) {
            $dates[] = $row['unlockedAt']->format('Y-m-d');
        }
        $dates = array_unique($dates);
        $dates = array_values($dates);

        if (count($dates) < 2) {
            return count($dates);
        }

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

        return $maxStreak;
    }

    public function hasAchievementOnDate(User $user, string $achievementId, \DateTimeImmutable $date): bool
    {
        $dayStart = $date->setTime(0, 0, 0);
        $dayEnd = $date->setTime(23, 59, 59);

        return (int) $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->where('ua.user = :user')
            ->andWhere('ua.achievementId = :achievementId')
            ->andWhere('ua.unlockedAt >= :dayStart')
            ->andWhere('ua.unlockedAt <= :dayEnd')
            ->setParameter('user', $user)
            ->setParameter('achievementId', $achievementId)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function findByUserAndAchievementId(User $user, string $achievementId): ?UserAchievement
    {
        return $this->findOneBy(['user' => $user, 'achievementId' => $achievementId]);
    }
}
