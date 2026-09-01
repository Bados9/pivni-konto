<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupMember>
 */
class GroupMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupMember::class);
    }

    public function findMembership(User $user, Group $group): ?GroupMember
    {
        return $this->findOneBy(['user' => $user, 'group' => $group]);
    }

    public function haveSharedGroup(User $user1, User $user2): bool
    {
        $count = (int) $this->createQueryBuilder('gm1')
            ->select('COUNT(gm1.id)')
            ->innerJoin('App\Entity\GroupMember', 'gm2', 'WITH', 'gm2.group = gm1.group AND gm2.user = :user2')
            ->where('gm1.user = :user1')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function isMember(User $user, Group $group): bool
    {
        return $this->findMembership($user, $group) !== null;
    }
}
