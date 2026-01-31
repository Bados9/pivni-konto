<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['group:read', 'group:detail']]
        ),
        new Patch(security: "object.getCreatedBy() == user"),
        new Delete(security: "object.getCreatedBy() == user"),
    ],
    normalizationContext: ['groups' => ['group:read']],
    denormalizationContext: ['groups' => ['group:write']],
)]
class Group
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['group:read', 'entry:read'])]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['group:read', 'group:write', 'group:create', 'entry:read'])]
    private string $name;

    #[ORM\Column(length: 16, unique: true)]
    #[Groups(['group:read'])]
    private string $inviteCode;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['group:read'])]
    private User $createdBy;

    #[ORM\Column]
    #[Groups(['group:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group', orphanRemoval: true)]
    #[Groups(['group:detail'])]
    private Collection $members;

    #[ORM\OneToMany(targetEntity: BeerEntry::class, mappedBy: 'group', orphanRemoval: true)]
    private Collection $beerEntries;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->inviteCode = $this->generateInviteCode();
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
        $this->beerEntries = new ArrayCollection();
    }

    private function generateInviteCode(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getInviteCode(): string
    {
        return $this->inviteCode;
    }

    public function regenerateInviteCode(): static
    {
        $this->inviteCode = $this->generateInviteCode();
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, GroupMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(GroupMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setGroup($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, BeerEntry>
     */
    public function getBeerEntries(): Collection
    {
        return $this->beerEntries;
    }
}
