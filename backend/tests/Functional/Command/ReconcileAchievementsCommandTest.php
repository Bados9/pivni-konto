<?php

namespace App\Tests\Functional\Command;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\UserAchievementRepository;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ReconcileAchievementsCommandTest extends ApiTestCase
{
    private function runReconcile(?string $achievementId = null): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:reconcile-achievements'));
        $tester->execute($achievementId !== null ? ['achievementId' => $achievementId] : []);

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

    public function testFullRunMovesGroupAwardToRightfulWinner(): void
    {
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[] = $this->createUser(name: 'Member ' . $i);
        }

        $group = new Group();
        $group->setName('Award Group');
        $group->setCreatedBy($users[0]);
        $createdAtReflection = new \ReflectionProperty(Group::class, 'createdAt');
        $createdAtReflection->setValue($group, new \DateTimeImmutable('-60 days'));
        $this->entityManager->persist($group);

        foreach ($users as $i => $user) {
            $member = new GroupMember();
            $member->setUser($user);
            $member->setGroup($group);
            $member->setRole($i === 0 ? 'admin' : 'member');
            $this->entityManager->persist($member);
        }

        // 10 days ago everyone drank one beer, user 0 drank three -> rightful winner
        $day = (new \DateTimeImmutable('-10 days'))->format('Y-m-d');
        foreach ($users as $user) {
            $entry = new BeerEntry();
            $entry->setUser($user);
            $entry->setVolumeMl(500);
            $entry->setQuantity($user === $users[0] ? 3 : 1);
            $entry->setConsumedAt(new \DateTimeImmutable($day . ' 14:00'));
            $this->entityManager->persist($entry);
        }

        // but the award sits on user 1 (data changed after the cron ran)
        $wrongAward = new UserAchievement();
        $wrongAward->setUser($users[1]);
        $wrongAward->setAchievementId('drinker_of_day');
        $wrongAward->setUnlockedAt(new \DateTimeImmutable($day . ' 12:00'));
        $this->entityManager->persist($wrongAward);
        $this->entityManager->flush();

        $this->runReconcile()->assertCommandIsSuccessful();

        /** @var UserAchievementRepository $repository */
        $repository = static::getContainer()->get(UserAchievementRepository::class);
        $this->assertTrue($repository->hasAchievement($users[0], 'drinker_of_day'));
        $this->assertFalse($repository->hasAchievement($users[1], 'drinker_of_day'));
    }

    public function testRejectsUnknownAndCronSourcedAchievements(): void
    {
        $this->createUser();

        $this->assertSame(1, $this->runReconcile('nonsense')->getStatusCode());
        $this->assertSame(1, $this->runReconcile('drinker_of_day')->getStatusCode());
    }

    public function testFullRunFixesRepeatableCountsAndKeepsCronRows(): void
    {
        $user = $this->createUser();

        // 2 mornings with an early-bird beer (5-10 AM)
        foreach (['2026-08-27', '2026-08-28'] as $day) {
            $entry = new BeerEntry();
            $entry->setUser($user);
            $entry->setVolumeMl(500);
            $entry->setQuantity(1);
            $entry->setConsumedAt(new \DateTimeImmutable($day . ' 08:00'));
            $this->entityManager->persist($entry);
        }

        // but 5 early_bird rows exist (stale after entry deletions)
        for ($i = 0; $i < 5; $i++) {
            $row = new UserAchievement();
            $row->setUser($user);
            $row->setAchievementId('early_bird');
            $row->setUnlockedAt(new \DateTimeImmutable('2026-08-2' . ($i + 1) . ' 12:00'));
            $this->entityManager->persist($row);
        }

        // cron-granted award must survive untouched
        $award = new UserAchievement();
        $award->setUser($user);
        $award->setAchievementId('drinker_of_day');
        $this->entityManager->persist($award);
        $this->entityManager->flush();

        $tester = $this->runReconcile();
        $tester->assertCommandIsSuccessful();

        /** @var UserAchievementRepository $repository */
        $repository = static::getContainer()->get(UserAchievementRepository::class);
        $counts = $repository->getUnlockedWithCounts($user);

        $this->assertSame(2, $counts['early_bird']);
        $this->assertSame(1, $counts['drinker_of_day']);
        // missing non-repeatable achievements were added by the same run
        $this->assertSame(1, $counts['first_beer']);
    }
}
