<?php

namespace App\Entity;

use App\Enum\StepType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/** A single observable event of a run (plan, tool call, retrieval, hand-off...). */
#[ORM\Entity]
#[ORM\Table(name: 'run_steps')]
#[ORM\UniqueConstraint(columns: ['run_id', 'sequence'])]
class RunStep
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['run:item', 'step:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'steps')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Run $run;

    #[ORM\Column]
    #[Groups(['run:item', 'step:read'])]
    private int $sequence;

    /** Agent name, "supervisor" or "system". */
    #[ORM\Column(length: 120)]
    #[Groups(['run:item', 'step:read'])]
    private string $actor;

    #[ORM\Column(length: 16, enumType: StepType::class)]
    #[Groups(['run:item', 'step:read'])]
    private StepType $type;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['run:item', 'step:read'])]
    private string $content;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['run:item', 'step:read'])]
    private array $metadata;

    #[ORM\Column]
    #[Groups(['run:item', 'step:read'])]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $metadata */
    public function __construct(Run $run, int $sequence, string $actor, StepType $type, string $content, array $metadata = [])
    {
        $this->id = Uuid::v7();
        $this->run = $run;
        $this->sequence = $sequence;
        $this->actor = mb_substr($actor, 0, 120);
        $this->type = $type;
        $this->content = $content;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
        $run->getSteps()->add($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRun(): Run
    {
        return $this->run;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    public function getType(): StepType
    {
        return $this->type;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
