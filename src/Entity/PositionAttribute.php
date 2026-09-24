<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PositionAttributeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionAttributeRepository::class)]
#[ORM\Table(name: 'position_attribute')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_POSITION_ATTRIBUTE_PAIR',
    columns: ['position_id', 'attribute_definition_id']
)]
class PositionAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Position::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AttributeDefinition $attributeDefinition;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

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

    public function getAttributeDefinition(): AttributeDefinition
    {
        return $this->attributeDefinition;
    }

    public function setAttributeDefinition(AttributeDefinition $attributeDefinition): static
    {
        $this->attributeDefinition = $attributeDefinition;
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
}