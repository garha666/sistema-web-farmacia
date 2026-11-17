<?php

declare(strict_types=1);

namespace App\Pos\Policy;

/**
 * B2 (RF-1, RF-2, RNF-3, S-7): el lector de códigos se comporta como teclado y
 * entrega el código completo, así que un acierto exacto de código de barras no
 * es una búsqueda: agrega el producto al carrito sin exigir un clic.
 *
 * Clase pura: no conoce Eloquent, la sesión ni el request. Recibe el término y
 * los candidatos ya resumidos, y devuelve qué hacer.
 */
final class BarcodeScanPolicy
{
    public const NOT_SELLABLE = 'product_not_sellable';

    public const OUT_OF_STOCK = 'product_out_of_stock';

    /**
     * @param  array<int, array{id: int, barcode: string|null, sellable: bool, stock: int}>  $candidates
     * @return array{product_id: int|null, error: string|null}
     */
    public function evaluate(string $term, array $candidates): array
    {
        $term = trim($term);

        if ($term === '') {
            return ['product_id' => null, 'error' => null];
        }

        foreach ($candidates as $candidate) {
            if (($candidate['barcode'] ?? null) !== $term) {
                continue;
            }

            if (! $candidate['sellable']) {
                return ['product_id' => null, 'error' => self::NOT_SELLABLE];
            }

            // Un producto sin existencia no entra: la caja avisa y deja la
            // búsqueda limpia, igual que el botón «Agregar» deshabilitado.
            if ($candidate['stock'] < 1) {
                return ['product_id' => null, 'error' => self::OUT_OF_STOCK];
            }

            return ['product_id' => $candidate['id'], 'error' => null];
        }

        return ['product_id' => null, 'error' => null];
    }
}
