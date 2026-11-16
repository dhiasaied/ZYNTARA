<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\OwnerProcessor;
use App\Enum\AgentLevel;
use App\Enum\CrewStrategy;
use App\Repository\CrewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** Several agents collaborating on one goal: the AGENTIC rung of the ladder. */
#[ORM\Entity(repositoryClass: CrewRepository::class)]
#[ORM\Table(name: 'crews')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: 'object.getOwner() == user'),
        new Post(processor: OwnerProcessor::class),
        new Patch(security: 'object.getOwner() == user'),
        new Delete(security: 'object.getOwner() == user'),
    ],
    normalizationContext: ['groups' => ['crew:read']],
    denormalizationContext: ['groups' => ['crew:write']],
    order: ['updatedAt' => 'DESC'],
    security: "is_granted('ROLE_USER')",
)]
class Crew implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['crew:read', 'run:read'])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['crew:read', 'crew:write', 'run:read'])]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['crew:read', 'crew:write'])]
    private ?string $description = null;

    /** The shared objective the supervisor drives members towards. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    #[Groups(['crew:read', 'crew:write'])]
    private string $goal = '';

    #[ORM\Column(length: 16, enumType: CrewStrategy::class)]
    #[Groups(['crew:read', 'crew:write'])]
    private CrewStrategy $strategy = CrewStrategy::Supervisor;

    #[ORM\Column(length: 32)]
    #[Assert\Choice(choices: Agent::PROVIDERS)]
    #[Groups(['crew:read', 'crew:write'])]
    private string $supervisorProvider = 'mock';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['crew:read', 'crew:write'])]
    private string $supervisorModel = 'zyntara-mock-1';

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 20)]
    #[Groups(['crew:read', 'crew:write'])]
    private int $maxRounds = 6;

    /** @var Collection<int, CrewMember> */
    #[ORM\OneToMany(targetEntity: CrewMember::class, mappedBy: 'crew', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Assert\Count(min: 2, max: 8, minMessage: 'Agentic AI needs at least two agents working together.')]
    #[Assert\Valid]
    #[Groups(['crew:read', 'crew:write'])]
    private Collection $members;

    #[ORM\Column]
    #[Groups(['crew:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['crew:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->members = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Groups(['crew:read'])]
    public function getLevel(): AgentLevel
    {
        return AgentLevel::Agentic;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getGoal(): string
    {
        return $this->goal;
    }

    public function setGoal(string $goal): static
    {
        $this->goal = $goal;

        return $this;
    }

    public function getStrategy(): CrewStrategy
    {
        return $this->strategy;
    }

    public function setStrategy(CrewStrategy $strategy): static
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function getSupervisorProvider(): string
    {
        return $this->supervisorProvider;
    }

    public function setSupervisorProvider(string $supervisorProvider): static
    {
        $this->supervisorProvider = $supervisorProvider;

        return $this;
    }

    public function getSupervisorModel(): string
    {
        return $this->supervisorModel;
    }

    public function setSupervisorModel(string $supervisorModel): static
    {
        $this->supervisorModel = $supervisorModel;

        return $this;
    }

    public function getMaxRounds(): int
    {
        return $this->maxRounds;
    }

    public function setMaxRounds(int $maxRounds): static
    {
        $this->maxRounds = $maxRounds;

        return $this;
    }

    /** @return Collection<int, CrewMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(CrewMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setCrew($this);
        }

        return $this;
    }

    public function removeMember(CrewMember $member): static
    {
        $this->members->removeElement($member);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
