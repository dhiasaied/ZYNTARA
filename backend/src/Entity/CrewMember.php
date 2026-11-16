<?php

namespace App\Entity;

use App\Validator\OwnedByCurrentUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** One seat in a crew: an agent plus the role it plays (researcher, reviewer, coder...). */
#[ORM\Entity]
#[ORM\Table(name: 'crew_members')]
class CrewMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['crew:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Crew $crew = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[OwnedByCurrentUser]
    #[Groups(['crew:read', 'crew:write'])]
    private ?Agent $agent = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Groups(['crew:read', 'crew:write'])]
    private string $role = '';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['crew:read', 'crew:write'])]
    private int $position = 0;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCrew(): ?Crew
    {
        return $this->crew;
    }

    public function setCrew(?Crew $crew): static
    {
        $this->crew = $crew;

        return $this;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = trim($role);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
