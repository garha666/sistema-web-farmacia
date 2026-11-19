<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Product;
use App\Pos\Enum\DiscountType;
use App\Pos\Money;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\Discount;
use App\Pos\Value\ProductSnapshot;
use Illuminate\Support\Str;

/**
 * Carrito de la caja, persistido en sesión (RF-6).
 *
 * En sesión sólo se guarda id de producto, cantidad y descuento de línea; el
 * precio y el stock se releen de la base en cada request para no vender con
 * datos viejos.
 */
final class CartService
{
    private const SESSION_KEY = 'pos.cart';

    /**
     * Stock congelado cuando se agregó o ajustó cada línea (RF-29).
     */
    private const STOCK_KEY = 'pos.cart_stock';

    /**
     * Descuento por línea, junto al carrito (RF-4, RF-13). Mapa id => datos.
     */
    private const LINE_DISCOUNT_KEY = 'pos.line_discounts';

    public function load(): Cart
    {
        $stored = session()->get(self::SESSION_KEY, []);

        if (! is_array($stored) || $stored === []) {
            return Cart::empty();
        }

        $products = Product::query()
            ->whereIn('id', array_map('intval', array_keys($stored)))
            // B2: un producto fuera de catálogo no se vende, aunque el carrito
            // guardado en sesión lo tenga de antes.
            ->where('active', true)
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

        // RF-6/RF-13: rehidrata el descuento de línea guardado con el carrito.
        $lineDiscounts = session()->get(self::LINE_DISCOUNT_KEY, []);

        if (is_array($lineDiscounts)) {
            foreach ($lineDiscounts as $productId => $data) {
                $discount = $this->decodeDiscount($data);

                if ($discount !== null && isset($stored[(int) $productId])) {
                    $cart = $cart->applyLineDiscount((int) $productId, $discount);
                }
            }
        }

        return $cart;
    }

    public function persist(Cart $cart): void
    {
        $stored = [];
        $stock = [];
        $lineDiscounts = [];
        $previous = session()->get(self::LINE_DISCOUNT_KEY, []);
        $previous = is_array($previous) ? $previous : [];

        /** @var CartLine $line */
        foreach ($cart->lines() as $line) {
            $stored[$line->productId()] = $line->quantity();
            $stock[$line->productId()] = $line->product->availableStock;

            if ($line->discount() !== null) {
                $entry = $this->encodeDiscount($line->discount());

                // S12: conserva el autor de la autorización mientras el
                // descuento de la línea siga siendo el mismo (p. ej. al cambiar
                // la cantidad, que recalcula el monto pero no reautoriza).
                $prior = $previous[$line->productId()] ?? null;

                if (is_array($prior)
                    && ($prior['type'] ?? null) === $entry['type']
                    && ($prior['value'] ?? null) == $entry['value']
                    && isset($prior['authorized_by'])) {
                    $entry['authorized_by'] = (int) $prior['authorized_by'];
                }

                $lineDiscounts[$line->productId()] = $entry;
            }
        }

        session()->put(self::SESSION_KEY, $stored);
        session()->put(self::STOCK_KEY, $stock);
        session()->put(self::LINE_DISCOUNT_KEY, $lineDiscounts);
    }

    /**
     * Supervisor que autorizó el descuento de cada línea (S12, RF-36).
     *
     * @return array<int, int> id de producto => id del supervisor
     */
    public function lineDiscountAuthors(): array
    {
        $stored = session()->get(self::LINE_DISCOUNT_KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        $authors = [];

        foreach ($stored as $productId => $entry) {
            if (is_array($entry) && isset($entry['authorized_by'])) {
                $authors[(int) $productId] = (int) $entry['authorized_by'];
            }
        }

        return $authors;
    }

    /**
     * Guarda el supervisor que autorizó el descuento de una línea (S12).
     */
    public function authorizeLineDiscount(int $productId, int $supervisorId): void
    {
        $stored = session()->get(self::LINE_DISCOUNT_KEY, []);

        if (! is_array($stored) || ! isset($stored[$productId]) || ! is_array($stored[$productId])) {
            return;
        }

        $stored[$productId]['authorized_by'] = $supervisorId;

        session()->put(self::LINE_DISCOUNT_KEY, $stored);
    }

    /**
     * Stock que tenía cada producto la última vez que se tocó el carrito (RF-29).
     *
     * @return array<int, int>
     */
    public function snapshottedStock(): array
    {
        $stored = session()->get(self::STOCK_KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        return array_map('intval', $stored);
    }

    /**
     * Existencia actual de los productos del carrito; ausente = producto borrado.
     *
     * @return array<int, int>
     */
    public function liveStock(Cart $cart): array
    {
        $ids = array_map(
            fn (CartLine $line): int => $line->productId(),
            $cart->lines(),
        );

        if ($ids === []) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'stock'])
            ->mapWithKeys(fn (Product $product): array => [$product->id => $product->stock])
            ->all();
    }

    public function clear(): void
    {
        session()->forget([
            self::SESSION_KEY,
            self::STOCK_KEY,
            self::LINE_DISCOUNT_KEY,
            'pos.checkout_token',
        ]);
    }

    /**
     * Token de idempotencia del carrito (RF-22): un doble envío no crea dos ventas.
     */
    public function token(): string
    {
        $token = session()->get('pos.checkout_token');

        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            session()->put('pos.checkout_token', $token);
        }

        return $token;
    }

    public function snapshot(Product $product): ProductSnapshot
    {
        return new ProductSnapshot(
            id: $product->id,
            name: $product->name,
            unitPrice: Money::fromPesos($product->price),
            category: $product->sale_category,
            availableStock: $product->stock,
            sellable: $product->sellable,
        );
    }

    /**
     * @return array{type: string, value: float|int, reason: string}
     */
    private function encodeDiscount(Discount $discount): array
    {
        return [
            'type' => $discount->type->value,
            'value' => $discount->value,
            'reason' => $discount->reason,
        ];
    }

    /**
     * @param  mixed  $data
     */
    private function decodeDiscount($data): ?Discount
    {
        if (! is_array($data)
            || ! isset($data['type'], $data['value'], $data['reason'])) {
            return null;
        }

        $type = DiscountType::tryFrom((string) $data['type']);
        $reason = (string) $data['reason'];

        if ($type === null || trim($reason) === '') {
            return null;
        }

        return $type === DiscountType::percent
            ? Discount::percent((float) $data['value'], $reason)
            : Discount::amount(Money::fromPesos((int) round((float) $data['value'])), $reason);
    }
}
