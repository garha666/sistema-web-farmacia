<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Enum\SaleCategory;
use App\Pos\Money;

/**
 * Línea del carrito. Congela precio y categoría al momento de agregar.
 */
final readonly class CartLine
{
    public function __construct(
        public ProductSnapshot $product,
        public int $quantity,
        public ?Discount $discount = null,
    ) {
    }

    public function productId(): int
    {
        return $this->product->id;
    }

    public function name(): string
    {
        return $this->product->name;
    }

    public function unitPrice(): Money
    {
        return $this->product->unitPrice;
    }

    public function category(): SaleCategory
    {
        return $this->product->category;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function discount(): ?Discount
    {
        return $this->discount;
    }

    public function withQuantity(int $quantity): self
    {
        return new self(product: $this->product, quantity: $quantity, discount: $this->discount);
    }

    public function withDiscount(?Discount $discount): self
    {
        return new self(product: $this->product, quantity: $this->quantity, discount: $discount);
    }
}
