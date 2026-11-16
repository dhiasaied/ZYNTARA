<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\KnowledgeBaseDeleteProcessor;
use App\ApiPlatform\OwnerProcessor;
use App\Enum\DocumentStatus;
use App\Repository\KnowledgeBaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** A collection of documents an agent can retrieve from (the RAG rung). */
#[ORM\Entity(repositoryClass: KnowledgeBaseRepository::class)]
#[ORM\Table(name: 'knowledge_bases')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: 'object.getOwner() == user'),
        new Post(processor: OwnerProcessor::class),
        new Patch(security: 'object.getOwner() == user'),
        new Delete(security: 'object.getOwner() == user', processor: KnowledgeBaseDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['kb:read']],
    denormalizationContext: ['groups' => ['kb:write']],
    order: ['createdAt' => 'DESC'],
    security: "is_granted('ROLE_USER')",
)]
class KnowledgeBase implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['kb:read', 'agent:read', 'document:read'])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['kb:read', 'kb:write', 'agent:read', 'document:read'])]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['kb:read', 'kb:write'])]
    private ?string $description = null;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'knowledgeBase')]
    private Collection $documents;

    #[ORM\Column]
    #[Groups(['kb:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->documents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    /** @return Collection<int, Document> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    #[Groups(['kb:read'])]
    public function getDocumentCount(): int
    {
        return $this->documents->count();
    }

    #[Groups(['kb:read'])]
    public function getIndexedCount(): int
    {
        return $this->documents->filter(static fn (Document $d) => DocumentStatus::Indexed === $d->getStatus())->count();
    }

    #[Groups(['kb:read'])]
    public function getChunkCount(): int
    {
        $total = 0;
        foreach ($this->documents as $document) {
            $total += $document->getChunkCount();
        }

        return $total;
    }

    #[Groups(['kb:read'])]
    public function getTotalBytes(): int
    {
        $total = 0;
        foreach ($this->documents as $document) {
            $total += $document->getSize();
        }

        return $total;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
