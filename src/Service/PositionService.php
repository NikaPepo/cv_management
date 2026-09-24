<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreatePositionDto;
use App\DTO\PositionAccessRuleInputDto;
use App\DTO\PositionAttributeInputDto;
use App\DTO\UpdatePositionDto;
use App\Entity\AttributeDefinition;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionAttribute;
use App\Enum\AccessRuleOperator;
use App\Enum\AttributeDataType;
use App\Enum\PositionLevel;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Position lifecycle: create / update / duplicate / delete. The Service
 * holds the rules about which (operator, dataType) pairs are legal and
 * about consolidating the attribute + accessRule collections on edit.
 *
 * Per the assignment: any Recruiter (or Admin) can edit any Position —
 * there is no ownership. We do not check the caller beyond ROLE_RECRUITER.
 */
final readonly class PositionService
{
    public function __construct(
        private PositionRepository $positionRepository,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreatePositionDto $dto): Position
    {
        $position = new Position();
        $this->applyBasics($position, $dto->title, $dto->shortDescription, $dto->company, $dto->level, $dto->isPublic, $dto->maxProjects, $dto->projectTagFilter);
        $this->syncAttributes($position, $dto->attributes);
        $this->syncAccessRules($position, $dto->accessRules);

        $this->entityManager->persist($position);
        $this->entityManager->flush();

        return $position;
    }

    public function update(Position $position, UpdatePositionDto $dto): Position
    {
        // Optimistic locking: positions have no owner, so multiple
        // recruiters (and admins) may have the editor open at the same
        // time. The client must echo back the version it loaded; if the
        // row has moved since, refuse with 409 and let it reload.
        if ($dto->version !== null && $position->getVersion() !== $dto->version) {
            throw new ConflictHttpException(
                'Position was modified by another session. Please reload.'
            );
        }

        if ($dto->title !== null) {
            $position->setTitle($dto->title);
        }
        if ($dto->shortDescription !== null) {
            $position->setShortDescription($dto->shortDescription);
        }
        if ($dto->company !== null) {
            $position->setCompany($dto->company === '' ? null : $dto->company);
        }
        if ($dto->level !== null) {
            $position->setLevel($dto->level === '' ? null : PositionLevel::from($dto->level));
        }
        if ($dto->isPublic !== null) {
            $position->setIsPublic($dto->isPublic);
        }
        if ($dto->maxProjects !== null) {
            $position->setMaxProjects($dto->maxProjects);
        }
        if ($dto->projectTagFilter !== null) {
            $position->setProjectTagFilter($dto->projectTagFilter);
        }
        if ($dto->attributes !== null) {
            $this->syncAttributes($position, $dto->attributes);
        }
        if ($dto->accessRules !== null) {
            $this->syncAccessRules($position, $dto->accessRules);
        }

        $position->touch();
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException(
                'Position was modified by another session. Please reload.'
            );
        }

        return $position;
    }

    public function duplicate(Position $source, ?string $newTitle = null): Position
    {
        $copy = new Position();
        $copy->setTitle($newTitle ?? $source->getTitle() . ' (copy)');
        $copy->setShortDescription($source->getShortDescription());
        $copy->setCompany($source->getCompany());
        $copy->setLevel($source->getLevel());
        $copy->setIsPublic($source->isPublic());
        $copy->setMaxProjects($source->getMaxProjects());
        $copy->setProjectTagFilter($source->getProjectTagFilter());

        foreach ($source->getAttributes() as $pa) {
            $copyPa = new PositionAttribute();
            $copyPa->setPosition($copy);
            $copyPa->setAttributeDefinition($pa->getAttributeDefinition());
            $copyPa->setSortOrder($pa->getSortOrder());
            $copy->getAttributes()->add($copyPa);
        }

        foreach ($source->getAccessRules() as $rule) {
            $copyRule = new PositionAccessRule();
            $copyRule->setPosition($copy);
            $copyRule->setAttributeDefinition($rule->getAttributeDefinition());
            $copyRule->setOperator($rule->getOperator());
            $copyRule->setValue($rule->getValue());
            $copy->getAccessRules()->add($copyRule);
        }

        $this->entityManager->persist($copy);
        $this->entityManager->flush();

        return $copy;
    }

    public function delete(Position $position): void
    {
        $this->entityManager->remove($position);
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException(
                'Position was modified by another session. Please reload.'
            );
        }
    }

    /**
     * @param PositionAttributeInputDto[] $items
     */
    private function syncAttributes(Position $position, array $items): void
    {
        // Wipe existing so a recruiter can drop attributes from the position
        // by simply omitting them from the payload. We MUST flush the
        // deletes before inserting new rows: Doctrine commits INSERTs
        // before DELETEs in a single transaction, so without this flush
        // the new INSERT for (position_id, attribute_definition_id) would
        // collide with the still-present old row of the same pair.
        foreach ($position->getAttributes() as $existing) {
            $this->entityManager->remove($existing);
        }
        $position->getAttributes()->clear();
        // Flush deletes BEFORE inserting the new rows: Doctrine commits
        // INSERTs before DELETEs in a single transaction, so without this
        // the new INSERT for (position_id, attribute_definition_id) would
        // collide with the still-present old row of the same pair and
        // raise UniqueConstraintViolationException. flush() with no queued
        // changes is a no-op, so it is safe to call unconditionally.
        $this->entityManager->flush();

        foreach ($items as $item) {
            $def = $this->attributeDefinitionRepository->find($item->attributeDefinitionId);
            if ($def === null) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'attributeDefinitionId %d not found.',
                    $item->attributeDefinitionId
                ));
            }
            $pa = new PositionAttribute();
            $pa->setPosition($position);
            $pa->setAttributeDefinition($def);
            $pa->setSortOrder($item->sortOrder);
            $position->getAttributes()->add($pa);
        }
    }

    /**
     * @param PositionAccessRuleInputDto[] $items
     */
    private function syncAccessRules(Position $position, array $items): void
    {
        foreach ($position->getAccessRules() as $existing) {
            $this->entityManager->remove($existing);
        }
        $position->getAccessRules()->clear();
        // Same flush-before-insert ordering issue as syncAttributes above:
        // the unique constraint on (position_id, attribute_definition_id)
        // would fire if we tried to insert new rules before the deletes
        // were committed.
        $this->entityManager->flush();

        foreach ($items as $item) {
            $def = $this->attributeDefinitionRepository->find($item->attributeDefinitionId);
            if ($def === null) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'attributeDefinitionId %d not found.',
                    $item->attributeDefinitionId
                ));
            }
            $operator = AccessRuleOperator::from($item->operator);
            if (!$operator->isCompatibleWith($def->getDataType())) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'Operator "%s" is not allowed for attribute "%s" of type "%s".',
                    $operator->value,
                    $def->getName(),
                    $def->getDataType()->value
                ));
            }
            $rule = new PositionAccessRule();
            $rule->setPosition($position);
            $rule->setAttributeDefinition($def);
            $rule->setOperator($operator);
            $rule->setValue($item->value);
            $position->getAccessRules()->add($rule);
        }
    }

    private function applyBasics(
        Position $position,
        string $title,
        string $shortDescription,
        ?string $company,
        ?string $level,
        bool $isPublic,
        int $maxProjects,
        array $projectTagFilter,
    ): void {
        $position->setTitle($title);
        $position->setShortDescription($shortDescription);
        $position->setCompany($company);
        $position->setLevel($level === null ? null : PositionLevel::from($level));
        $position->setIsPublic($isPublic);
        $position->setMaxProjects($maxProjects);
        $position->setProjectTagFilter($projectTagFilter);
    }
}