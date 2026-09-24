<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload for the auto-save endpoint. `version` carries the optimistic lock
 * the assignment requires; an empty/missing version skips the check (the
 * very first save after registration has nothing to lock against).
 */
final readonly class UpdateProfileDto
{
    public function __construct(
        #[Assert\Length(max: 128)]
        public ?string $firstName = null,

        #[Assert\Length(max: 128)]
        public ?string $lastName = null,

        #[Assert\Length(max: 255)]
        public ?string $location = null,

        #[Assert\Length(max: 512)]
        #[Assert\Url(protocols: ['http', 'https'])]
        public ?string $photoUrl = null,

        #[Assert\PositiveOrZero]
        public ?int $version = null,
    ) {
    }
}