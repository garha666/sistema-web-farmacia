<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Enum\PaymentMethod;

/**
 * Monto y cantidad vendidos con un medio de pago (RF-56, RF-59, RF-61).
 */
final readonly class PaymentBreakdown
{
    public function __construct(
        private PaymentMethod $method,
        private int $amount,
        private int $count,
    ) {}

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function count(): int
    {
        return $this->count;
    }
}
