<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Enum\DiscountOrigin;
use App\Pos\Enum\DiscountType;
use App\Pos\Money;

/**
 * Descuento por porcentaje o por monto (RF-13).
 *
 * El porcentaje es el único dato decimal del dominio: se redondea una sola vez
 * al convertirlo en pesos, con round half-up.
 */
final readonly class Discount
{
    private function __construct(
        public DiscountType $type,
        public float $value,
        public string $reason,
        public DiscountOrigin $origin,
    ) {
    }

    public static function percent(
        float $percent,
        string $reason,
        DiscountOrigin $origin = DiscountOrigin::manual,
    ): self {
        return new self(DiscountType::percent, $percent, $reason, $origin);
    }

    public static function amount(
        Money $amount,
        string $reason,
        DiscountOrigin $origin = DiscountOrigin::manual,
    ): self {
        return new self(DiscountType::amount, (float) $amount->pesos(), $reason, $origin);
    }

    /**
     * Monto del descuento aplicado sobre una base, sin superar esa base.
     */
    public function amountFor(Money $base): Money
    {
        $pesos = $this->type === DiscountType::percent
            ? (int) floor(($base->pesos() * $this->value / 100) + 0.5)
            : (int) floor($this->value);

        $pesos = max(0, min($pesos, $base->pesos()));

        return Money::fromPesos($pesos);
    }
}
