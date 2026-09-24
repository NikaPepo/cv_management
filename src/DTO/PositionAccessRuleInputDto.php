<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single access rule. The Service validates the operator is compatible
 * with the AttributeDefinition.dataType before persisting.
 */
final readonly class PositionAccessRuleInputDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $attributeDefinitionId,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'contains', 'before', 'after'])]
        public string $operator,

        public mixed $value = null,
    ) {
    }
}