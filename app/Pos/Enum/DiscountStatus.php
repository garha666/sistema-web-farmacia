<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum DiscountStatus: string
{
    case applied = 'applied';
    case requiresSupervisor = 'requires_supervisor';
    case rejected = 'rejected';
}
