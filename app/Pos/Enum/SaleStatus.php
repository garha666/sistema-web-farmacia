<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum SaleStatus: string
{
    case confirmed = 'confirmed';
    case voided = 'voided';
}
