<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
class ForgotPasswordRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,
    )
    {
    }
}
