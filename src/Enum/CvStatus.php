<?php

declare(strict_types=1);

namespace App\Enum;

enum CvStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
}