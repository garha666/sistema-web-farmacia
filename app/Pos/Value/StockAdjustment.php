<?php

declare(strict_types=1);

namespace App\Pos\Value;

/**
 * Resultado de evaluar un ajuste de stock (RF-73, RF-74).
 *
 * Conserva si el ajuste se acepta, la existencia resultante, la existencia
 * disponible al evaluar y, si se rechaza, el código del motivo. El código se
 * traduce en la capa de presentación.
 */
final readonly class StockAdjustment
{
    public const INVALID_QUANTITY = 'invalid_quantity';

    public const REASON_REQUIRED = 'reason_required';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    private function __construct(
        private bool $accepted,
        private int $resultingStock,
        private int $available,
        private ?string $rejection,
    ) {}

    public static function accept(int $resultingStock, int $available): self
    {
        return new self(true, $resultingStock, $available, null);
    }

    /**
     * Un rechazo no cambia la existencia: la resultante queda igual a la
     * disponible.
     */
    public static function reject(string $rejection, int $available): self
    {
        return new self(false, $available, $available, $rejection);
    }

    public function accepted(): bool
    {
        return $this->accepted;
    }

    public function resultingStock(): int
    {
        return $this->resultingStock;
    }

    public function available(): int
    {
        return $this->available;
    }

    public function rejection(): ?string
    {
        return $this->rejection;
    }
}
