<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\BeerEntry;
use App\Entity\User;
use App\Service\DrinkingDayService;
use App\Service\WebPushService;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NotifyStreakRiskCommandTest extends ApiTestCase
{
    private \DateTimeImmutable $dayStart;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var DrinkingDayService $drinkingDayService */
        $drinkingDayService = static::getContainer()->get(DrinkingDayService::class);
        $this->dayStart = $drinkingDayService->getDrinkingDayStart();
    }

    private function addEntry(User $user, \DateTimeImmutable $consumedAt): void
    {
        $entry = new BeerEntry();
        $entry->setUser($user);
        $entry->setVolumeMl(500);
        $entry->setQuantity(1);
        $entry->setConsumedAt($consumedAt);
        $this->entityManager->persist($entry);
    }

    /**
     * Entries on the N-th drinking day before today (always inside that day).
     */
    private function addEntryDaysAgo(User $user, int $daysAgo): void
    {
        $this->addEntry($user, $this->dayStart->modify(sprintf('-%d hours', $daysAgo * 24 - 18)));
    }

    private function runCommand(): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:notify-streak-risk'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }

    public function testRemindsUserWithStreakAtRisk(): void
    {
        $atRisk = $this->createUser();
        $this->addEntryDaysAgo($atRisk, 1);
        $this->addEntryDaysAgo($atRisk, 2);

        $drankToday = $this->createUser();
        $this->addEntryDaysAgo($drankToday, 1);
        $this->addEntryDaysAgo($drankToday, 2);
        $this->addEntry($drankToday, $this->dayStart->modify('+1 hour'));

        $shortStreak = $this->createUser();
        $this->addEntryDaysAgo($shortStreak, 1);

        $optedOut = $this->createUser();
        $this->addEntryDaysAgo($optedOut, 1);
        $this->addEntryDaysAgo($optedOut, 2);
        $optedOut->setNotificationPreferences(['streak' => false]);

        $this->entityManager->flush();

        $webPush = $this->createMock(WebPushService::class);
        $webPush->expects($this->once())
            ->method('sendToUser')
            ->with(
                $atRisk,
                $this->callback(fn (array $payload) => str_contains($payload['title'], '2 dní')),
                'streak',
            );
        static::getContainer()->set(WebPushService::class, $webPush);

        $this->runCommand();
    }

    public function testBrokenStreakGetsNoReminder(): void
    {
        // drank the day before yesterday, skipped yesterday - streak is gone
        $user = $this->createUser();
        $this->addEntryDaysAgo($user, 2);
        $this->addEntryDaysAgo($user, 3);
        $this->entityManager->flush();

        $webPush = $this->createMock(WebPushService::class);
        $webPush->expects($this->never())->method('sendToUser');
        static::getContainer()->set(WebPushService::class, $webPush);

        $this->runCommand();
    }
}
