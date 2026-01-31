<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\GroupMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GroupMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_group_member', columns: ['group_id', 'user_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/groups/{groupId}/members',
            uriVariables: [
                'groupId' => new Link(toProperty: 'group', fromClass: Group::class),
            ],
        ),
        new Delete(security: "object.getUser() == user or object.getGroup().getCreatedBy() == user"),
    ],
    normalizationContext: ['groups' => ['member:read']],
)]
class GroupMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['member:read', 'group:detail'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'groupMemberships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['member:read', 'group:detail'])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false)]
    private Group $group;

    #[ORM\Column(length: 20)]
    #[Groups(['member:read', 'group:detail'])]
    private string $role = 'member';

    #[ORM\Column]
    #[Groups(['member:read', 'group:detail'])]
    private \DateTimeImmutable $joinedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->joinedAt = new \DateTimeImmutable();
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

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
