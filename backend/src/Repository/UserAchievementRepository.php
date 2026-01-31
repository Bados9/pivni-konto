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
        return $this->findOneBy(['user' => $user, 'achievementId' => $achievementId]) !== null;
    }

    /**
     * @return string[]
     */
    public function getUnlockedIds(User $user): array
    {
        $results = $this->createQueryBuilder('ua')
            ->select('ua.achievementId')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'achievementId');
    }
}
