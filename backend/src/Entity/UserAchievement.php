<?php

namespace App\Entity;

use App\Repository\UserAchievementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\Index(columns: ['user_id', 'achievement_id'], name: 'idx_ua_user_achievement')]
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
}
