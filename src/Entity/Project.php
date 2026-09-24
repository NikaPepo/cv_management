<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
#[ORM\Index(name: 'IDX_PROJECT_PROFILE', columns: ['profile_id'])]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $markdownDescription = null;

    /** @var Collection<int, TechnologyTag> */
    #[ORM\ManyToMany(targetEntity: TechnologyTag::class, inversedBy: 'projects')]
    #[ORM\JoinTable(name: 'project_technology_tag')]
    private Collection $technologyTags;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->technologyTags = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(?\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(?\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getMarkdownDescription(): ?string
    {
        return $this->markdownDescription;
    }

    public function setMarkdownDescription(?string $markdownDescription): static
    {
        $this->markdownDescription = $markdownDescription;
        return $this;
    }

    /** @return Collection<int, TechnologyTag> */
    public function getTechnologyTags(): Collection
    {
        return $this->technologyTags;
    }

    public function addTechnologyTag(TechnologyTag $tag): static
    {
        if (!$this->technologyTags->contains($tag)) {
            $this->technologyTags->add($tag);
        }
        return $this;
    }

    public function removeTechnologyTag(TechnologyTag $tag): static
    {
        $this->technologyTags->removeElement($tag);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}