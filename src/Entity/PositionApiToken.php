<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PositionApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A bearer credential bound to a single Position. The plaintext secret
 * is shown exactly once at creation; only its SHA-256 hash is stored.
 * Revocation is soft (revokedAt timestamp) so we keep an audit trail.
 */
#[ORM\Entity(repositoryClass: PositionApiTokenRepository::class)]
#[ORM\Table(name: 'position_api_token')]
#[ORM\Index(name: 'IDX_POSITION_API_TOKEN_POSITION', columns: ['position_id'])]
class PositionApiToken
{
    public const PREFIX = 'pst_';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    /**
     * Hex-encoded SHA-256 of the plaintext secret. 64 chars.
     * UNIQUE so each hash is at most one row.
     */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    /**
     * First 8 chars of the plaintext secret, kept for UI display so
     * recruiters can tell tokens apart without revealing the secret.
     */
    #[ORM\Column(length: 8)]
    private string $prefix;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function setPosition(Position $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touchLastUsed(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function revoke(): static
    {
        $this->revokedAt = new \DateTimeImmutable();
        return $this;
    }
}
