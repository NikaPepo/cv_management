<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetProfileAttributeDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $attributeDefinitionId,

        #[Assert\PositiveOrZero]
        public ?int $version = null,

        public ?string $stringValue = null,

        public ?string $markdownText = null,

        #[Assert\Regex('/^-?\d+(\.\d+)?$/', message: 'numericValue must be a decimal number.')]
        public ?string $numericValue = null,

        public ?string $dateValue = null,

        public ?string $periodStart = null,

        public ?string $periodEnd = null,

        public ?bool $booleanValue = null,

        public ?string $imageUrl = null,

        #[Assert\Positive]
        public ?int $selectedOptionId = null,
    ) {
    }
}