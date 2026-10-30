<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Calculator\PriceCalculator;
use App\Pos\Enum\SaleCategory;
use App\Pos\Money;
use App\Pos\Value\Cart;
use App\Pos\Value\Discount;
use App\Pos\Value\ProductSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T2 - RF-4 (totales en vivo), RF-12 (precio con IVA incluido y desglose),
 * RF-19 (montos exactos en pesos enteros y redondeo).
 */
final class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PriceCalculator();
    }

    public function test_line_subtotal_is_unit_price_times_quantity(): void
    {
        $cart = Cart::empty()->add($this->product(1, 1990), 3);

        $this->assertSame(5970, $this->calculator->lineSubtotal($cart->lines()[0])->pesos());
    }

    public function test_totals_without_discount_keep_total_equal_to_subtotal(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, 1990), 2)
            ->add($this->product(2, 500), 1);

        $totals = $this->calculator->totals($cart, 19);

        $this->assertSame(4480, $totals->subtotal()->pesos());
        $this->assertSame(0, $totals->discounts()->pesos());
        $this->assertSame(4480, $totals->total()->pesos());
    }

    public function test_iva_breakdown_of_1190_is_1000_net_and_190_tax(): void
    {
        $breakdown = $this->calculator->ivaBreakdown(1190, 19);

        $this->assertSame(1000, $breakdown['net']);
        $this->assertSame(190, $breakdown['iva']);
    }

    public function test_iva_breakdown_rounds_to_exact_peso(): void
    {
        $breakdown = $this->calculator->ivaBreakdown(1000, 19);

        $this->assertSame(840, $breakdown['net']);
        $this->assertSame(160, $breakdown['iva']);
        $this->assertSame(1000, $breakdown['net'] + $breakdown['iva']);
    }

    public function test_totals_expose_net_and_iva_that_add_up_to_total(): void
    {
        $cart = Cart::empty()->add($this->product(1, 999), 3);

        $totals = $this->calculator->totals($cart, 19);

        $this->assertSame(2997, $totals->total()->pesos());
        $this->assertSame($totals->net()->pesos(), $totals->total()->pesos() - $totals->iva()->pesos());
    }

    public function test_percent_discount_on_line(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, 1000), 2)
            ->applyLineDiscount(1, Discount::percent(10, 'Convenio interno'));

        $totals = $this->calculator->totals($cart, 19);

        $this->assertSame(200, $totals->discounts()->pesos());
        $this->assertSame(1800, $totals->total()->pesos());
    }

    public function test_amount_discount_on_line_cannot_exceed_line_subtotal(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, 1000), 1)
            ->applyLineDiscount(1, Discount::amount(Money::fromPesos(5000), 'Ajuste'));

        $totals = $this->calculator->totals($cart, 19);

        $this->assertSame(1000, $totals->discounts()->pesos());
        $this->assertSame(0, $totals->total()->pesos());
    }

    public function test_global_discount_applies_after_line_discounts(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, 1000), 2)
            ->applyLineDiscount(1, Discount::percent(10, 'Convenio interno'));

        $totals = $this->calculator->totals(
            $cart,
            19,
            Discount::percent(10, 'Descuento de caja'),
        );

        // 2000 - 200 (línea) = 1800; 10% global = 180; total 1620.
        $this->assertSame(1800, $totals->subtotalAfterLineDiscounts()->pesos());
        $this->assertSame(380, $totals->discounts()->pesos());
        $this->assertSame(1620, $totals->total()->pesos());
    }

    public function test_total_never_becomes_negative(): void
    {
        $cart = Cart::empty()->add($this->product(1, 1000), 1);

        $totals = $this->calculator->totals(
            $cart,
            19,
            Discount::amount(Money::fromPesos(9999), 'Descuento excesivo'),
        );

        $this->assertSame(0, $totals->total()->pesos());
        $this->assertFalse($totals->total()->isNegative());
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $lines
     */
    #[DataProvider('oddAmountsProvider')]
    public function test_total_equals_lines_minus_discounts_for_odd_amounts(array $lines): void
    {
        $cart = Cart::empty();
        $expectedSubtotal = 0;

        foreach ($lines as $index => [$price, $quantity]) {
            $cart = $cart->add($this->product($index + 1, $price), $quantity);
            $expectedSubtotal += $price * $quantity;
        }

        $totals = $this->calculator->totals($cart, 19);
        $sumOfLines = array_sum(array_map(
            fn ($line) => $this->calculator->lineSubtotal($line)->pesos(),
            $cart->lines(),
        ));

        $this->assertSame($expectedSubtotal, $sumOfLines);
        $this->assertSame($sumOfLines, $totals->subtotal()->pesos());
        $this->assertSame(
            $totals->subtotal()->pesos() - $totals->discounts()->pesos(),
            $totals->total()->pesos(),
        );
    }

    /**
     * @return array<string, array{0: array<int, array{0: int, 1: int}>}>
     */
    public static function oddAmountsProvider(): array
    {
        return [
            'un solo peso' => [[[1, 1]]],
            'precio con decimales aparentes' => [[[999, 1]]],
            'tres líneas raras' => [[[333, 3], [777, 1], [1490, 2]]],
            'cantidad alta' => [[[1234, 7]]],
        ];
    }

    public function test_round_to_peso_is_half_up(): void
    {
        $this->assertSame(1, $this->calculator->roundToPeso(0.5));
        $this->assertSame(1, $this->calculator->roundToPeso(1.4));
        $this->assertSame(2, $this->calculator->roundToPeso(1.5));
        $this->assertSame(3, $this->calculator->roundToPeso(2.5));
        $this->assertSame(0, $this->calculator->roundToPeso(0.4));
    }

    private function product(int $id, int $price): ProductSnapshot
    {
        return new ProductSnapshot(
            id: $id,
            name: "Product {$id}",
            unitPrice: Money::fromPesos($price),
            category: SaleCategory::free,
            availableStock: 100,
        );
    }
}
