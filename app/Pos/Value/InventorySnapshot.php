<?php

declare(strict_types=1);

namespace App\Pos\Value;

/**
 * Resumen del inventario para el panel del Bodeguero (RF-63).
 *
 * Cuatro cifras: productos activos, bajo el mínimo, sin stock y movimientos
 * registrados durante el día.
 */
final readonly class InventorySnapshot
{
    public function __construct(
        private int $active,
        private int $belowMinimum,
        private int $outOfStock,
        private int $movementsToday,
    ) {}

    public function active(): int
    {
        return $this->active;
    }

    public function belowMinimum(): int
    {
        return $this->belowMinimum;
    }

    public function outOfStock(): int
    {
        return $this->outOfStock;
    }

    public function movementsToday(): int
    {
        return $this->movementsToday;
    }
}
