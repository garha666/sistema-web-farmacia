<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Money;

/**
 * Totales de una venta (RF-4, RF-12, RF-19).
 *
 * Invariantes:
 *  - total = subtotal - descuentos
 *  - neto + IVA = total
 */
final readonly class ReceiptTotals
{
    public function __construct(
        private Money $subtotal,
        private Money $subtotalAfterLineDiscounts,
        private Money $lineDiscounts,
        private Money $globalDiscount,
        private Money $net,
        private Money $iva,
        private Money $total,
    ) {
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function subtotalAfterLineDiscounts(): Money
    {
        return $this->subtotalAfterLineDiscounts;
    }

    public function lineDiscounts(): Money
    {
        return $this->lineDiscounts;
    }

    public function globalDiscount(): Money
    {
        return $this->globalDiscount;
    }

    public function discounts(): Money
    {
        return $this->lineDiscounts->add($this->globalDiscount);
    }

    public function net(): Money
    {
        return $this->net;
    }

    public function iva(): Money
    {
        return $this->iva;
    }

    public function total(): Money
    {
        return $this->total;
    }
}
