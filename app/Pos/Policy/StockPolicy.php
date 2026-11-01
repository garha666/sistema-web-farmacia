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
     * La caja guarda el stock de cada línea al agregarla o ajustarla; sin ese
     * mapa se usa el snapshot de la propia línea (RF-29).
     *
     * @param  array<int, int>  $availableByProductId  stock vivo
     * @param  array<int, int>|null  $snapshotByProductId  stock congelado al armar el carrito
     * @return int[]
     */
    public function detectChanges(
        Cart $cart,
        array $availableByProductId,
        ?array $snapshotByProductId = null,
    ): array {
        $changed = [];

        /** @var CartLine $line */
        foreach ($cart->lines() as $line) {
            $available = $availableByProductId[$line->productId()] ?? null;
            $snapshot = $snapshotByProductId === null
                ? $line->product->availableStock
                : ($snapshotByProductId[$line->productId()] ?? null);

            if ($available !== null && $snapshot !== null && $available !== $snapshot) {
                $changed[] = $line->productId();
            }
        }

        return $changed;
    }
}
