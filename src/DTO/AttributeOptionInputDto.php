<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One dropdown entry inside CreateAttributeDefinitionDto / UpdateAttributeDefinitionDto.
 *
 * `id` carries identity for the syncAttributeOptionId routine in
 * AttributeDefinitionService::update():
 *   - null  → caller is asking for a new row (INSERT);
 *   - int   → caller is editing an existing row (UPDATE in place, OR
 *              rejected with 422 if it doesn't belong to the target
 *              AttributeDefinition).
 *
 * `value` is NEVER used as an identity — renaming an option must
 * UPDATE the same Entity, not DELETE + INSERT.
 */
final readonly class AttributeOptionInputDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $value,

        #[Assert\GreaterThanOrEqual(0)]
        public int $sortOrder = 0,

        #[Assert\Positive]
        public ?int $id = null,
    ) {
    }
}