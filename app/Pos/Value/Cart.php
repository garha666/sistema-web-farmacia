<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Exception\InvalidQuantity;
use App\Pos\Exception\ProductNotSellable;

/**
 * Carrito inmutable. Cada operación devuelve una instancia nueva.
 *
 * RF-2: agregar el mismo producto incrementa la cantidad, no duplica la línea.
 * RF-3: cantidad entera >= 1; cantidad 0 elimina la línea.
 * RF-5: el carrito vacío se puede detectar con isEmpty().
 */
final readonly class Cart
{
    /**
     * @param  array<int, CartLine>  $lines  indexado por id de producto
     */
    private function __construct(private array $lines)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function add(ProductSnapshot $product, int $quantity = 1): self
    {
        $this->assertValidQuantity($quantity);

        if (! $product->sellable) {
            throw new ProductNotSellable("Product {$product->id} is not sellable");
        }

        $lines = $this->lines;

        if (isset($lines[$product->id])) {
            $line = $lines[$product->id];
            $lines[$product->id] = $line->withQuantity($line->quantity + $quantity);

            return new self($lines);
        }

        $lines[$product->id] = new CartLine(product: $product, quantity: $quantity);

        return new self($lines);
    }

    public function changeQuantity(int $productId, int $quantity): self
    {
        if ($quantity === 0) {
            return $this->remove($productId);
        }

        $this->assertValidQuantity($quantity);

        if (! isset($this->lines[$productId])) {
            return $this;
        }

        $lines = $this->lines;
        $lines[$productId] = $lines[$productId]->withQuantity($quantity);

        return new self($lines);
    }

    public function remove(int $productId): self
    {
        if (! isset($this->lines[$productId])) {
            return $this;
        }

        $lines = $this->lines;
        unset($lines[$productId]);

        return new self($lines);
    }

    public function applyLineDiscount(int $productId, ?Discount $discount): self
    {
        if (! isset($this->lines[$productId])) {
            return $this;
        }

        $lines = $this->lines;
        $lines[$productId] = $lines[$productId]->withDiscount($discount);

        return new self($lines);
    }

    /**
     * @return CartLine[]
     */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(
            fn (CartLine $line): int => $line->quantity,
            $this->lines,
        ));
    }

    public function hasPrescriptionItems(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->product->requiresPrescription()) {
                return true;
            }
        }

        return false;
    }

    private function assertValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidQuantity("Quantity must be a positive integer, got {$quantity}");
        }
    }
}
