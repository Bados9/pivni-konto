<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function findByEndpoint(User $user, string $endpoint): ?PushSubscription
    {
        return $this->findOneBy(['user' => $user, 'endpoint' => $endpoint]);
    }

    /**
     * @param User[] $users
     * @return PushSubscription[]
     */
    public function findByUsers(array $users): array
    {
        if (empty($users)) {
            return [];
        }

        return $this->createQueryBuilder('ps')
            ->where('ps.user IN (:users)')
            ->setParameter('users', $users)
            ->getQuery()
            ->getResult();
    }

    public function removeByEndpoint(string $endpoint): void
    {
        $this->createQueryBuilder('ps')
            ->delete()
            ->where('ps.endpoint = :endpoint')
            ->setParameter('endpoint', $endpoint)
            ->getQuery()
            ->execute();
    }
}
