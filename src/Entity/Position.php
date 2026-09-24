<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PositionLevel;
use App\Repository\PositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shared position template. Per the assignment:
 *   - No recruiter_id. All recruiters share the same pool.
 *   - Public (anyone authenticated) or Restricted (access rules).
 *   - Subset of AttributeDefinitions + a project-tag filter.
 */
#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: 'position')]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column(length: 128)]
    private string $title;

    #[ORM\Column(length: 512)]
    private string $shortDescription;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 16, nullable: true, enumType: PositionLevel::class)]
    private ?PositionLevel $level = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublic = true;

    #[ORM\Column]
    private int $maxProjects = 5;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $projectTagFilter = [];

    /** @var Collection<int, PositionAttribute> */
    #[ORM\OneToMany(targetEntity: PositionAttribute::class, mappedBy: 'position', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $attributes;

    /** @var Collection<int, PositionAccessRule> */
    #[ORM\OneToMany(targetEntity: PositionAccessRule::class, mappedBy: 'position', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $accessRules;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
        $this->accessRules = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function getLevel(): ?PositionLevel
    {
        return $this->level;
    }

    public function setLevel(?PositionLevel $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getMaxProjects(): int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(int $maxProjects): static
    {
        $this->maxProjects = max(0, $maxProjects);
        return $this;
    }

    /** @return string[] */
    public function getProjectTagFilter(): array
    {
        return $this->projectTagFilter;
    }

    /** @param string[] $filter */
    public function setProjectTagFilter(array $filter): static
    {
        $this->projectTagFilter = array_values(array_unique(array_filter(array_map(
            static fn ($t) => mb_strtolower(trim((string) $t)),
            $filter
        ))));
        return $this;
    }

    /** @return Collection<int, PositionAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, PositionAccessRule> */
    public function getAccessRules(): Collection
    {
        return $this->accessRules;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}