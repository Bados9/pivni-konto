<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\GroupAchievement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupAchievement>
 */
class GroupAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupAchievement::class);
    }

    public function findExisting(Group $group, string $type, \DateTimeImmutable $date): ?GroupAchievement
    {
        return $this->createQueryBuilder('ga')
            ->where('ga.group = :group')
            ->andWhere('ga.type = :type')
            ->andWhere('ga.date = :date')
            ->setParameter('group', $group)
            ->setParameter('type', $type)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count distinct days a user won a specific award type (across all groups)
     */
    public function countDistinctDaysByUserAndType(User $user, string $type): int
    {
        return (int) $this->createQueryBuilder('ga')
            ->select('COUNT(DISTINCT ga.date)')
            ->where('ga.user = :user')
            ->andWhere('ga.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get max consecutive days a user won a specific award type (across all groups)
     */
    public function getMaxConsecutiveDays(User $user, string $type): int
    {
        $rows = $this->createQueryBuilder('ga')
            ->select('DISTINCT ga.date')
            ->where('ga.user = :user')
            ->andWhere('ga.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('ga.date', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($rows)) {
            return 0;
        }

        $maxStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($rows); $i++) {
            $prev = $rows[$i - 1]['date'];
            $curr = $rows[$i]['date'];
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
}
