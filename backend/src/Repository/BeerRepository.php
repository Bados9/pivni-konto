<?php

namespace App\Repository;

use App\Entity\Beer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Beer>
 */
class BeerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Beer::class);
    }

    /**
     * @return Beer[]
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('b')
            ->where('LOWER(b.name) LIKE LOWER(:query)')
            ->orWhere('LOWER(b.brewery) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('b.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByNameCaseInsensitive(string $name): ?Beer
    {
        return $this->createQueryBuilder('b')
            ->where('LOWER(b.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countPendingByUser(object $user): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.submittedBy = :user')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
