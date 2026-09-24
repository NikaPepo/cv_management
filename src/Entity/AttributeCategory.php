<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttributeCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeCategoryRepository::class)]
#[ORM\Table(name: 'attribute_category')]
class AttributeCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $code;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    /** @var Collection<int, AttributeDefinition> */
    #[ORM\OneToMany(targetEntity: AttributeDefinition::class, mappedBy: 'category')]
    private Collection $attributes;

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /** @return Collection<int, AttributeDefinition> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }
}