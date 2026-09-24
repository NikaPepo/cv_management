<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 180)]
        public string $password,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['candidate', 'recruiter'])]
        public string $accountType,
    )
    {
    }
}
