<?php

namespace App\Controller;

use App\Controller\Trait\UuidValidationTrait;
use App\Entity\User;
use App\Repository\GroupMemberRepository;
use App\Repository\UserRepository;
use App\Service\AchievementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/achievements')]
class AchievementController extends AbstractController
{
    use UuidValidationTrait;

    public function __construct(
        private AchievementService $achievementService,
        private UserRepository $userRepository,
        private GroupMemberRepository $memberRepository,
    ) {
    }

    #[Route('/me', name: 'achievements_me', methods: ['GET'])]
    public function myAchievements(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->buildAchievementsPayload($user));
    }

    #[Route('/summary', name: 'achievements_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->achievementService->getAchievementsSummary($user));
    }

    #[Route('/user/{userId}', name: 'achievements_user', methods: ['GET'])]
    public function userAchievements(string $userId): JsonResponse
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
        if ($currentUser->getId()->toRfc4122() !== $userId
            && !$this->memberRepository->haveSharedGroup($currentUser, $targetUser)
        ) {
            return $this->json(['error' => 'Nemáte oprávnění zobrazit achievementy tohoto uživatele'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->buildAchievementsPayload($targetUser);
        $payload['userId'] = $targetUser->getId()->toRfc4122();
        $payload['userName'] = $targetUser->getName();

        return $this->json($payload);
    }

    private function buildAchievementsPayload(User $user): array
    {
        $achievements = $this->achievementService->getUserAchievements($user);

        $grouped = [];
        foreach ($achievements as $achievement) {
            $category = $achievement['category'];
            $grouped[$category] ??= [];
            $grouped[$category][] = $achievement;
        }

        return [
            'summary' => $this->achievementService->getAchievementsSummary($user),
            'achievements' => $achievements,
            'grouped' => $grouped,
        ];
    }
}
