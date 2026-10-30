<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Money;
use PHPUnit\Framework\TestCase;

/**
 * T1 - RF-19: montos exactos en pesos chilenos enteros, sin flotantes.
 */
final class MoneyTest extends TestCase
{
    public function test_creates_money_from_integer_pesos(): void
    {
        $money = Money::fromPesos(1990);

        $this->assertSame(1990, $money->pesos());
    }

    public function test_rejects_float_amounts(): void
    {
        $this->expectException(\TypeError::class);

        Money::fromPesos(19.9);
    }

    public function test_adds_two_amounts(): void
    {
        $sum = Money::fromPesos(1000)->add(Money::fromPesos(250));

        $this->assertSame(1250, $sum->pesos());
    }

    public function test_subtracts_two_amounts(): void
    {
        $difference = Money::fromPesos(1000)->subtract(Money::fromPesos(250));

        $this->assertSame(750, $difference->pesos());
    }

    public function test_multiplies_by_quantity(): void
    {
        $total = Money::fromPesos(990)->multiplyBy(3);

        $this->assertSame(2970, $total->pesos());
    }

    public function test_detects_zero_and_negative(): void
    {
        $this->assertTrue(Money::fromPesos(0)->isZero());
        $this->assertFalse(Money::fromPesos(1)->isZero());
        $this->assertTrue(Money::fromPesos(-1)->isNegative());
        $this->assertFalse(Money::fromPesos(0)->isNegative());
    }

    public function test_compares_two_amounts(): void
    {
        $this->assertTrue(Money::fromPesos(500)->equals(Money::fromPesos(500)));
        $this->assertFalse(Money::fromPesos(500)->equals(Money::fromPesos(501)));
        $this->assertTrue(Money::fromPesos(500)->isGreaterThan(Money::fromPesos(499)));
    }
}
