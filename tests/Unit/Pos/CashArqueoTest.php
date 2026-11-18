<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Calculator\CashArqueo;
use App\Pos\Enum\CashDifference;
use App\Pos\Value\ArqueoResult;
use PHPUnit\Framework\TestCase;

/**
 * T12 - RF-59: efectivo esperado, diferencia y su sentido.
 *
 * La calculadora es pura: recibe pesos enteros y una fecha ya local no hace
 * falta porque no depende del reloj.
 */
final class CashArqueoTest extends TestCase
{
    public function test_expected_cash_adds_sales_and_subtracts_voids(): void
    {
        $this->assertSame(56000, (new CashArqueo)->expected(50000, 10000, 4000));
    }

    public function test_expected_cash_without_sales_or_voids_is_the_opening_amount(): void
    {
        $this->assertSame(50000, (new CashArqueo)->expected(50000, 0, 0));
    }

    public function test_difference_is_the_declared_amount_minus_the_expected_cash(): void
    {
        $arqueo = new CashArqueo;

        $this->assertSame(2000, $arqueo->difference(50000, 52000));
        $this->assertSame(-2000, $arqueo->difference(50000, 48000));
        $this->assertSame(0, $arqueo->difference(50000, 50000));
    }

    public function test_the_sign_names_the_sense_of_the_difference(): void
    {
        $arqueo = new CashArqueo;

        $this->assertSame(CashDifference::balanced, $arqueo->sign(0));
        $this->assertSame(CashDifference::over, $arqueo->sign(1));
        $this->assertSame(CashDifference::short, $arqueo->sign(-1));
    }

    public function test_the_result_keeps_expected_declared_difference_and_kind(): void
    {
        $result = new ArqueoResult(expected: 50000, declared: 47000);

        $this->assertSame(50000, $result->expected());
        $this->assertSame(47000, $result->declared());
        $this->assertSame(-3000, $result->difference());
        $this->assertSame(CashDifference::short, $result->kind());
        $this->assertFalse($result->isBalanced());
    }

    public function test_a_result_without_difference_is_balanced(): void
    {
        $result = new ArqueoResult(expected: 50000, declared: 50000);

        $this->assertSame(0, $result->difference());
        $this->assertSame(CashDifference::balanced, $result->kind());
        $this->assertTrue($result->isBalanced());
    }
}
