<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiPlatform\DocumentDeleteProcessor;
use App\Enum\DocumentStatus;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/** An uploaded file inside a knowledge base. Created via DocumentController (multipart upload). */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\Index(columns: ['status'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: 'object.getKnowledgeBase().getOwner() == user'),
        new Delete(security: 'object.getKnowledgeBase().getOwner() == user', processor: DocumentDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['document:read']],
    order: ['createdAt' => 'DESC'],
    security: "is_granted('ROLE_USER')",
)]
#[ApiFilter(SearchFilter::class, properties: ['knowledgeBase' => 'exact', 'status' => 'exact'])]
class Document
{
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'text/plain',
        'text/markdown',
        'text/x-markdown',
        'text/csv',
        'text/html',
        'application/json',
    ];
    public const MAX_SIZE = 20 * 1024 * 1024;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['document:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['document:read'])]
    private KnowledgeBase $knowledgeBase;

    #[ORM\Column(length: 255)]
    #[Groups(['document:read'])]
    private string $originalName;

    #[ORM\Column(length: 500)]
    private string $storedPath;

    #[ORM\Column(length: 120)]
    #[Groups(['document:read'])]
    private string $mimeType;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private int $size;

    #[ORM\Column(length: 16, enumType: DocumentStatus::class)]
    #[Groups(['document:read'])]
    private DocumentStatus $status = DocumentStatus::Pending;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private int $chunkCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $error = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['document:read'])]
    private ?\DateTimeImmutable $indexedAt = null;

    public function __construct(KnowledgeBase $knowledgeBase, string $originalName, string $storedPath, string $mimeType, int $size)
    {
        $this->id = Uuid::v7();
        $this->knowledgeBase = $knowledgeBase;
        $this->originalName = $originalName;
        $this->storedPath = $storedPath;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->createdAt = new \DateTimeImmutable();
        $knowledgeBase->getDocuments()->add($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKnowledgeBase(): KnowledgeBase
    {
        return $this->knowledgeBase;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getStoredPath(): string
    {
        return $this->storedPath;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function markProcessing(): static
    {
        $this->status = DocumentStatus::Processing;
        $this->error = null;

        return $this;
    }

    public function markIndexed(int $chunkCount): static
    {
        $this->status = DocumentStatus::Indexed;
        $this->chunkCount = $chunkCount;
        $this->indexedAt = new \DateTimeImmutable();
        $this->error = null;

        return $this;
    }

    public function markFailed(string $error): static
    {
        $this->status = DocumentStatus::Failed;
        $this->error = mb_substr($error, 0, 2000);

        return $this;
    }

    public function getChunkCount(): int
    {
        return $this->chunkCount;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getIndexedAt(): ?\DateTimeImmutable
    {
        return $this->indexedAt;
    }
}
