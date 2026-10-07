<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeDataType;
use App\Repository\AttributeDefinitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeDefinitionRepository::class)]
#[ORM\Table(name: 'attribute_definition')]
#[ORM\UniqueConstraint(name: 'UNIQ_ATTRIBUTE_DEF_NAME', columns: ['name'])]
class AttributeDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private string $name;

    #[ORM\Column(length: 512)]
    private string $description = '';

    #[ORM\Column(length: 32, enumType: AttributeDataType::class)]
    private AttributeDataType $dataType;

    #[ORM\ManyToOne(targetEntity: AttributeCategory::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private AttributeCategory $category;

    #[ORM\Column(options: ['default' => false])]
    private bool $required = false;

    /** @var Collection<int, AttributeOption> */
    #[ORM\OneToMany(targetEntity: AttributeOption::class, mappedBy: 'attributeDefinition', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $options;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->options = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDataType(): AttributeDataType
    {
        return $this->dataType;
    }

    public function setDataType(AttributeDataType $dataType): static
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function getCategory(): AttributeCategory
    {
        return $this->category;
    }

    public function setCategory(AttributeCategory $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, AttributeOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    /**
     * Adds an option to the in-memory collection and keeps the
     * owning-side FK in sync so Doctrine treats this as a single
     * managed relationship (no duplicate INSERTs on flush).
     */
    public function addOption(AttributeOption $option): static
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setAttributeDefinition($this);
        }
        return $this;
    }

    /**
     * Removes an option from the in-memory collection. Combined with
     * `orphanRemoval: true` on the mapping, this triggers a single
     * DELETE on flush — no need for a separate EntityManager::remove()
     * call, and no risk of leaving a detached Entity behind in the
     * collection. The owning-side FK stays set; this method is only
     * safe to use during a sync where the Entity will be discarded
     * (e.g. on the same flush that drops it from the collection).
     */
    public function removeOption(AttributeOption $option): static
    {
        $this->options->removeElement($option);
        return $this;
    }
}