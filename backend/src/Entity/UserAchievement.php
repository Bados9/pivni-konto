<?php

namespace App\Entity;

use App\Repository\UserAchievementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_achievement', columns: ['user_id', 'achievement_id'])]
class UserAchievement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $achievementId;

    #[ORM\Column]
    private \DateTimeImmutable $unlockedAt;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $timesUnlocked = 1;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->unlockedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAchievementId(): string
    {
        return $this->achievementId;
    }

    public function setAchievementId(string $achievementId): static
    {
        $this->achievementId = $achievementId;
        return $this;
    }

    public function getUnlockedAt(): \DateTimeImmutable
    {
        return $this->unlockedAt;
    }

    public function setUnlockedAt(\DateTimeImmutable $unlockedAt): static
    {
        $this->unlockedAt = $unlockedAt;
        return $this;
    }

    public function getTimesUnlocked(): int
    {
        return $this->timesUnlocked;
    }

    public function setTimesUnlocked(int $timesUnlocked): static
    {
        $this->timesUnlocked = $timesUnlocked;
        return $this;
    }

    public function incrementTimesUnlocked(): static
    {
        $this->timesUnlocked++;
        $this->unlockedAt = new \DateTimeImmutable();
        return $this;
    }
}
