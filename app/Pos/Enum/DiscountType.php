<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum DiscountType: string
{
    case percent = 'percent';
    case amount = 'amount';
}
