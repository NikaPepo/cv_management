<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AttributeOptionInputDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $value,

        #[Assert\GreaterThanOrEqual(0)]
        public int $sortOrder = 0,
    ) {
    }
}