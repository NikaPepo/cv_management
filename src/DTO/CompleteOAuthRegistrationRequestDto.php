<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CompleteOAuthRegistrationRequestDto
{
    public function __construct(
        #[Assert\Choice(choices: ['candidate', 'recruiter'])]
        public string $accountType,
    ) {
    }
}
