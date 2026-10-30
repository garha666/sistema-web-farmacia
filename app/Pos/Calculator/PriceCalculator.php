<?php

declare(strict_types=1);

namespace App\Pos\Calculator;

use App\Pos\Money;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\Discount;
use App\Pos\Value\ReceiptTotals;

/**
 * Cálculos de precio del POS (RF-4, RF-12, RF-19).
 *
 * Los precios de lista incluyen IVA. Todo monto se cierra a peso entero
 * (round half-up) en el mismo cálculo donde aparece el decimal.
 */
final class PriceCalculator
{
    public function lineSubtotal(CartLine $line): Money
    {
        return $line->unitPrice()->multiplyBy($line->quantity());
    }

    public function lineDiscount(CartLine $line): Money
    {
        if ($line->discount() === null) {
            return Money::fromPesos(0);
        }

        return $line->discount()->amountFor($this->lineSubtotal($line));
    }

    public function subtotal(Cart $cart): Money
    {
        $subtotal = Money::fromPesos(0);

        foreach ($cart->lines() as $line) {
            $subtotal = $subtotal->add($this->lineSubtotal($line));
        }

        return $subtotal;
    }

    public function totals(Cart $cart, int $ivaRate, ?Discount $globalDiscount = null): ReceiptTotals
    {
        $subtotal = Money::fromPesos(0);
        $lineDiscounts = Money::fromPesos(0);

        foreach ($cart->lines() as $line) {
            $subtotal = $subtotal->add($this->lineSubtotal($line));
            $lineDiscounts = $lineDiscounts->add($this->lineDiscount($line));
        }

        $afterLines = $subtotal->subtract($lineDiscounts);

        $global = $globalDiscount === null
            ? Money::fromPesos(0)
            : $globalDiscount->amountFor($afterLines);

        $total = $afterLines->subtract($global);

        if ($total->isNegative()) {
            $total = Money::fromPesos(0);
        }

        $breakdown = $this->ivaBreakdown($total->pesos(), $ivaRate);

        return new ReceiptTotals(
            subtotal: $subtotal,
            subtotalAfterLineDiscounts: $afterLines,
            lineDiscounts: $lineDiscounts,
            globalDiscount: $global,
            net: Money::fromPesos($breakdown['net']),
            iva: Money::fromPesos($breakdown['iva']),
            total: $total,
        );
    }

    /**
     * @return array{net: int, iva: int}
     */
    public function ivaBreakdown(int $grossTotal, int $ivaRate): array
    {
        if ($grossTotal <= 0) {
            return ['net' => 0, 'iva' => 0];
        }

        $net = (int) floor(($grossTotal * 100) / (100 + $ivaRate) + 0.5);

        return ['net' => $net, 'iva' => $grossTotal - $net];
    }

    public function roundToPeso(float $amount): int
    {
        return (int) floor($amount + 0.5);
    }
}
