<?php

declare(strict_types=1);

namespace App\Pos\Value;

/**
 * Ventas de un día completo (RF-46, RF-52, RF-75).
 *
 * Separa las ventas confirmadas de las anuladas para que el monto vendido no
 * incluya lo que después se anuló. Sólo describe: no calcula ni consulta.
 */
final readonly class SalesDaySummary
{
    /**
     * @param  array<int, PaymentBreakdown>  $breakdown
     */
    public function __construct(
        private int $salesCount,
        private int $totalSales,
        private int $voidCount,
        private int $totalVoids,
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
