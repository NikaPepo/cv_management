<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCvDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $positionId,
    ) {
    }
}