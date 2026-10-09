<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Length;

/**
 * Payload for POST /api/positions/{id}/api-token. The label is the
 * only knob a recruiter has; everything else (the secret, the hash,
 * the prefix) is generated server-side.
 */
final readonly class GeneratePositionApiTokenDto
{
    public function __construct(
        #[Length(max: 255)]
        public ?string $label = null,
    ) {
    }
}
