<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'An account with this email already exists.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const DEFAULT_PREFERENCES = [
        'defaultProvider' => 'mock',
        'defaultModel' => 'zyntara-mock-1',
        'toastNotifications' => true,
        'theme' => 'dark',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['user:read'])]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read'])]
    private string $email = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 120)]
    #[Groups(['user:read'])]
    private string $fullName = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['user:read'])]
    private bool $active = true;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $preferences = self::DEFAULT_PREFERENCES;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = trim($fullName);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    #[Groups(['user:read'])]
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique(array_filter($roles, static fn (string $r) => 'ROLE_USER' !== $r)));

        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /** @return array<string, mixed> */
    #[Groups(['user:read'])]
    public function getPreferences(): array
    {
        return array_merge(self::DEFAULT_PREFERENCES, $this->preferences);
    }

    /** @param array<string, mixed> $preferences */
    public function setPreferences(array $preferences): static
    {
        $this->preferences = array_intersect_key(
            array_merge($this->getPreferences(), $preferences),
            self::DEFAULT_PREFERENCES,
        );

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function touchLastLogin(): static
    {
        $this->lastLoginAt = new \DateTimeImmutable();

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
