<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Enum\DiscountStatus;
use App\Pos\Money;

/**
 * Resultado de evaluar un descuento (RF-13 a RF-18).
 */
final readonly class DiscountDecision
{
    private function __construct(
        private DiscountStatus $status,
        private Money $amount,
        private ?string $reason,
        private ?Discount $discount,
    ) {
    }

    public static function applied(Money $amount, Discount $discount): self
    {
        return new self(DiscountStatus::applied, $amount, null, $discount);
    }

    public static function forSupervisor(Money $amount, Discount $discount): self
    {
        return new self(DiscountStatus::requiresSupervisor, $amount, 'above_cap', $discount);
    }

    public static function rejected(string $reason): self
    {
        return new self(DiscountStatus::rejected, Money::fromPesos(0), $reason, null);
    }

    public function status(): DiscountStatus
    {
        return $this->status;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function discount(): ?Discount
    {
        return $this->discount;
    }

    public function isApplied(): bool
    {
        return $this->status === DiscountStatus::applied;
    }

    public function requiresSupervisor(): bool
    {
        return $this->status === DiscountStatus::requiresSupervisor;
    }

    public function isRejected(): bool
    {
        return $this->status === DiscountStatus::rejected;
    }
}
