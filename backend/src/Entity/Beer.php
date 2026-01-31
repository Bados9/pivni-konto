<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Repository\BeerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BeerRepository::class)]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            paginationEnabled: true,
            paginationItemsPerPage: 50
        ),
        new Post(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['beer:read']],
    denormalizationContext: ['groups' => ['beer:write']],
    order: ['name' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'brewery' => 'partial', 'style' => 'partial'])]
class Beer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['beer:read', 'entry:read'])]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[Groups(['beer:read', 'beer:write', 'entry:read'])]
    private string $name;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['beer:read', 'beer:write', 'entry:read'])]
    private ?string $brewery = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['beer:read', 'beer:write'])]
    private ?string $style = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 30)]
    #[Groups(['beer:read', 'beer:write', 'entry:read'])]
    private ?float $abv = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['beer:read', 'beer:write'])]
    private ?string $logo = null;

    #[ORM\Column]
    #[Groups(['beer:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getBrewery(): ?string
    {
        return $this->brewery;
    }

    public function setBrewery(?string $brewery): static
    {
        $this->brewery = $brewery;
        return $this;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function setStyle(?string $style): static
    {
        $this->style = $style;
        return $this;
    }

    public function getAbv(): ?float
    {
        return $this->abv;
    }

    public function setAbv(?float $abv): static
    {
        $this->abv = $abv;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
