<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Pos\Enum\SaleStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Anula una venta confirmada y repone el stock (RF-31).
 *
 * La anulación es atómica: estado de la venta + stock + movimientos en una
 * sola transacción. Bloquea la venta y los productos en orden de id para
 * evitar deadlocks con cobros simultáneos. La nota de crédito, la autorización
 * de supervisor y la auditoría llegan en T9.
 */
final class VoidSaleService
{
    public function void(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale): Sale {
            /** @var Sale $locked */
            $locked = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === SaleStatus::voided) {
                throw new DomainException('La venta ya está anulada.');
            }

            $lines = $locked->lines()->get();

            // Bloqueo pesimista en orden de id: mismo orden que el cobro.
            $productIds = $lines->pluck('product_id')->unique()->sort()->values()->all();

            Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lines as $line) {
                Product::query()
                    ->whereKey($line->product_id)
                    ->increment('stock', $line->quantity);

                StockMovement::query()->create([
                    'product_id' => $line->product_id,
                    'sale_id' => $locked->id,
                    'quantity' => $line->quantity,
                    'reason' => 'void',
                ]);
            }

            $locked->update(['status' => SaleStatus::voided]);

            return $locked->load('lines', 'document');
        }, 3);
    }
}
