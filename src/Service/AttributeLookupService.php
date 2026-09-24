<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AttributeLookupQueryDto;
use App\Entity\AttributeDefinition;
use App\Entity\Profile;
use App\Repository\AttributeDefinitionRepository;

/**
 * Read-side service for the "attribute picker" UI the assignment requires:
 * lookup by prefix + filter by category + recently used by candidate.
 *
 * This is a service, not a repository method, because the assembly of
 * results depends on which inputs the caller passed.
 */
final readonly class AttributeLookupService
{
    public function __construct(
        private AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    /**
     * @return array{prefix: AttributeDefinition[], recent: AttributeDefinition[], byCategory: AttributeDefinition[]}
     */
    public function lookup(AttributeLookupQueryDto $query, ?Profile $profile = null): array
    {
        $prefix = trim((string) $query->prefix);
        $limit = $query->limit;

        $prefixResults = $prefix === ''
            ? []
            : $this->attributeDefinitionRepository->findByNamePrefix($prefix, $limit);

        $categoryResults = $query->categoryId !== null
            ? $this->attributeDefinitionRepository->findByCategory($query->categoryId)
            : [];

        $recentResults = [];
        if ($query->recentOnly && $profile !== null) {
            $recentResults = $this->attributeDefinitionRepository->findRecentlyUsedByProfile($profile->getId() ?? 0, $limit);
        }

        return [
            'prefix' => $prefixResults,
            'byCategory' => $categoryResults,
            'recent' => $recentResults,
        ];
    }
}