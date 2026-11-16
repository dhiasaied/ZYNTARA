<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\RunCreateProcessor;
use App\Enum\RunKind;
use App\Enum\RunStatus;
use App\Repository\RunRepository;
use App\Validator\OwnedByCurrentUser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One execution on the ladder: a raw prompt (playground), a single agent, or a crew.
 * Executed asynchronously by the Messenger worker via the LangGraph agents service.
 */
#[ORM\Entity(repositoryClass: RunRepository::class)]
#[ORM\Table(name: 'runs')]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['created_at'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(normalizationContext: ['groups' => ['run:read', 'run:item']], security: 'object.getOwner() == user'),
        new Post(processor: RunCreateProcessor::class),
    ],
    normalizationContext: ['groups' => ['run:read']],
    denormalizationContext: ['groups' => ['run:write']],
    order: ['createdAt' => 'DESC'],
    paginationItemsPerPage: 20,
    security: "is_granted('ROLE_USER')",
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact', 'kind' => 'exact', 'agent' => 'exact', 'crew' => 'exact', 'conversation' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'durationMs'])]
class Run implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['run:read'])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 16, enumType: RunKind::class)]
    #[Assert\NotNull]
    #[Groups(['run:read', 'run:write'])]
    private RunKind $kind = RunKind::Prompt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[OwnedByCurrentUser]
    #[Groups(['run:read', 'run:write'])]
    private ?Agent $agent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[OwnedByCurrentUser]
    #[Groups(['run:read', 'run:write'])]
    private ?Crew $crew = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['run:read'])]
    private ?Conversation $conversation = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20000)]
    #[Groups(['run:read', 'run:write'])]
    private string $input = '';

    /** Only used by prompt runs (playground); agents and crews carry their own model. */
    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Choice(choices: Agent::PROVIDERS)]
    #[Groups(['run:read', 'run:write'])]
    private ?string $provider = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['run:read', 'run:write'])]
    private ?string $model = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 2)]
    #[Groups(['run:read', 'run:write'])]
    private ?float $temperature = null;

    #[ORM\Column(length: 16, enumType: RunStatus::class)]
    #[Groups(['run:read'])]
    private RunStatus $status = RunStatus::Queued;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['run:read'])]
    private ?string $output = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['run:read'])]
    private ?string $error = null;

    #[ORM\Column]
    #[Groups(['run:read'])]
    private int $tokensIn = 0;

    #[ORM\Column]
    #[Groups(['run:read'])]
    private int $tokensOut = 0;

    #[ORM\Column(nullable: true)]
    #[Groups(['run:read'])]
    private ?int $durationMs = null;

    /** @var list<string> tool keys actually invoked during the run */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['run:read'])]
    private array $toolsUsed = [];

    /** @var Collection<int, RunStep> */
    #[ORM\OneToMany(targetEntity: RunStep::class, mappedBy: 'run', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    #[Groups(['run:item'])]
    private Collection $steps;

    #[ORM\Column]
    #[Groups(['run:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['run:read'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['run:read'])]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->steps = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        if (RunKind::Agent === $this->kind && null === $this->agent) {
            $context->buildViolation('An agent run needs an agent.')->atPath('agent')->addViolation();
        }
        if (RunKind::Crew === $this->kind && null === $this->crew) {
            $context->buildViolation('A crew run needs a crew.')->atPath('crew')->addViolation();
        }
        if (RunKind::Prompt === $this->kind && (null === $this->provider || null === $this->model || '' === $this->model)) {
            $context->buildViolation('A prompt run needs a provider and a model.')->atPath('model')->addViolation();
        }
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

    public function getKind(): RunKind
    {
        return $this->kind;
    }

    public function setKind(RunKind $kind): static
    {
        $this->kind = $kind;

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

    public function getCrew(): ?Crew
    {
        return $this->crew;
    }

    public function setCrew(?Crew $crew): static
    {
        $this->crew = $crew;

        return $this;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function setInput(string $input): static
    {
        $this->input = $input;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(?float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function getStatus(): RunStatus
    {
        return $this->status;
    }

    public function markRunning(): static
    {
        $this->status = RunStatus::Running;
        $this->startedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @param list<string> $toolsUsed */
    public function markSucceeded(string $output, int $tokensIn, int $tokensOut, array $toolsUsed = []): static
    {
        $this->status = RunStatus::Succeeded;
        $this->output = $output;
        $this->tokensIn = $tokensIn;
        $this->tokensOut = $tokensOut;
        $this->toolsUsed = array_values(array_unique($toolsUsed));
        $this->finish();

        return $this;
    }

    public function markFailed(string $error): static
    {
        $this->status = RunStatus::Failed;
        $this->error = mb_substr($error, 0, 4000);
        $this->finish();

        return $this;
    }

    public function markCancelled(): static
    {
        $this->status = RunStatus::Cancelled;
        $this->finish();

        return $this;
    }

    private function finish(): void
    {
        $this->finishedAt = new \DateTimeImmutable();
        $start = $this->startedAt ?? $this->createdAt;
        $this->durationMs = (int) round(((float) $this->finishedAt->format('U.u') - (float) $start->format('U.u')) * 1000);
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getTokensIn(): int
    {
        return $this->tokensIn;
    }

    public function getTokensOut(): int
    {
        return $this->tokensOut;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    /** @return list<string> */
    public function getToolsUsed(): array
    {
        return $this->toolsUsed;
    }

    /** @return Collection<int, RunStep> */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    #[Groups(['run:read'])]
    public function getStepCount(): int
    {
        return $this->steps->count();
    }

    #[Groups(['run:read'])]
    public function getTitle(): string
    {
        return match ($this->kind) {
            RunKind::Agent => $this->agent?->getName() ?? 'Deleted agent',
            RunKind::Crew => $this->crew?->getName() ?? 'Deleted crew',
            RunKind::Prompt => 'Playground · '.($this->model ?? ''),
        };
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
