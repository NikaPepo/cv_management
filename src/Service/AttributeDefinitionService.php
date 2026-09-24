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
            // The assignment requires that "Add new Position attribute should appear empty
            // in old CVs" — we don't cascade edits. We just replace the dropdown options
            // for OneOfMany; CVs read selected options by FK with SET NULL, so old CVs
            // render as empty until candidate re-selects.
            if ($attribute->getDataType() !== AttributeDataType::OneOfMany) {
                throw new UnprocessableEntityHttpException('Only OneOfMany attributes accept options.');
            }
            foreach ($attribute->getOptions() as $existing) {
                $this->attributeDefinitionRepository->getEntityManager()->remove($existing);
            }
            foreach ($dto->options as $opt) {
                $option = new AttributeOption();
                $option->setAttributeDefinition($attribute);
                $option->setValue($opt->value);
                $option->setSortOrder($opt->sortOrder);
                $attribute->getOptions()->add($option);
            }
        }

        $this->attributeDefinitionRepository->save($attribute);
        return $attribute;
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