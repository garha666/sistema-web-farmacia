<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Enum\SaleCategory;
use App\Pos\Exception\InvalidQuantity;
use App\Pos\Exception\ProductNotSellable;
use App\Pos\Value\Cart;
use App\Pos\Value\ProductSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * T1 - RF-2 (agregar e incrementar), RF-3 (cantidad y quitar), RF-5 (carrito vacío).
 */
final class CartTest extends TestCase
{
    public function test_new_cart_is_empty(): void
    {
        $cart = Cart::empty();

        $this->assertTrue($cart->isEmpty());
        $this->assertCount(0, $cart->lines());
    }

    public function test_adds_a_product_with_quantity_one(): void
    {
        $cart = Cart::empty()->add($this->product(1));

        $this->assertFalse($cart->isEmpty());
        $this->assertCount(1, $cart->lines());
        $this->assertSame(1, $cart->lines()[0]->quantity());
    }

    public function test_adding_same_product_increments_quantity_instead_of_duplicating(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1))
            ->add($this->product(1));

        $this->assertCount(1, $cart->lines());
        $this->assertSame(2, $cart->lines()[0]->quantity());
    }

    public function test_adding_different_products_creates_two_lines(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1))
            ->add($this->product(2));

        $this->assertCount(2, $cart->lines());
    }

    public function test_rejects_non_positive_quantity(): void
    {
        $this->expectException(InvalidQuantity::class);

        Cart::empty()->add($this->product(1), 0);
    }

    public function test_rejects_fractional_quantity(): void
    {
        $this->expectException(\TypeError::class);

        Cart::empty()->add($this->product(1), 1.5);
    }

    public function test_rejects_product_that_is_not_sellable(): void
    {
        $this->expectException(ProductNotSellable::class);

        Cart::empty()->add($this->product(3, sellable: false));
    }

    public function test_changes_quantity_of_an_existing_line(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1))
            ->changeQuantity(1, 5);

        $this->assertSame(5, $cart->lines()[0]->quantity());
    }

    public function test_changing_quantity_to_zero_removes_the_line(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1))
            ->changeQuantity(1, 0);

        $this->assertTrue($cart->isEmpty());
    }

    public function test_removes_a_line(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1))
            ->add($this->product(2))
            ->remove(1);

        $this->assertCount(1, $cart->lines());
        $this->assertSame(2, $cart->lines()[0]->productId());
    }

    public function test_removing_a_missing_line_does_not_fail(): void
    {
        $cart = Cart::empty()->add($this->product(1))->remove(99);

        $this->assertCount(1, $cart->lines());
    }

    public function test_cart_is_immutable(): void
    {
        $original = Cart::empty();
        $original->add($this->product(1));

        $this->assertTrue($original->isEmpty());
    }

    public function test_keeps_category_and_price_of_the_snapshot(): void
    {
        $cart = Cart::empty()->add($this->product(1, price: 1490, category: SaleCategory::prescription));
        $line = $cart->lines()[0];

        $this->assertSame(1490, $line->unitPrice()->pesos());
        $this->assertSame(SaleCategory::prescription, $line->category());
    }

    private function product(
        int $id,
        int $price = 1000,
        bool $sellable = true,
        SaleCategory $category = SaleCategory::free
    ): ProductSnapshot {
        return new ProductSnapshot(
            id: $id,
            name: "Product {$id}",
            unitPrice: \App\Pos\Money::fromPesos($price),
            category: $category,
            availableStock: 10,
            sellable: $sellable,
        );
    }
}
