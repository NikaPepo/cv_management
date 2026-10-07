<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateAttributeCategoryDto;
use App\DTO\CreateAttributeDefinitionDto;
use App\DTO\UpdateAttributeDefinitionDto;
use App\Entity\AttributeCategory;
use App\Entity\AttributeDefinition;
use App\Entity\AttributeOption;
use App\Enum\AttributeDataType;
use App\Repository\AttributeCategoryRepository;
use App\Repository\AttributeDefinitionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class AttributeDefinitionService
{
    public function __construct(
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeCategoryRepository $attributeCategoryRepository,
    ) {
    }

    public function create(CreateAttributeDefinitionDto $dto): AttributeDefinition
    {
        $category = $this->attributeCategoryRepository->find($dto->categoryId);
        if ($category === null) {
            throw new UnprocessableEntityHttpException('categoryId not found.');
        }

        if ($this->attributeDefinitionRepository->findOneByName($dto->name) !== null) {
            throw new ConflictHttpException('Attribute with this name already exists.');
        }

        $dataType = $dto->getDataTypeEnum();

        if ($dataType === AttributeDataType::OneOfMany && count($dto->options) === 0) {
            throw new UnprocessableEntityHttpException('OneOfMany attributes require at least one option.');
        }
        if ($dataType !== AttributeDataType::OneOfMany && count($dto->options) > 0) {
            throw new UnprocessableEntityHttpException('Only OneOfMany attributes accept options.');
        }

        $attribute = new AttributeDefinition();
        $attribute->setName($dto->name);
        $attribute->setDescription($dto->description);
        $attribute->setDataType($dataType);
        $attribute->setCategory($category);
        $attribute->setRequired($dto->required);

        foreach ($dto->options as $opt) {
            $option = new AttributeOption();
            $option->setAttributeDefinition($attribute);
            $option->setValue($opt->value);
            $option->setSortOrder($opt->sortOrder);
            $attribute->getOptions()->add($option);
        }

        try {
            $this->attributeDefinitionRepository->save($attribute);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Attribute with this name already exists.');
        }

        return $attribute;
    }

    public function update(AttributeDefinition $attribute, UpdateAttributeDefinitionDto $dto): AttributeDefinition
    {
        if ($dto->name !== null && $dto->name !== $attribute->getName()) {
            if ($this->attributeDefinitionRepository->findOneByName($dto->name) !== null) {
                throw new ConflictHttpException('Attribute with this name already exists.');
            }
            $attribute->setName($dto->name);
        }
        if ($dto->description !== null) {
            $attribute->setDescription($dto->description);
        }
        if ($dto->categoryId !== null) {
            $category = $this->attributeCategoryRepository->find($dto->categoryId);
            if ($category === null) {
                throw new UnprocessableEntityHttpException('categoryId not found.');
            }
            $attribute->setCategory($category);
        }
        if ($dto->required !== null) {
            $attribute->setRequired($dto->required);
        }
        if ($dto->options !== null) {
            if ($attribute->getDataType() !== AttributeDataType::OneOfMany) {
                throw new UnprocessableEntityHttpException('Only OneOfMany attributes accept options.');
            }
            $this->syncOptions($attribute, $dto->options);
        }

        $this->attributeDefinitionRepository->save($attribute);
        return $attribute;
    }

    /**
     * Identity-aware sync of AttributeOption rows against the submitted
     * list. Three rules:
     *
     *   - DTO has id + the id is one of OUR existing options → UPDATE
     *     (rename, re-sort, anything — keeps the row id stable so any
     *     ProfileAttribute.selectedOptionId FK still resolves).
     *
     *   - DTO has id but the id is foreign / stale (not in our set) → 422.
     *     Letting a recruiter "claim" another attribute's option would break
     *     the FK isolation that ProfileAttribute and the access-rule
     *     validator already rely on (see PositionService::validateAndNormalize…one_of_many).
     *
     *   - DTO has no id → INSERT a new AttributeOption.
     *
     * After the loop, anything still in the in-memory collection whose
     * id was NOT submitted is dropped via the inverse-side
     * `AttributeDefinition::removeOption()`. Combined with
     * `orphanRemoval: true` on the mapping, this produces exactly one
     * DELETE per dropped row — single flush at the end of `update()`.
     */
    private function syncOptions(AttributeDefinition $attribute, array $submitted): void
    {
        $existingById = [];
        foreach ($attribute->getOptions() as $current) {
            $existingId = $current->getId();
            if ($existingId !== null) {
                $existingById[$existingId] = $current;
            }
        }

        $seenIds = [];
        foreach ($submitted as $item) {
            if ($item->id !== null) {
                $id = $item->id;
                if (!isset($existingById[$id])) {
                    // Foreign or stale id — refuse rather than silently
                    // CREATE a new row under another attribute's id.
                    throw new UnprocessableEntityHttpException(sprintf(
                        'AttributeOption id %d does not belong to this AttributeDefinition.',
                        $id,
                    ));
                }
                $existing = $existingById[$id];
                $existing->setValue($item->value);
                $existing->setSortOrder($item->sortOrder);
                $seenIds[$id] = true;
                continue;
            }

            // New option: id is null. Build it via addOption so the
            // owning-side FK stays consistent on both sides.
            $new = new AttributeOption();
            $new->setValue($item->value);
            $new->setSortOrder($item->sortOrder);
            $attribute->addOption($new);
        }

        // Drop options the recruiter removed from the dropdown.
        // removeElement + orphanRemoval = single DELETE on flush.
        foreach ($existingById as $id => $current) {
            if (!isset($seenIds[$id])) {
                $attribute->removeOption($current);
            }
        }
    }

    public function delete(AttributeDefinition $attribute): void
    {
        $em = $this->attributeDefinitionRepository->getEntityManager();
        $em->remove($attribute);
        $em->flush();
    }

    public function createCategory(CreateAttributeCategoryDto $dto): AttributeCategory
    {
        if ($this->attributeCategoryRepository->findOneByCode($dto->code) !== null) {
            throw new ConflictHttpException('Category with this code already exists.');
        }
        $category = new AttributeCategory();
        $category->setCode($dto->code);
        $category->setName($dto->name);
        $category->setSortOrder($dto->sortOrder);
        $this->attributeCategoryRepository
            ->getEntityManager()
            ->persist($category);
        $this->attributeCategoryRepository
            ->getEntityManager()
            ->flush();
        return $category;
    }
}