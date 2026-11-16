<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\OwnerProcessor;
use App\Enum\AgentLevel;
use App\Repository\AgentRepository;
use App\Service\ToolCatalog;
use App\Validator\OwnedByCurrentUser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single AI application / agent. Depending on its configuration it sits on the
 * APP, TOOLS, RAG or AGENT rung of the ladder (see getLevel()).
 */
#[ORM\Entity(repositoryClass: AgentRepository::class)]
#[ORM\Table(name: 'agents')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: 'object.getOwner() == user'),
        new Post(processor: OwnerProcessor::class),
        new Patch(security: 'object.getOwner() == user'),
        new Delete(security: 'object.getOwner() == user'),
    ],
    normalizationContext: ['groups' => ['agent:read']],
    denormalizationContext: ['groups' => ['agent:write']],
    order: ['updatedAt' => 'DESC'],
    security: "is_granted('ROLE_USER')",
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'ipartial', 'vertical' => 'exact', 'provider' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt', 'updatedAt'])]
class Agent implements OwnedInterface
{
    public const PROVIDERS = ['mock', 'openai', 'anthropic', 'ollama'];
    public const VERTICALS = ['generic', 'ecommerce', 'education', 'support', 'email', 'research'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['agent:read', 'crew:read', 'run:read', 'conversation:read'])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agent:read', 'agent:write', 'crew:read', 'run:read', 'conversation:read'])]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['agent:read', 'agent:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 32)]
    #[Assert\Choice(choices: self::VERTICALS)]
    #[Groups(['agent:read', 'agent:write', 'crew:read'])]
    private string $vertical = 'generic';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Every AI app starts with a prompt.')]
    #[Assert\Length(max: 20000)]
    #[Groups(['agent:read', 'agent:write'])]
    private string $systemPrompt = '';

    #[ORM\Column(length: 32)]
    #[Assert\Choice(choices: self::PROVIDERS)]
    #[Groups(['agent:read', 'agent:write', 'crew:read'])]
    private string $provider = 'mock';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agent:read', 'agent:write', 'crew:read'])]
    private string $model = 'zyntara-mock-1';

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 2)]
    #[Groups(['agent:read', 'agent:write'])]
    private float $temperature = 0.3;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Choice(choices: ToolCatalog::KEYS)])]
    #[Assert\Count(max: 10)]
    #[Groups(['agent:read', 'agent:write', 'crew:read'])]
    private array $tools = [];

    /** @var Collection<int, KnowledgeBase> */
    #[ORM\ManyToMany(targetEntity: KnowledgeBase::class)]
    #[ORM\JoinTable(name: 'agent_knowledge_bases')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[Assert\All([new OwnedByCurrentUser()])]
    #[Groups(['agent:read', 'agent:write'])]
    private Collection $knowledgeBases;

    /** Autonomous agents plan, loop over tools and reflect until the goal is met. */
    #[ORM\Column]
    #[Groups(['agent:read', 'agent:write', 'crew:read'])]
    private bool $autonomous = false;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 25)]
    #[Groups(['agent:read', 'agent:write'])]
    private int $maxIterations = 6;

    #[ORM\Column]
    #[Groups(['agent:read', 'agent:write'])]
    private bool $memoryEnabled = false;

    #[ORM\Column]
    #[Groups(['agent:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['agent:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->knowledgeBases = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Which rung of the ladder this agent occupies. */
    #[Groups(['agent:read', 'crew:read', 'run:read'])]
    public function getLevel(): AgentLevel
    {
        if ($this->autonomous) {
            return AgentLevel::Agent;
        }
        if (!$this->knowledgeBases->isEmpty()) {
            return AgentLevel::Rag;
        }
        if ([] !== $this->tools) {
            return AgentLevel::Tools;
        }

        return AgentLevel::App;
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

    public function getVertical(): string
    {
        return $this->vertical;
    }

    public function setVertical(string $vertical): static
    {
        $this->vertical = $vertical;

        return $this;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(string $systemPrompt): static
    {
        $this->systemPrompt = $systemPrompt;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    /** @return list<string> */
    public function getTools(): array
    {
        return $this->tools;
    }

    /** @param list<string> $tools */
    public function setTools(array $tools): static
    {
        $this->tools = array_values(array_unique($tools));

        return $this;
    }

    /** @return Collection<int, KnowledgeBase> */
    public function getKnowledgeBases(): Collection
    {
        return $this->knowledgeBases;
    }

    public function addKnowledgeBase(KnowledgeBase $knowledgeBase): static
    {
        if (!$this->knowledgeBases->contains($knowledgeBase)) {
            $this->knowledgeBases->add($knowledgeBase);
        }

        return $this;
    }

    public function removeKnowledgeBase(KnowledgeBase $knowledgeBase): static
    {
        $this->knowledgeBases->removeElement($knowledgeBase);

        return $this;
    }

    public function isAutonomous(): bool
    {
        return $this->autonomous;
    }

    public function setAutonomous(bool $autonomous): static
    {
        $this->autonomous = $autonomous;

        return $this;
    }

    public function getMaxIterations(): int
    {
        return $this->maxIterations;
    }

    public function setMaxIterations(int $maxIterations): static
    {
        $this->maxIterations = $maxIterations;

        return $this;
    }

    public function isMemoryEnabled(): bool
    {
        return $this->memoryEnabled;
    }

    public function setMemoryEnabled(bool $memoryEnabled): static
    {
        $this->memoryEnabled = $memoryEnabled;

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
