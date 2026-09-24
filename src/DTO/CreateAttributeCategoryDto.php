<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAttributeCategoryDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 64)]
        #[Assert\Regex('/^[a-z0-9_]+$/', message: 'code must be lowercase alphanumeric/underscore.')]
        public string $code,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 128)]
        public string $name,

        #[Assert\GreaterThanOrEqual(0)]
        public int $sortOrder = 0,
    ) {
    }
}