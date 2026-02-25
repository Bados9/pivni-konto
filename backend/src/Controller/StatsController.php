<?php

namespace App\Controller;

use App\Controller\Trait\UuidValidationTrait;
use App\Entity\User;
use App\Repository\BeerEntryRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Service\DrinkingDayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use Symfony\Component\Uid\Uuid;

#[Route('/api/stats')]
class StatsController extends AbstractController
{
    use UuidValidationTrait;
    public function __construct(
        private BeerEntryRepository $entryRepository,
        private GroupRepository $groupRepository,
        private GroupMemberRepository $memberRepository,
        private UserRepository $userRepository,
        private DrinkingDayService $drinkingDayService,
    ) {
    }

    #[Route('/me', name: 'stats_me', methods: ['GET'])]
    public function myStats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();
        $weekStart = new \DateTimeImmutable('monday this week 05:00');
        $monthStart = new \DateTimeImmutable('first day of this month 05:00');
        $yearStart = new \DateTimeImmutable('first day of january this year 05:00');
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');

        $todayCount = $this->entryRepository->countByUserInPeriod($user, $dayStart, $dayEnd);
        $weekCount = $this->entryRepository->countByUserInPeriod($user, $weekStart, $dayEnd);
        $monthCount = $this->entryRepository->countByUserInPeriod($user, $monthStart, $dayEnd);
        $yearCount = $this->entryRepository->countByUserInPeriod($user, $yearStart, $dayEnd);

        $totals = $this->entryRepository->getTotalStatsByUser($user);

        $todayEntries = $this->entryRepository->findTodayByUser($user);
        $recentEntries = [];
        foreach ($todayEntries as $entry) {
            $recentEntries[] = [
                'id' => $entry->getId()->toRfc4122(),
                'beerName' => $entry->getBeerDisplayName(),
                'quantity' => $entry->getQuantity(),
                'volumeMl' => $entry->getVolumeMl(),
                'consumedAt' => $entry->getConsumedAt()->format('c'),
            ];
        }

        // Extended stats for personal view
        $dailyCounts = $this->entryRepository->getDailyCountsByUser($user, $thirtyDaysAgo, $dayEnd);
        $topBeers = $this->entryRepository->getTopBeersByUser($user);
        $topBreweries = $this->entryRepository->getTopBreweriesByUser($user);
        $currentStreak = $this->entryRepository->getCurrentStreakByUser($user);
        $averagePerDay = $this->entryRepository->getAveragePerDayByUser($user);

        return $this->json([
            'today' => $todayCount,
            'thisWeek' => $weekCount,
            'thisMonth' => $monthCount,
            'thisYear' => $yearCount,
            'totalBeers' => $totals['totalBeers'],
            'totalVolume' => $totals['totalVolume'],
            'todayEntries' => $recentEntries,
            'dailyCounts' => $dailyCounts,
            'topBeers' => $topBeers,
            'topBreweries' => $topBreweries,
            'currentStreak' => $currentStreak,
            'averagePerDay' => $averagePerDay,
        ]);
    }

    #[Route('/user/{userId}', name: 'stats_user', methods: ['GET'])]
    public function userStats(string $userId): JsonResponse
    {
        $uuid = $this->parseUuid($userId);
        if ($uuid === null) {
            return $this->invalidUuidResponse();
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $targetUser = $this->userRepository->find($uuid);
        if ($targetUser === null) {
            return $this->json(['error' => 'Uživatel nenalezen'], Response::HTTP_NOT_FOUND);
        }

        // Check authorization: users must share at least one group
        if ($currentUser->getId()->toRfc4122() !== $userId) {
            $sharedGroup = $this->findSharedGroup($currentUser, $targetUser);
            if ($sharedGroup === null) {
                return $this->json(['error' => 'Nemáte oprávnění zobrazit statistiky tohoto uživatele'], Response::HTTP_FORBIDDEN);
            }
        }

        $dayStart = $this->drinkingDayService->getDrinkingDayStart();
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();
        $weekStart = new \DateTimeImmutable('monday this week 05:00');
        $monthStart = new \DateTimeImmutable('first day of this month 05:00');
        $yearStart = new \DateTimeImmutable('first day of january this year 05:00');

        $todayCount = $this->entryRepository->countByUserInPeriod($targetUser, $dayStart, $dayEnd);
        $weekCount = $this->entryRepository->countByUserInPeriod($targetUser, $weekStart, $dayEnd);
        $monthCount = $this->entryRepository->countByUserInPeriod($targetUser, $monthStart, $dayEnd);
        $yearCount = $this->entryRepository->countByUserInPeriod($targetUser, $yearStart, $dayEnd);
        $totals = $this->entryRepository->getTotalStatsByUser($targetUser);

        return $this->json([
            'userId' => $targetUser->getId()->toRfc4122(),
            'userName' => $targetUser->getName(),
            'today' => $todayCount,
            'thisWeek' => $weekCount,
            'thisMonth' => $monthCount,
            'thisYear' => $yearCount,
            'totalBeers' => $totals['totalBeers'],
            'totalVolume' => $totals['totalVolume'],
        ]);
    }

    private function findSharedGroup(User $user1, User $user2): ?object
    {
        $em = $this->entryRepository->getEntityManager();

        $result = $em->createQueryBuilder()
            ->select('g')
            ->from('App\Entity\Group', 'g')
            ->innerJoin('App\Entity\GroupMember', 'gm1', 'WITH', 'gm1.group = g AND gm1.user = :user1')
            ->innerJoin('App\Entity\GroupMember', 'gm2', 'WITH', 'gm2.group = g AND gm2.user = :user2')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    #[Route('/leaderboard/{groupId}', name: 'stats_leaderboard', methods: ['GET'])]
    public function leaderboard(string $groupId, Request $request): JsonResponse
    {
        $uuid = $this->parseUuid($groupId);
        if ($uuid === null) {
            return $this->invalidUuidResponse();
        }

        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->find($uuid);
        if ($group === null) {
            return $this->json(['error' => 'Skupina nenalezena'], Response::HTTP_NOT_FOUND);
        }

        $isMember = $this->memberRepository->isMember($user, $group);
        if (!$isMember) {
            return $this->json(['error' => 'Nejste členem této skupiny'], Response::HTTP_FORBIDDEN);
        }

        $period = $request->query->get('period', 'week');
        $dayEnd = $this->drinkingDayService->getDrinkingDayEnd();

        $periodStart = match ($period) {
            'today' => $this->drinkingDayService->getDrinkingDayStart(),
            'month' => new \DateTimeImmutable('first day of this month 05:00'),
            'year' => new \DateTimeImmutable('first day of january this year 05:00'),
            default => new \DateTimeImmutable('monday this week 05:00'),
        };

        $leaderboard = $this->entryRepository->getLeaderboardWithAllMembers($group, $periodStart, $dayEnd);

        return $this->json([
            'group' => [
                'id' => $group->getId()->toRfc4122(),
                'name' => $group->getName(),
            ],
            'period' => $period,
            'leaderboard' => $leaderboard,
        ]);
    }
}
