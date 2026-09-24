<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AttributeLookupQueryDto
{
    public function __construct(
        #[Assert\Length(max: 128)]
        public ?string $prefix = null,

        #[Assert\Positive]
        public ?int $categoryId = null,

        public bool $recentOnly = false,

        #[Assert\Range(min: 1, max: 50)]
        public int $limit = 20,
    ) {
    }
}