<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['recipient_id', 'read_at'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Patch(security: 'object.getOwner() == user'),
        new Delete(security: 'object.getOwner() == user'),
    ],
    normalizationContext: ['groups' => ['notification:read']],
    denormalizationContext: ['groups' => ['notification:write']],
    order: ['createdAt' => 'DESC'],
    paginationItemsPerPage: 30,
    security: "is_granted('ROLE_USER')",
)]
#[ApiFilter(ExistsFilter::class, properties: ['readAt'])]
class Notification implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['notification:read'])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 32, enumType: NotificationType::class)]
    #[Groups(['notification:read'])]
    private NotificationType $type;

    #[ORM\Column(length: 200)]
    #[Groups(['notification:read'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $body;

    /** Frontend route to open when clicked, e.g. /runs/{id}. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $link;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    #[Groups(['notification:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $recipient, NotificationType $type, string $title, ?string $body = null, ?string $link = null)
    {
        $this->id = Uuid::v7();
        $this->recipient = $recipient;
        $this->type = $type;
        $this->title = mb_substr($title, 0, 200);
        $this->body = $body;
        $this->link = $link;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->recipient;
    }

    public function setOwner(User $owner): static
    {
        $this->recipient = $owner;

        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    #[Groups(['notification:read'])]
    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    #[Groups(['notification:write'])]
    public function setRead(bool $read): static
    {
        $this->readAt = $read ? ($this->readAt ?? new \DateTimeImmutable()) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
