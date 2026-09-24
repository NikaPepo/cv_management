<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AttributeDataType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAttributeDefinitionDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 128)]
        public string $name,

        #[Assert\Length(max: 512)]
        public string $description = '',

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['string', 'text', 'image', 'numeric', 'date', 'period', 'boolean', 'one_of_many'])]
        public string $dataType,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $categoryId,

        public bool $required = false,

        /** @var AttributeOptionInputDto[] */
        public array $options = [],
    ) {
    }

    public function getDataTypeEnum(): AttributeDataType
    {
        return AttributeDataType::from($this->dataType);
    }
}