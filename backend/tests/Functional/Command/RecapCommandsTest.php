<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\NotificationRepository;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RecapCommandsTest extends ApiTestCase
{
    private function createGroupWith(array $users): Group
    {
        $group = new Group();
        $group->setName('Recap parta');
        $group->setCreatedBy($users[0]);
        $this->entityManager->persist($group);

        foreach ($users as $i => $user) {
            $member = new GroupMember();
            $member->setUser($user);
            $member->setGroup($group);
            $member->setRole($i === 0 ? 'admin' : 'member');
            $this->entityManager->persist($member);
        }

        return $group;
    }

    private function addEntries(User $user, \DateTimeImmutable $consumedAt, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $entry = new BeerEntry();
            $entry->setUser($user);
            $entry->setVolumeMl(500);
            $entry->setQuantity(1);
            $entry->setConsumedAt($consumedAt->modify('+' . $i . ' minutes'));
            $this->entityManager->persist($entry);
        }
    }

    private function runCommand(string $name): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find($name));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }

    public function testWeeklyRecapWithRanksAndWinner(): void
    {
        $winner = $this->createUser(name: 'Vítěz');
        $runnerUp = $this->createUser(name: 'Druhý');
        $inactive = $this->createUser(name: 'Neaktivní');
        $this->createGroupWith([$winner, $runnerUp, $inactive]);

        // same window the service uses: previous drinking week
        $weekStart = new \DateTimeImmutable(
            (new \DateTimeImmutable('last monday'))->format('Y-m-d') . ' 05:00',
        );
        $this->addEntries($winner, $weekStart->modify('+30 hours'), 3);
        $this->addEntries($runnerUp, $weekStart->modify('+31 hours'), 1);
        // entry before the window - must not produce a recap
        $this->addEntries($inactive, $weekStart->modify('-2 hours'), 1);
        $this->entityManager->flush();

        $this->runCommand('app:send-weekly-recap');

        /** @var NotificationRepository $notifications */
        $notifications = static::getContainer()->get(NotificationRepository::class);

        $winnerRecap = $notifications->findLatestByUser($winner)[0];
        $this->assertSame('recap', $winnerRecap->getType());
        $this->assertStringContainsString('Minulý týden: 3 piv, 1,5 l', $winnerRecap->getMessage());
        $this->assertStringContainsString('1. místo', $winnerRecap->getMessage());
        $this->assertStringContainsString('nejlepší', $winnerRecap->getMessage());

        $runnerUpRecap = $notifications->findLatestByUser($runnerUp)[0];
        $this->assertStringContainsString('2. místo', $runnerUpRecap->getMessage());
        $this->assertStringContainsString('Vítěz', $runnerUpRecap->getMessage());

        $this->assertCount(0, $notifications->findLatestByUser($inactive));
    }

    public function testMonthlyRecapCountsNewAchievements(): void
    {
        $user = $this->createUser();

        $monthStart = new \DateTimeImmutable(
            (new \DateTimeImmutable())->format('Y-m-01') . ' 05:00',
        );
        $from = $monthStart->modify('-1 month');

        $this->addEntries($user, $from->modify('+3 days'), 2);

        $achievement = new UserAchievement();
        $achievement->setUser($user);
        $achievement->setAchievementId('first_beer');
        $achievement->setUnlockedAt($from->modify('+3 days'));
        $this->entityManager->persist($achievement);
        $this->entityManager->flush();

        $this->runCommand('app:send-monthly-recap');

        /** @var NotificationRepository $notifications */
        $notifications = static::getContainer()->get(NotificationRepository::class);

        $recap = $notifications->findLatestByUser($user)[0];
        $this->assertSame('recap', $recap->getType());
        $this->assertStringContainsString('Minulý měsíc: 2 piv, 1 l', $recap->getMessage());
        $this->assertStringContainsString('Nové achievementy: 1', $recap->getMessage());
    }
}
