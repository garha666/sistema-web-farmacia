<?php

declare(strict_types=1);

namespace App\Pos\Value;

/**
 * Faltante de stock de una línea (RF-27, RF-29).
 */
final readonly class Shortage
{
    private int $missing;

    public function __construct(
        private int $productId,
        private int $requested,
        private int $available,
    ) {
        $this->missing = max(0, $requested - $available);
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function requested(): int
    {
        return $this->requested;
    }

    public function available(): int
    {
        return $this->available;
    }

    public function missing(): int
    {
        return $this->missing;
    }
}
