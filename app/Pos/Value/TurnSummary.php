<?php

declare(strict_types=1);

namespace App\Pos\Value;

/**
 * Resumen de un turno de caja (RF-56, RF-59, RF-61).
 *
 * Sólo describe lo que ya ocurrió: no calcula ni consulta. El desglose por
 * medio de pago corresponde a las ventas confirmadas, mientras que las
 * anulaciones se informan aparte para no inflar el total cobrado. `cashSales`
 * es el efectivo cobrado —incluidas las ventas luego anuladas— y `cashVoids`
 * el efectivo devuelto, para que el arqueo descuente cada anulación una sola
 * vez.
 */
final readonly class TurnSummary
{
    /**
     * @param  array<int, PaymentBreakdown>  $breakdown
     */
    public function __construct(
        private int $salesCount,
        private int $totalSales,
        private int $voidCount,
        private int $totalVoids,
        private int $cashSales,
        private int $cashVoids,
        private array $breakdown,
    ) {}

    public function salesCount(): int
    {
        return $this->salesCount;
    }

    public function totalSales(): int
    {
        return $this->totalSales;
    }

    public function voidCount(): int
    {
        return $this->voidCount;
    }

    public function totalVoids(): int
    {
        return $this->totalVoids;
    }

    public function cashSales(): int
    {
        return $this->cashSales;
    }

    public function cashVoids(): int
    {
        return $this->cashVoids;
    }

    /**
     * @return array<int, PaymentBreakdown>
     */
    public function breakdown(): array
    {
        return $this->breakdown;
    }

    public function hasSales(): bool
    {
        return $this->salesCount > 0;
    }
}
