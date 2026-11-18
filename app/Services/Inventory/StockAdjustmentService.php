<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Pos\Enum\StockMovementType;
use App\Pos\Policy\StockAdjustmentPolicy;
use App\Pos\Value\StockAdjustment;
use Illuminate\Support\Facades\DB;

/**
 * Ajuste manual de stock (RF-66, RF-73, RF-74).
 *
 * Bloquea la fila del producto, decide con `StockAdjustmentPolicy` y, si se
 * acepta, actualiza la existencia e inserta el movimiento con su autor, su
 * motivo y la existencia resultante. Todo ocurre en una sola transacción.
 */
final class StockAdjustmentService
{
    public function __construct(
        private readonly StockAdjustmentPolicy $policy,
    ) {}

    public function adjust(
        Product $product,
        StockMovementType $type,
        int $quantity,
        string $reason,
        ?int $userId,
    ): StockAdjustment {
        return DB::transaction(function () use ($product, $type, $quantity, $reason, $userId): StockAdjustment {
            /** @var Product $locked */
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $decision = $this->policy->evaluate((int) $locked->stock, $quantity, $type, $reason);

            if (! $decision->accepted()) {
                return $decision;
            }

            $locked->update(['stock' => $decision->resultingStock()]);

            StockMovement::query()->create([
                'product_id' => $locked->id,
                'user_id' => $userId,
                'type' => $type,
                'quantity' => $type->sign() * $quantity,
                'resulting_stock' => $decision->resultingStock(),
                'note' => trim($reason),
            ]);

            return $decision;
        }, 3);
    }
}
