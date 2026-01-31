<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AchievementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/achievements')]
class AchievementController extends AbstractController
{
    public function __construct(
        private AchievementService $achievementService,
    ) {
    }

    #[Route('/me', name: 'achievements_me', methods: ['GET'])]
    public function myAchievements(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $achievements = $this->achievementService->getUserAchievements($user);

        // Group by category
        $grouped = [];
        foreach ($achievements as $achievement) {
            $category = $achievement['category'];
            $grouped[$category] ??= [];
            $grouped[$category][] = $achievement;
        }

        $summary = $this->achievementService->getAchievementsSummary($user);

        return $this->json([
            'summary' => $summary,
            'achievements' => $achievements,
            'grouped' => $grouped,
        ]);
    }

    #[Route('/summary', name: 'achievements_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->achievementService->getAchievementsSummary($user));
    }
}
