<?php

declare(strict_types=1);

namespace App\Pos\Calculator;

use App\Pos\Enum\CashDifference;

/**
 * Arqueo de caja (RF-59).
 *
 * Función pura sobre pesos enteros: no lee reloj, sesión ni base de datos.
 * `expected()` recibe el efectivo del turno ya agregado por el llamador.
 */
final class CashArqueo
{
    public function expected(int $openingAmount, int $cashSales, int $cashVoids): int
    {
        return $openingAmount + $cashSales - $cashVoids;
    }

    public function difference(int $expected, int $declared): int
    {
        return $declared - $expected;
    }

    public function sign(int $difference): CashDifference
    {
        return match (true) {
            $difference > 0 => CashDifference::over,
            $difference < 0 => CashDifference::short,
            default => CashDifference::balanced,
        };
    }
}
