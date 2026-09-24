<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\Profile;
use App\Entity\ProfileAttribute;
use App\Enum\AccessRuleOperator;
use App\Enum\AttributeDataType;
use App\Repository\ProfileAttributeRepository;

/**
 * Decides whether a candidate profile satisfies a Position's access rules.
 *
 * Per the assignment:
 *  - A public position is accessible to all authenticated users.
 *  - A restricted position requires every rule to be satisfied.
 *  - If a rule references an attribute the candidate has not filled out,
 *    the rule is treated as NOT satisfied (the candidate has not yet met
 *    the requirement).
 */
final readonly class PositionAccessEvaluator
{
    public function __construct(
        private ProfileAttributeRepository $profileAttributeRepository,
    ) {
    }

    public function isAccessible(Position $position, ?Profile $profile): bool
    {
        if ($position->isPublic()) {
            return true;
        }

        if ($profile === null) {
            return false;
        }

        foreach ($position->getAccessRules() as $rule) {
            if (!$this->satisfies($profile, $rule)) {
                return false;
            }
        }
        return true;
    }

    private function satisfies(Profile $profile, PositionAccessRule $rule): bool
    {
        $definition = $rule->getAttributeDefinition();
        $row = $this->profileAttributeRepository->findOneByProfileAndDefinition($profile, $definition);
        if ($row === null) {
            return false;
        }

        return match ($rule->getOperator()) {
            AccessRuleOperator::Equals => $this->extractValue($row, $definition) == $rule->getValue(),
            AccessRuleOperator::NotEquals => $this->extractValue($row, $definition) != $rule->getValue(),
            AccessRuleOperator::GreaterThan => (float) $this->extractValue($row, $definition) > (float) $rule->getValue(),
            AccessRuleOperator::GreaterThanOrEqual => (float) $this->extractValue($row, $definition) >= (float) $rule->getValue(),
            AccessRuleOperator::LessThan => (float) $this->extractValue($row, $definition) < (float) $rule->getValue(),
            AccessRuleOperator::LessThanOrEqual => (float) $this->extractValue($row, $definition) <= (float) $rule->getValue(),
            AccessRuleOperator::OneOf => in_array($this->extractValue($row, $definition), (array) $rule->getValue(), true),
            AccessRuleOperator::Contains => is_string($this->extractValue($row, $definition))
                && str_contains(mb_strtolower($this->extractValue($row, $definition)), mb_strtolower((string) $rule->getValue())),
            AccessRuleOperator::Before => (string) $this->extractValue($row, $definition) < (string) $rule->getValue(),
            AccessRuleOperator::After => (string) $this->extractValue($row, $definition) > (string) $rule->getValue(),
        };
    }

    /**
     * Returns the value stored in the row as a scalar suitable for comparison.
     * For OneOfMany we return the option id (numeric); for everything else,
     * the typed column value.
     */
    private function extractValue(ProfileAttribute $row, AttributeDefinition $definition): mixed
    {
        return match ($definition->getDataType()) {
            AttributeDataType::String => $row->getStringValue(),
            AttributeDataType::Text => $row->getMarkdownText(),
            AttributeDataType::Numeric => $row->getNumericValue(),
            AttributeDataType::Date => $row->getDateValue()?->format('Y-m-d'),
            AttributeDataType::Period => $row->getPeriodStart()?->format('Y-m-d'),
            AttributeDataType::Boolean => $row->getBooleanValue(),
            AttributeDataType::Image => $row->getImageUrl(),
            AttributeDataType::OneOfMany => $row->getSelectedOption()?->getId(),
        };
    }
}