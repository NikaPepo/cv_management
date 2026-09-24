<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PositionAttributeInputDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $attributeDefinitionId,

        #[Assert\GreaterThanOrEqual(0)]
        public int $sortOrder = 0,
    ) {
    }
}