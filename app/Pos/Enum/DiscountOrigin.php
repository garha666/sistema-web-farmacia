<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum DiscountOrigin: string
{
    case manual = 'manual';
    case convention = 'convention';
}
