<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePositionDto
{
    public function __construct(
        /**
         * Optimistic-lock token. The caller passes back the version it read
         * when it loaded the position; if the row has moved since, the
         * server rejects with HTTP 409 so the client can reload and merge.
         */
        #[Assert\Type('integer')]
        public ?int $version = null,

        #[Assert\Length(min: 1, max: 128)]
        public ?string $title = null,

        #[Assert\Length(min: 1, max: 512)]
        public ?string $shortDescription = null,

        #[Assert\Length(max: 128)]
        public ?string $company = null,

        #[Assert\Choice(choices: ['junior', 'middle', 'senior', 'c_level'])]
        public ?string $level = null,

        public ?bool $isPublic = null,

        #[Assert\Range(min: 0, max: 50)]
        public ?int $maxProjects = null,

        /** @var string[]|null */
        public ?array $projectTagFilter = null,

        /** @var PositionAttributeInputDto[]|null */
        public ?array $attributes = null,

        /** @var PositionAccessRuleInputDto[]|null */
        public ?array $accessRules = null,
    ) {
    }
}