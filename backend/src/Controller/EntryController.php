<?php

namespace App\Controller;

use App\Controller\Trait\UuidValidationTrait;
use App\Entity\BeerEntry;
use App\Entity\User;
use App\Repository\BeerEntryRepository;
use App\Repository\BeerRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Service\AchievementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/entries')]
class EntryController extends AbstractController
{
    use UuidValidationTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BeerEntryRepository $entryRepository,
        private GroupRepository $groupRepository,
        private GroupMemberRepository $memberRepository,
        private BeerRepository $beerRepository,
        private AchievementService $achievementService,
    ) {
    }

    #[Route('/quick-add', name: 'entries_quick_add', methods: ['POST'])]
    public function quickAdd(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $entry = new BeerEntry();
        $entry->setUser($user);

        $groupId = $data['groupId'] ?? null;
        if ($groupId) {
            $groupUuid = $this->parseUuid($groupId);
            if ($groupUuid !== null) {
                $group = $this->groupRepository->find($groupUuid);
                if ($group !== null) {
                    $isMember = $this->memberRepository->isMember($user, $group);
                    if ($isMember) {
                        $entry->setGroup($group);
                    }
                }
            }
        }

        $beerId = $data['beerId'] ?? null;
        if ($beerId) {
            $beerUuid = $this->parseUuid($beerId);
            if ($beerUuid !== null) {
                $beer = $this->beerRepository->find($beerUuid);
                if ($beer !== null) {
                    $entry->setBeer($beer);
                }
            }
        }

        $customBeerName = $data['customBeerName'] ?? null;
        if ($customBeerName) {
            $entry->setCustomBeerName($customBeerName);
        }

        $quantity = $data['quantity'] ?? 1;
        $entry->setQuantity((int) $quantity);

        $volumeMl = $data['volumeMl'] ?? 500;
        $entry->setVolumeMl((int) $volumeMl);

        $consumedAt = $data['consumedAt'] ?? null;
        if ($consumedAt) {
            $entry->setConsumedAt(new \DateTimeImmutable($consumedAt));
        }

        $note = $data['note'] ?? null;
        if ($note) {
            $entry->setNote($note);
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        // Check for newly unlocked achievements
        $newAchievements = $this->achievementService->checkAndUnlockAchievements($user);

        $response = [
            'id' => $entry->getId()->toRfc4122(),
            'beerName' => $entry->getBeerDisplayName(),
            'quantity' => $entry->getQuantity(),
            'volumeMl' => $entry->getVolumeMl(),
            'consumedAt' => $entry->getConsumedAt()->format('c'),
            'newAchievements' => $newAchievements,
        ];

        $group = $entry->getGroup();
        if ($group !== null) {
            $response['group'] = [
                'id' => $group->getId()->toRfc4122(),
                'name' => $group->getName(),
            ];
        }

        return $this->json($response, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'entries_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $uuid = $this->parseUuid($id);
        if ($uuid === null) {
            return $this->invalidUuidResponse();
        }

        /** @var User $user */
        $user = $this->getUser();

        $entry = $this->entryRepository->find($uuid);
        if ($entry === null) {
            return $this->json(['error' => 'Záznam nenalezen'], Response::HTTP_NOT_FOUND);
        }

        if ($entry->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return $this->json(['error' => 'Nemáte oprávnění smazat tento záznam'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        return $this->json(['message' => 'Záznam smazán']);
    }
}
