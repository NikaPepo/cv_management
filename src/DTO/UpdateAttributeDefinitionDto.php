<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateAttributeDefinitionDto
{
    public function __construct(
        #[Assert\Length(min: 1, max: 128)]
        public ?string $name = null,

        #[Assert\Length(max: 512)]
        public ?string $description = null,

        #[Assert\GreaterThanOrEqual(0)]
        public ?int $categoryId = null,

        public ?bool $required = null,

        /** @var AttributeOptionInputDto[]|null */
        public ?array $options = null,
    ) {
    }
}