<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminUpdateUserDto
{
    public function __construct(
        #[Assert\Choice(choices: ['candidate', 'recruiter', 'admin'])]
        public ?string $role = null,

        public ?bool $blocked = null,
    ) {
    }
}