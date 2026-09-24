<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
class ResetPasswordRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 180)]
        public string $password,
    )
    {
    }
}
