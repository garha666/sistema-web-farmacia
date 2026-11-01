<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Enum\SaleCategory;
use App\Pos\Money;
use App\Pos\Policy\StockPolicy;
use App\Pos\Value\Cart;
use App\Pos\Value\ProductSnapshot;
use App\Pos\Value\Shortage;
use PHPUnit\Framework\TestCase;

/**
 * T5 - RF-3 (cantidad mayor a la existencia), RF-27 (sin sobreventa),
 * RF-29 (el stock cambió entre armar el carrito y confirmar).
 */
final class StockPolicyTest extends TestCase
{
    private StockPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new StockPolicy();
    }

    public function test_can_sell_when_stock_is_greater_than_requested(): void
    {
        $this->assertTrue($this->policy->canSell(available: 10, requested: 3));
    }

    public function test_can_sell_exactly_the_available_stock(): void
    {
        $this->assertTrue($this->policy->canSell(available: 3, requested: 3));
    }

    public function test_cannot_sell_more_than_available(): void
    {
        $this->assertFalse($this->policy->canSell(available: 2, requested: 3));
    }

    public function test_cannot_sell_when_stock_is_zero(): void
    {
        $this->assertFalse($this->policy->canSell(available: 0, requested: 1));
    }

    public function test_cannot_sell_a_non_positive_quantity(): void
    {
        $this->assertFalse($this->policy->canSell(available: 10, requested: 0));
        $this->assertFalse($this->policy->canSell(available: 10, requested: -2));
    }

    public function test_no_shortages_when_every_line_is_covered(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, stock: 10), 2)
            ->add($this->product(2, stock: 5), 1);

        $shortages = $this->policy->shortages($cart, [1 => 10, 2 => 5]);

        $this->assertSame([], $shortages);
    }

    public function test_shortage_reports_the_exact_missing_quantity(): void
    {
        $cart = Cart::empty()->add($this->product(1, stock: 10), 5);

        $shortages = $this->policy->shortages($cart, [1 => 3]);

        $this->assertCount(1, $shortages);
        $this->assertSame(1, $shortages[0]->productId());
        $this->assertSame(5, $shortages[0]->requested());
        $this->assertSame(3, $shortages[0]->available());
        $this->assertSame(2, $shortages[0]->missing());
    }

    public function test_reports_every_shortage_of_the_cart(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, stock: 10), 4)
            ->add($this->product(2, stock: 10), 2)
            ->add($this->product(3, stock: 10), 1);

        $shortages = $this->policy->shortages($cart, [1 => 4, 2 => 0, 3 => 0]);

        $this->assertCount(2, $shortages);
        $this->assertSame(2, $shortages[0]->productId());
        $this->assertSame(2, $shortages[0]->missing());
        $this->assertSame(3, $shortages[1]->productId());
        $this->assertSame(1, $shortages[1]->missing());
    }

    public function test_unknown_product_counts_as_zero_stock(): void
    {
        $cart = Cart::empty()->add($this->product(1, stock: 10), 1);

        $shortages = $this->policy->shortages($cart, []);

        $this->assertCount(1, $shortages);
        $this->assertSame(0, $shortages[0]->available());
        $this->assertSame(1, $shortages[0]->missing());
    }

    public function test_negative_stock_is_treated_as_zero(): void
    {
        $cart = Cart::empty()->add($this->product(1, stock: 10), 2);

        $shortages = $this->policy->shortages($cart, [1 => -5]);

        $this->assertSame(0, $shortages[0]->available());
        $this->assertSame(2, $shortages[0]->missing());
    }

    public function test_has_shortages_helper(): void
    {
        $cart = Cart::empty()->add($this->product(1, stock: 10), 3);

        $this->assertTrue($this->policy->hasShortages($cart, [1 => 2]));
        $this->assertFalse($this->policy->hasShortages($cart, [1 => 3]));
    }

    public function test_detects_products_whose_stock_changed_since_the_cart_was_built(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, stock: 10), 1)
            ->add($this->product(2, stock: 10), 1);

        $changed = $this->policy->detectChanges($cart, [1 => 7, 2 => 10]);

        $this->assertSame([1], $changed);
    }

    public function test_no_changes_when_live_stock_matches_the_snapshot(): void
    {
        $cart = Cart::empty()->add($this->product(1, stock: 10), 1);

        $this->assertSame([], $this->policy->detectChanges($cart, [1 => 10]));
    }

    public function test_detects_changes_against_an_explicit_snapshot(): void
    {
        $cart = Cart::empty()->add($this->product(1, stock: 10), 1);

        $this->assertSame([1], $this->policy->detectChanges($cart, [1 => 7], [1 => 10]));
        $this->assertSame([], $this->policy->detectChanges($cart, [1 => 10], [1 => 10]));
    }

    public function test_shortage_exposes_the_line_product_id_as_int(): void
    {
        $shortage = new Shortage(productId: 7, requested: 5, available: 1);

        $this->assertSame(7, $shortage->productId());
        $this->assertSame(4, $shortage->missing());
    }

    private function product(int $id, int $stock): ProductSnapshot
    {
        return new ProductSnapshot(
            id: $id,
            name: "Product {$id}",
            unitPrice: Money::fromPesos(1000),
            category: SaleCategory::free,
            availableStock: $stock,
        );
    }
}
