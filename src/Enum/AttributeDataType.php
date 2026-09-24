<?php

declare(strict_types=1);

namespace App\Enum;

enum AttributeDataType: string
{
    case String = 'string';
    case Text = 'text';
    case Image = 'image';
    case Numeric = 'numeric';
    case Date = 'date';
    case Period = 'period';
    case Boolean = 'boolean';
    case OneOfMany = 'one_of_many';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}