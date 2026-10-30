<?php

declare(strict_types=1);

namespace App\Pos\Enum;

/**
 * Categoría de venta del producto (RF-7).
 */
enum SaleCategory: string
{
    case free = 'free';
    case prescription = 'prescription';
    case controlled = 'controlled';

    public function requiresPrescription(): bool
    {
        return $this !== self::free;
    }
}
