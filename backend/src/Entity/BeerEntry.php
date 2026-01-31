<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Repository\BeerEntryRepository;
use App\State\BeerEntryProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BeerEntryRepository::class)]
#[ORM\Index(name: 'idx_user_consumed', columns: ['user_id', 'consumed_at'])]
#[ORM\Index(name: 'idx_group_consumed', columns: ['group_id', 'consumed_at'])]
#[ApiResource(
    operations: [
        new Get(security: "object.getUser() == user"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['entry:create']],
            processor: BeerEntryProcessor::class
        ),
        new Patch(security: "object.getUser() == user"),
        new Delete(security: "object.getUser() == user"),
    ],
    normalizationContext: ['groups' => ['entry:read']],
    denormalizationContext: ['groups' => ['entry:write']],
    order: ['consumedAt' => 'DESC'],
)]
#[ApiFilter(DateFilter::class, properties: ['consumedAt'])]
#[ApiFilter(SearchFilter::class, properties: ['group' => 'exact', 'user' => 'exact'])]
class BeerEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['entry:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'beerEntries')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['entry:read'])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'beerEntries')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['entry:read', 'entry:create'])]
    private ?Group $group = null;

    #[ORM\ManyToOne(targetEntity: Beer::class)]
    #[Groups(['entry:read', 'entry:write', 'entry:create'])]
    private ?Beer $beer = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['entry:read', 'entry:write', 'entry:create'])]
    private ?string $customBeerName = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['entry:read', 'entry:write', 'entry:create'])]
    private int $quantity = 1;

    #[ORM\Column]
    #[Assert\Positive]
    #[Assert\Range(min: 100, max: 2000)]
    #[Groups(['entry:read', 'entry:write', 'entry:create'])]
    private int $volumeMl = 500;

    #[ORM\Column]
    #[Groups(['entry:read', 'entry:write', 'entry:create'])]
    private \DateTimeImmutable $consumedAt;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['entry:read', 'entry:write', 'entry:create'])]
    private ?string $note = null;

    #[ORM\Column]
    #[Groups(['entry:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->consumedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function getBeer(): ?Beer
    {
        return $this->beer;
    }

    public function setBeer(?Beer $beer): static
    {
        $this->beer = $beer;
        return $this;
    }

    public function getCustomBeerName(): ?string
    {
        return $this->customBeerName;
    }

    public function setCustomBeerName(?string $customBeerName): static
    {
        $this->customBeerName = $customBeerName;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getVolumeMl(): int
    {
        return $this->volumeMl;
    }

    public function setVolumeMl(int $volumeMl): static
    {
        $this->volumeMl = $volumeMl;
        return $this;
    }

    public function getConsumedAt(): \DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(\DateTimeImmutable $consumedAt): static
    {
        $this->consumedAt = $consumedAt;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getBeerDisplayName(): string
    {
        if ($this->beer !== null) {
            return $this->beer->getName();
        }
        return $this->customBeerName ?? 'Pivo';
    }
}
