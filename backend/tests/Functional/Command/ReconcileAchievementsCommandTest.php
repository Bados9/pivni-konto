<?php

namespace App\Tests\Functional\Command;

use App\Entity\BeerEntry;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\UserAchievementRepository;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ReconcileAchievementsCommandTest extends ApiTestCase
{
    private function runReconcile(string $achievementId): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:reconcile-achievements'));
        $tester->execute(['achievementId' => $achievementId]);

        return $tester;
    }

    private function createWeekendEntries(User $user, int $count, string $saturday): void
    {
        for ($i = 0; $i < $count; $i++) {
            $entry = new BeerEntry();
            $entry->setUser($user);
            $entry->setVolumeMl(500);
            $entry->setQuantity(1);
            $entry->setConsumedAt((new \DateTimeImmutable($saturday . ' 12:00'))->modify('+' . $i . ' minutes'));
            $this->entityManager->persist($entry);
        }
    }

    public function testUnlocksMissingAndRemovesUndeserved(): void
    {
        // 2026-08-29 is a Saturday
        $deserving = $this->createUser();
        $this->createWeekendEntries($deserving, 25, '2026-08-29');

        $undeserving = $this->createUser();
        $this->createWeekendEntries($undeserving, 5, '2026-08-29');
        $oldRow = new UserAchievement();
        $oldRow->setUser($undeserving);
        $oldRow->setAchievementId('weekend_warrior');
        $this->entityManager->persist($oldRow);
        $this->entityManager->flush();

        $tester = $this->runReconcile('weekend_warrior');
        $tester->assertCommandIsSuccessful();

        /** @var UserAchievementRepository $repository */
        $repository = static::getContainer()->get(UserAchievementRepository::class);
        $this->assertTrue($repository->hasAchievement($deserving, 'weekend_warrior'));
        $this->assertFalse($repository->hasAchievement($undeserving, 'weekend_warrior'));
    }

    public function testRejectsUnknownAndRepeatableAchievements(): void
    {
        $this->createUser();

        $this->assertSame(1, $this->runReconcile('nonsense')->getStatusCode());
        $this->assertSame(1, $this->runReconcile('drinker_of_day')->getStatusCode());
    }
}
