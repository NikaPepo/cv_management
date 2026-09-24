<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SetProfileAttributeDto;
use App\Entity\AttributeDefinition;
use App\Entity\Profile;
use App\Entity\ProfileAttribute;
use App\Enum\AttributeDataType;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\AttributeOptionRepository;
use App\Repository\ProfileAttributeRepository;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Writes the master value of an attribute for a candidate profile.
 * Edits made from a CV (in-place) call into this same service — that
 * is what makes the master-value principle work: there is exactly one
 * row per (profile, attribute), edited from anywhere.
 */
final readonly class ProfileAttributeService
{
    public function __construct(
        private ProfileAttributeRepository $profileAttributeRepository,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeOptionRepository $attributeOptionRepository,
    ) {
    }

    public function setValue(Profile $profile, SetProfileAttributeDto $dto): ProfileAttribute
    {
        $definition = $this->attributeDefinitionRepository->find($dto->attributeDefinitionId);
        if ($definition === null) {
            throw new UnprocessableEntityHttpException('attributeDefinitionId not found.');
        }

        $existing = $this->profileAttributeRepository->findOneByProfileAndDefinition($profile, $definition);

        if ($existing === null) {
            $existing = new ProfileAttribute();
            $existing->setProfile($profile);
            $existing->setAttributeDefinition($definition);
        } else {
            // Optimistic locking: the assignment requires version-based conflict detection.
            if ($dto->version !== null && $existing->getVersion() !== null && $existing->getVersion() !== $dto->version) {
                throw new ConflictHttpException('Attribute was modified by another session.');
            }
        }

        $this->writeTypedValue($existing, $definition, $dto);

        try {
            $this->profileAttributeRepository->save($existing);
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('Attribute was modified by another session.');
        }

        return $existing;
    }

    public function remove(Profile $profile, AttributeDefinition $definition): void
    {
        $existing = $this->profileAttributeRepository->findOneByProfileAndDefinition($profile, $definition);
        if ($existing === null) {
            return;
        }
        $em = $this->profileAttributeRepository->getEntityManager();
        $em->remove($existing);
        $em->flush();
    }

    private function writeTypedValue(ProfileAttribute $attribute, AttributeDefinition $definition, SetProfileAttributeDto $dto): void
    {
        // Always reset all typed columns first so a stale value from a previous
        // dataType (e.g., after admin re-typed the attribute) doesn't leak through.
        $attribute->setStringValue(null);
        $attribute->setMarkdownText(null);
        $attribute->setNumericValue(null);
        $attribute->setDateValue(null);
        $attribute->setPeriodStart(null);
        $attribute->setPeriodEnd(null);
        $attribute->setBooleanValue(null);
        $attribute->setImageUrl(null);
        $attribute->setSelectedOption(null);

        switch ($definition->getDataType()) {
            case AttributeDataType::String:
                $attribute->setStringValue($dto->stringValue);
                break;
            case AttributeDataType::Text:
                $attribute->setMarkdownText($dto->markdownText);
                break;
            case AttributeDataType::Numeric:
                $attribute->setNumericValue($dto->numericValue);
                break;
            case AttributeDataType::Date:
                $attribute->setDateValue($this->parseDate($dto->dateValue));
                break;
            case AttributeDataType::Period:
                $attribute->setPeriodStart($this->parseDate($dto->periodStart));
                $attribute->setPeriodEnd($this->parseDate($dto->periodEnd));
                break;
            case AttributeDataType::Boolean:
                $attribute->setBooleanValue($dto->booleanValue);
                break;
            case AttributeDataType::Image:
                $attribute->setImageUrl($dto->imageUrl);
                break;
            case AttributeDataType::OneOfMany:
                if ($dto->selectedOptionId === null) {
                    throw new UnprocessableEntityHttpException('selectedOptionId required for OneOfMany.');
                }
                $option = $this->attributeOptionRepository->find($dto->selectedOptionId);
                if ($option === null || $option->getAttributeDefinition()->getId() !== $definition->getId()) {
                    throw new UnprocessableEntityHttpException('selectedOptionId does not belong to this attribute.');
                }
                $attribute->setSelectedOption($option);
                break;
        }
    }

    private function parseDate(?string $raw): ?\DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($date === false) {
            throw new UnprocessableEntityHttpException('Invalid date; expected YYYY-MM-DD.');
        }
        return $date;
    }
}