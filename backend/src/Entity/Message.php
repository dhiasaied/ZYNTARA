<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'messages')]
class Message
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['conversation:item'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\Column(length: 16)]
    #[Groups(['conversation:item'])]
    private string $role;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['conversation:item'])]
    private string $content;

    /** The run that produced (assistant) or was triggered by (user) this message. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Run $run = null;

    #[ORM\Column]
    #[Groups(['conversation:item'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(Conversation $conversation, string $role, string $content, ?Run $run = null)
    {
        $this->id = Uuid::v7();
        $this->conversation = $conversation;
        $this->role = $role;
        $this->content = $content;
        $this->run = $run;
        $this->createdAt = new \DateTimeImmutable();
        $conversation->addMessage($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRun(): ?Run
    {
        return $this->run;
    }

    public function setRun(?Run $run): static
    {
        $this->run = $run;

        return $this;
    }

    #[Groups(['conversation:item'])]
    public function getRunId(): ?string
    {
        return $this->run?->getId()->toRfc4122();
    }

    #[Groups(['conversation:item'])]
    public function getRunStatus(): ?string
    {
        return $this->run?->getStatus()->value;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
