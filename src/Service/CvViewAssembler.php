<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Entity\Cv;
use App\Entity\ProfileAttribute;
use App\Enum\AttributeDataType;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds the read-only CV view from Position + Profile + ProfileAttribute
 * master values + filtered Projects. NO JSON serialization of CV data —
 * this just assembles pointers that the controller emits as a structured
 * object.
 */
final readonly class CvViewAssembler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
    ) {
    }

    /**
     * @return array{
     *     cv: array<string, mixed>,
     *     candidate: array<string, mixed>,
     *     position: array<string, mixed>,
     *     attributes: list<array<string, mixed>>,
     *     projects: list<array<string, mixed>>,
     *     likeCount: int,
     *     hasUnpublishedRequired: bool,
     * }
     */
    public function assemble(Cv $cv): array
    {
        $profile = $cv->getProfile();
        $position = $cv->getPosition();

        $repo = $this->entityManager->getRepository(ProfileAttribute::class);
        $rows = [];
        foreach ($position->getAttributes() as $pa) {
            $definition = $pa->getAttributeDefinition();
            $row = $repo->findOneBy(['profile' => $profile, 'attributeDefinition' => $definition]);
            $rows[] = $this->presentAttribute($pa->getSortOrder(), $definition, $row);
        }

        $projects = $this->projectRepository->findForProfileFilteredByTags(
            $profile,
            $position->getProjectTagFilter(),
            $position->getMaxProjects()
        );

        return [
            'cv' => [
                'id' => $cv->getId(),
                'status' => $cv->getStatus()->value,
                'createdAt' => $cv->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $cv->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'publishedAt' => $cv->getPublishedAt()?->format('Y-m-d'),
            ],
            'candidate' => [
                'firstName' => $profile->getFirstName(),
                'lastName' => $profile->getLastName(),
                'location' => $profile->getLocation(),
                'photoUrl' => $profile->getPhotoUrl(),
                'email' => $profile->getUser()?->getEmail(),
            ],
            'position' => [
                'id' => $position->getId(),
                'title' => $position->getTitle(),
                'company' => $position->getCompany(),
                'level' => $position->getLevel()?->value,
                'shortDescription' => $position->getShortDescription(),
            ],
            'attributes' => $rows,
            'projects' => array_map(static fn ($p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'periodStart' => $p->getPeriodStart()?->format('Y-m-d'),
                'periodEnd' => $p->getPeriodEnd()?->format('Y-m-d'),
                'markdownDescription' => $p->getMarkdownDescription(),
                'technologyTags' => array_map(
                    static fn ($t) => ['id' => $t->getId(), 'name' => $t->getName()],
                    $p->getTechnologyTags()->toArray()
                ),
            ], $projects),
            'likeCount' => $cv->likeCount(),
            'hasUnpublishedRequired' => $this->hasUnpublishedRequired($position, $profile),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAttribute(int $sortOrder, AttributeDefinition $def, ?ProfileAttribute $row): array
    {
        $value = null;
        $empty = true;
        if ($row !== null) {
            $value = match ($def->getDataType()) {
                AttributeDataType::String => $row->getStringValue(),
                AttributeDataType::Text => $row->getMarkdownText(),
                AttributeDataType::Numeric => $row->getNumericValue(),
                AttributeDataType::Date => $row->getDateValue()?->format('Y-m-d'),
                AttributeDataType::Period => [
                    'start' => $row->getPeriodStart()?->format('Y-m-d'),
                    'end' => $row->getPeriodEnd()?->format('Y-m-d'),
                ],
                AttributeDataType::Boolean => $row->getBooleanValue(),
                AttributeDataType::Image => $row->getImageUrl(),
                AttributeDataType::OneOfMany => $row->getSelectedOption()?->getValue(),
            };
            $empty = $row->isEmpty();
        }

        return [
            'attributeDefinitionId' => $def->getId(),
            'name' => $def->getName(),
            'description' => $def->getDescription(),
            'dataType' => $def->getDataType()->value,
            'required' => $def->isRequired(),
            'options' => array_map(
                static fn ($o) => ['id' => $o->getId(), 'value' => $o->getValue()],
                $def->getOptions()->toArray()
            ),
            'sortOrder' => $sortOrder,
            'value' => $value,
            'empty' => $empty,
            'version' => $row?->getVersion(),
        ];
    }

    private function hasUnpublishedRequired(\App\Entity\Position $position, \App\Entity\Profile $profile): bool
    {
        $repo = $this->entityManager->getRepository(ProfileAttribute::class);
        foreach ($position->getAttributes() as $pa) {
            $def = $pa->getAttributeDefinition();
            if (!$def->isRequired()) {
                continue;
            }
            $row = $repo->findOneBy(['profile' => $profile, 'attributeDefinition' => $def]);
            if ($row === null || $row->isEmpty()) {
                return true;
            }
        }
        return false;
    }
}