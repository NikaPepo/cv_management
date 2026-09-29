<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validates a new password that the CURRENTLY AUTHENTICATED user wants
 * to set on their own account.
 *
 * Identity comes from Symfony Security (`#[CurrentUser]`), NOT from the
 * request body — there is no `email` / `userId` field on purpose. That
 * is the core of this flow's security. Adding such a field would allow
 * any authenticated user to set the password of an arbitrary other
 * user, which is an authentication-bypass primitive.
 *
 * Length bounds mirror RegisterRequestDto exactly so users get the same
 * password policy whether they register through /api/registration or
 * later add a password via this endpoint. Do not loosen these without
 * also updating RegisterRequestDto.
 */
final readonly class SetPasswordRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 180)]
        public string $password,
    ) {
    }
}