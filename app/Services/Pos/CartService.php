<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Product;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\ProductSnapshot;

/**
 * Carrito de la caja, persistido en sesión (RF-6).
 *
 * En sesión sólo se guarda id de producto y cantidad; el precio y el stock se
 * releen de la base en cada request para no vender con datos viejos.
 */
final class CartService
{
    private const SESSION_KEY = 'pos.cart';

    public function load(): Cart
    {
        $stored = session()->get(self::SESSION_KEY, []);

        if (! is_array($stored) || $stored === []) {
            return Cart::empty();
        }

        $products = Product::query()
            ->whereIn('id', array_map('intval', array_keys($stored)))
            ->get()
            ->keyBy('id');

        $cart = Cart::empty();

        foreach ($stored as $productId => $quantity) {
            $product = $products->get((int) $productId);

            if ($product === null || ! $product->sellable) {
                continue;
            }

            $cart = $cart->add($this->snapshot($product), max(1, (int) $quantity));
        }

        return $cart;
    }

    public function persist(Cart $cart): void
    {
        $stored = [];

        /** @var CartLine $line */
        foreach ($cart->lines() as $line) {
            $stored[$line->productId()] = $line->quantity();
        }

        session()->put(self::SESSION_KEY, $stored);
    }

    public function clear(): void
    {
        session()->forget([self::SESSION_KEY, 'pos.checkout_token']);
    }

    /**
     * Token de idempotencia del carrito (RF-22): un doble envío no crea dos ventas.
     */
    public function token(): string
    {
        $token = session()->get('pos.checkout_token');

        if (! is_string($token) || $token === '') {
            $token = (string) \Illuminate\Support\Str::uuid();
            session()->put('pos.checkout_token', $token);
        }

        return $token;
    }

    public function snapshot(Product $product): ProductSnapshot
    {
        return new ProductSnapshot(
            id: $product->id,
            name: $product->name,
            unitPrice: \App\Pos\Money::fromPesos($product->price),
            category: $product->sale_category,
            availableStock: $product->stock,
            sellable: $product->sellable,
        );
    }
}
