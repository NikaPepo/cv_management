<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProjectDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 128)]
        public string $name,

        public ?string $periodStart = null,

        public ?string $periodEnd = null,

        public ?string $markdownDescription = null,

        /** @var string[] */
        #[Assert\All([new Assert\Length(max: 64)])]
        public array $technologyTagNames = [],
    ) {
    }
}