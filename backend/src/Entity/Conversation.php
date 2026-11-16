<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\OwnerProcessor;
use App\Repository\ConversationRepository;
use App\Validator\OwnedByCurrentUser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** A chat thread between the user and one agent. Each user message triggers a Run. */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversations')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(normalizationContext: ['groups' => ['conversation:read', 'conversation:item']], security: 'object.getOwner() == user'),
        new Post(processor: OwnerProcessor::class),
        new Patch(security: 'object.getOwner() == user'),
        new Delete(security: 'object.getOwner() == user'),
    ],
    normalizationContext: ['groups' => ['conversation:read']],
    denormalizationContext: ['groups' => ['conversation:write']],
    order: ['updatedAt' => 'DESC'],
    security: "is_granted('ROLE_USER')",
)]
#[ApiFilter(SearchFilter::class, properties: ['agent' => 'exact'])]
class Conversation implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['conversation:read', 'run:read'])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[OwnedByCurrentUser]
    #[Groups(['conversation:read', 'conversation:write'])]
    private ?Agent $agent = null;

    #[ORM\Column(length: 160)]
    #[Assert\Length(max: 160)]
    #[Groups(['conversation:read', 'conversation:write'])]
    private string $title = 'New conversation';

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    #[Groups(['conversation:item'])]
    private Collection $messages;

    #[ORM\Column]
    #[Groups(['conversation:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['conversation:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = '' === trim($title) ? 'New conversation' : trim($title);

        return $this;
    }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        $this->messages->add($message);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    #[Groups(['conversation:read'])]
    public function getMessageCount(): int
    {
        return $this->messages->count();
    }

    #[Groups(['conversation:read'])]
    public function getLastMessagePreview(): ?string
    {
        $last = $this->messages->last();

        return $last ? mb_substr($last->getContent(), 0, 140) : null;
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
