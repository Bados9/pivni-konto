<?php

namespace App\Tests\Functional\Repository;

use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Repository\BeerEntryRepository;
use App\Tests\Functional\Api\ApiTestCase;

class BeerEntryRepositoryTest extends ApiTestCase
{
    private BeerEntryRepository $repository;
    private \DateTimeImmutable $dayStart;
    private \DateTimeImmutable $dayEnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(BeerEntryRepository::class);
        $this->dayStart = new \DateTimeImmutable('2026-08-25 05:00');
        $this->dayEnd = new \DateTimeImmutable('2026-08-26 05:00');
    }

    /**
     * @param User[] $users
     */
    private function createGroupWithMembers(array $users): Group
    {
        $group = new Group();
        $group->setName('Award Group');
        $group->setCreatedBy($users[0]);
        $this->entityManager->persist($group);

        foreach ($users as $index => $user) {
            $member = new GroupMember();
            $member->setUser($user);
            $member->setGroup($group);
            $member->setRole($index === 0 ? 'admin' : 'member');
            $this->entityManager->persist($member);
        }

        return $group;
    }

    private function createEntry(User $user, \DateTimeImmutable $consumedAt, int $quantity = 1): void
    {
        $entry = new BeerEntry();
        $entry->setUser($user);
        $entry->setVolumeMl(500);
        $entry->setQuantity($quantity);
        $entry->setCustomBeerName('Test Beer');
        $entry->setConsumedAt($consumedAt);
        $this->entityManager->persist($entry);
    }

    /**
     * @return User[]
     */
    private function createUsers(int $count): array
    {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = $this->createUser(name: 'Drinker ' . $i);
        }

        return $users;
    }

    public function testNoDailyAwardWithFewerThanFiveActiveDrinkers(): void
    {
        $users = $this->createUsers(5);
        $group = $this->createGroupWithMembers($users);

        for ($i = 0; $i < 4; $i++) {
            $this->createEntry($users[$i], $this->dayStart->modify('+10 hours'));
        }
        $this->entityManager->flush();

        $awards = $this->repository->getGroupAwards($group, $this->dayStart, $this->dayEnd);

        $this->assertArrayNotHasKey('drinker_of_day', $awards);
    }

    public function testDailyAwardGoesToHighestScoreWithFiveActiveDrinkers(): void
    {
        $users = $this->createUsers(5);
        $group = $this->createGroupWithMembers($users);

        foreach ($users as $user) {
            $this->createEntry($user, $this->dayStart->modify('+10 hours'));
        }
        $this->createEntry($users[2], $this->dayStart->modify('+12 hours'), 2);
        $this->entityManager->flush();

        $awards = $this->repository->getGroupAwards($group, $this->dayStart, $this->dayEnd);

        $this->assertArrayHasKey('drinker_of_day', $awards);
        $this->assertSame('Drinker 2', $awards['drinker_of_day']['userName']);
        $this->assertSame(3.0, $awards['drinker_of_day']['value']);
    }

    public function testWeeklyAwardAlsoRequiresFiveActiveDrinkers(): void
    {
        $users = $this->createUsers(5);
        $group = $this->createGroupWithMembers($users);

        $weekStart = new \DateTimeImmutable('2026-08-24 05:00');
        $weekEnd = new \DateTimeImmutable('2026-08-31 05:00');

        for ($i = 0; $i < 4; $i++) {
            $this->createEntry($users[$i], $weekStart->modify('+1 day'));
        }
        $this->entityManager->flush();

        $awards = $this->repository->getGroupAwards(
            $group, $this->dayStart, $this->dayEnd, $weekStart, $weekEnd
        );

        $this->assertArrayNotHasKey('drinker_of_week', $awards);
    }

    public function testTieIsBrokenByEarlierLastEntry(): void
    {
        $users = $this->createUsers(5);
        $group = $this->createGroupWithMembers($users);

        // users 0 and 1 tie with 2 beers; user 0 finished drinking earlier
        $this->createEntry($users[0], $this->dayStart->modify('+8 hours'));
        $this->createEntry($users[0], $this->dayStart->modify('+13 hours'));
        $this->createEntry($users[1], $this->dayStart->modify('+8 hours'));
        $this->createEntry($users[1], $this->dayStart->modify('+15 hours'));

        for ($i = 2; $i < 5; $i++) {
            $this->createEntry($users[$i], $this->dayStart->modify('+10 hours'));
        }
        $this->entityManager->flush();

        $awards = $this->repository->getGroupAwards($group, $this->dayStart, $this->dayEnd);

        $this->assertArrayHasKey('drinker_of_day', $awards);
        $this->assertSame('Drinker 0', $awards['drinker_of_day']['userName']);
    }
}
