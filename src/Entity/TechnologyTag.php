<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TechnologyTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TechnologyTagRepository::class)]
#[ORM\Table(name: 'technology_tag')]
#[ORM\UniqueConstraint(name: 'UNIQ_TECHNOLOGY_TAG_NAME', columns: ['name'])]
class TechnologyTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $name;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'technologyTags')]
    private Collection $projects;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        // Normalise once at the boundary so duplicate "JavaScript" / "javascript"
        // cannot coexist.
        $this->name = mb_strtolower(trim($name));
        return $this;
    }

    /** @return Collection<int, Project> */
    public function getProjects(): Collection
    {
        return $this->projects;
    }
}