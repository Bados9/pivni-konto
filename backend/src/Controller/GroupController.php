<?php

namespace App\Controller;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class GroupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GroupRepository $groupRepository,
        private GroupMemberRepository $groupMemberRepository,
        private RateLimiterFactory $groupJoinLimiter,
    ) {
    }

    #[Route('/groups/my', name: 'groups_my', methods: ['GET'], priority: 10)]
    public function myGroups(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $groups = $this->groupRepository->findByUser($user);

        $result = [];
        foreach ($groups as $group) {
            $result[] = [
                'id' => $group->getId()->toRfc4122(),
                'name' => $group->getName(),
                'inviteCode' => $group->getInviteCode(),
                'memberCount' => $group->getMembers()->count(),
                'createdAt' => $group->getCreatedAt()->format('c'),
            ];
        }

        return $this->json($result);
    }

    #[Route('/groups/create', name: 'groups_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? null;
        if (!$name) {
            return $this->json(['error' => 'Název skupiny je povinný'], Response::HTTP_BAD_REQUEST);
        }

        $group = new Group();
        $group->setName($name);
        $group->setCreatedBy($user);

        $member = new GroupMember();
        $member->setUser($user);
        $member->setGroup($group);
        $member->setRole(GroupRole::ADMIN->value);

        $this->entityManager->persist($group);
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $this->json([
            'id' => $group->getId()->toRfc4122(),
            'name' => $group->getName(),
            'inviteCode' => $group->getInviteCode(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/groups/join', name: 'groups_join', methods: ['POST'])]
    public function join(Request $request): JsonResponse
    {
        // Rate limiting - ochrana proti brute-force hádání invite kódů
        $limiter = $this->groupJoinLimiter->create($request->getClientIp());
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            return $this->json([
                'error' => 'Příliš mnoho pokusů. Zkuste to později.',
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
                'X-RateLimit-Retry-After' => $limit->getRetryAfter()->getTimestamp(),
            ]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $code = $data['code'] ?? null;
        if (!$code) {
            return $this->json(['error' => 'Kód je povinný'], Response::HTTP_BAD_REQUEST);
        }

        $group = $this->groupRepository->findByInviteCode($code);
        if ($group === null) {
            return $this->json(['error' => 'Skupina s tímto kódem neexistuje'], Response::HTTP_NOT_FOUND);
        }

        $existingMembership = $this->groupMemberRepository->findMembership($user, $group);
        if ($existingMembership !== null) {
            return $this->json(['error' => 'Již jste členem této skupiny'], Response::HTTP_CONFLICT);
        }

        $member = new GroupMember();
        $member->setUser($user);
        $member->setGroup($group);
        $member->setRole(GroupRole::MEMBER->value);

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Úspěšně jste se připojili do skupiny',
            'group' => [
                'id' => $group->getId()->toRfc4122(),
                'name' => $group->getName(),
            ],
        ]);
    }
}
