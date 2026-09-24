<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CvStatus;
use App\Repository\CvRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A CV is just a (candidate, position) pair plus status metadata. It does
 * NOT duplicate attribute values — those live in ProfileAttribute and are
 * read at view time. When a candidate edits a value inside a CV, the edit
 * goes through ProfileAttributeService, so the master value is updated
 * exactly once.
 */
#[ORM\Entity(repositoryClass: CvRepository::class)]
#[ORM\Table(name: 'cv')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_CV_PROFILE_POSITION',
    columns: ['profile_id', 'position_id']
)]
#[ORM\Index(name: 'IDX_CV_POSITION', columns: ['position_id'])]
#[ORM\Index(name: 'IDX_CV_STATUS', columns: ['status'])]
class Cv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\Column(length: 16, enumType: CvStatus::class)]
    private CvStatus $status = CvStatus::Draft;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, CvLike> */
    #[ORM\OneToMany(targetEntity: CvLike::class, mappedBy: 'cv', orphanRemoval: true, cascade: ['remove'])]
    private Collection $likes;

    public function __construct()
    {
        $this->likes = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): static
    {
        $this->profile = $profile;
        return $this;
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

    public function getStatus(): CvStatus
    {
        return $this->status;
    }

    public function setStatus(CvStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, CvLike> */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function likeCount(): int
    {
        return $this->likes->count();
    }
}