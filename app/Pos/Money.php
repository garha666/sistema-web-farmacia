<?php

declare(strict_types=1);

namespace App\Pos;

/**
 * Monto en pesos chilenos enteros.
 *
 * El dominio nunca usa flotantes: los porcentajes se redondean una sola vez
 * antes de construir un Money (ver PriceCalculator::roundToPeso).
 */
final readonly class Money
{
    private function __construct(private int $pesos)
    {
    }

    public static function fromPesos(int $pesos): self
    {
        return new self($pesos);
    }

    public function pesos(): int
    {
        return $this->pesos;
    }

    public function add(self $other): self
    {
        return new self($this->pesos + $other->pesos);
    }

    public function subtract(self $other): self
    {
        return new self($this->pesos - $other->pesos);
    }

    public function multiplyBy(int $quantity): self
    {
        return new self($this->pesos * $quantity);
    }

    public function isZero(): bool
    {
        return $this->pesos === 0;
    }

    public function isNegative(): bool
    {
        return $this->pesos < 0;
    }

    public function equals(self $other): bool
    {
        return $this->pesos === $other->pesos;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->pesos > $other->pesos;
    }
}
