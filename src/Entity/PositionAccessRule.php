<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccessRuleOperator;
use App\Repository\PositionAccessRuleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Restricts who can build a CV for this position. The operator depends on
 * the AttributeDefinition.dataType — see AccessRuleOperator::allowedFor().
 *
 * `value` is JSONB because access rules compare against typed values
 * (numeric, date, one_of_many option id, etc.). The PositionService
 * validates compatibility at write time so we don't store garbage.
 */
#[ORM\Entity(repositoryClass: PositionAccessRuleRepository::class)]
#[ORM\Table(name: 'position_access_rule')]
class PositionAccessRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Position::class, inversedBy: 'accessRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AttributeDefinition $attributeDefinition;

    #[ORM\Column(length: 32, enumType: AccessRuleOperator::class)]
    private AccessRuleOperator $operator;

    #[ORM\Column(type: 'json')]
    private mixed $value = null;

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

    public function getOperator(): AccessRuleOperator
    {
        return $this->operator;
    }

    public function setOperator(AccessRuleOperator $operator): static
    {
        $this->operator = $operator;
        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }
}