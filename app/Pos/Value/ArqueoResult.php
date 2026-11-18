<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Enum\CashDifference;

/**
 * Resultado del arqueo de un turno (RF-59).
 *
 * Conserva lo esperado, lo declarado, la diferencia con signo y su sentido,
 * de modo que la vista y el acta del cierre muestren exactamente lo mismo.
 */
final readonly class ArqueoResult
{
    private int $difference;

    private CashDifference $kind;

    public function __construct(
        private int $expected,
        private int $declared,
    ) {
        $this->difference = $declared - $expected;
        $this->kind = $this->difference > 0
            ? CashDifference::over
            : ($this->difference < 0 ? CashDifference::short : CashDifference::balanced);
    }

    public function expected(): int
    {
        return $this->expected;
    }

    public function declared(): int
    {
        return $this->declared;
    }

    public function difference(): int
    {
        return $this->difference;
    }

    public function kind(): CashDifference
    {
        return $this->kind;
    }

    public function isBalanced(): bool
    {
        return $this->kind === CashDifference::balanced;
    }
}
