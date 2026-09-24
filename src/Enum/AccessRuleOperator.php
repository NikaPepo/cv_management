<?php

declare(strict_types=1);

namespace App\Enum;

use App\Enum\AttributeDataType;

/**
 * The operators an access rule may use. The set of legal operators for
 * a given attribute depends on its dataType; see isCompatibleWith().
 */
enum AccessRuleOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'ne';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case OneOf = 'in';
    case Contains = 'contains';
    case Before = 'before';
    case After = 'after';

    /** @return AccessRuleOperator[] */
    public static function allowedFor(AttributeDataType $type): array
    {
        return match ($type) {
            AttributeDataType::Numeric => [
                self::Equals,
                self::NotEquals,
                self::GreaterThan,
                self::GreaterThanOrEqual,
                self::LessThan,
                self::LessThanOrEqual,
            ],
            AttributeDataType::Boolean => [self::Equals],
            AttributeDataType::OneOfMany => [self::Equals, self::NotEquals, self::OneOf],
            AttributeDataType::Date, AttributeDataType::Period => [
                self::Before,
                self::After,
                self::Equals,
            ],
            AttributeDataType::String, AttributeDataType::Text => [
                self::Equals,
                self::NotEquals,
                self::Contains,
            ],
            AttributeDataType::Image => [self::Equals, self::NotEquals],
        };
    }

    public function isCompatibleWith(AttributeDataType $type): bool
    {
        return in_array($this, self::allowedFor($type), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}