<?php

declare(strict_types=1);

namespace App\Pos\Policy;

use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\Shortage;

/**
 * Reglas de disponibilidad de stock.
 *
 * RF-3: no se acepta una cantidad mayor a la existencia.
 * RF-27: nunca se vende más de lo disponible (sin sobreventa).
 * RF-29: si el stock cambió desde que se armó el carrito, hay que avisar.
 *
 * Esta clase sólo decide; el bloqueo real de la fila ocurre en la transacción
 * de cobro (T8).
 */
final class StockPolicy
{
    public function canSell(int $available, int $requested): bool
    {
        if ($requested < 1) {
            return false;
        }

        return $available >= $requested;
    }

    /**
     * @param  array<int, int>  $availableByProductId  existencia leída en el momento de confirmar
     * @return Shortage[]
     */
    public function shortages(Cart $cart, array $availableByProductId): array
    {
        $shortages = [];

        /** @var CartLine $line */
        foreach ($cart->lines() as $line) {
            $available = max(0, $availableByProductId[$line->productId()] ?? 0);

            if (! $this->canSell($available, $line->quantity())) {
                $shortages[] = new Shortage(
                    productId: $line->productId(),
                    requested: $line->quantity(),
                    available: $available,
                );
            }
        }

        return $shortages;
    }

    /**
     * @param  array<int, int>  $availableByProductId
     */
    public function hasShortages(Cart $cart, array $availableByProductId): bool
    {
        return $this->shortages($cart, $availableByProductId) !== [];
    }

    /**
     * Productos cuyo stock ya no coincide con el que se congeló en el carrito.
     *
     * @param  array<int, int>  $availableByProductId
     * @return int[]
     */
    public function changedSinceSnapshot(Cart $cart, array $availableByProductId): array
    {
        $changed = [];

        /** @var CartLine $line */
        foreach ($cart->lines() as $line) {
            $available = $availableByProductId[$line->productId()] ?? null;

            if ($available !== null && $available !== $line->product->availableStock) {
                $changed[] = $line->productId();
            }
        }

        return $changed;
    }
}
