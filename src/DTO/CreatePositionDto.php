<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePositionDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 128)]
        public string $title,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 512)]
        public string $shortDescription,

        #[Assert\Length(max: 128)]
        public ?string $company = null,

        #[Assert\Choice(choices: ['junior', 'middle', 'senior', 'c_level'])]
        public ?string $level = null,

        public bool $isPublic = true,

        #[Assert\Range(min: 0, max: 50)]
        public int $maxProjects = 5,

        /** @var string[] */
        public array $projectTagFilter = [],

        /** @var PositionAttributeInputDto[] */
        public array $attributes = [],

        /** @var PositionAccessRuleInputDto[] */
        public array $accessRules = [],
    ) {
    }
}