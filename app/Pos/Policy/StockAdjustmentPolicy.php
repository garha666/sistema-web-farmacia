<?php

declare(strict_types=1);

namespace App\Pos\Policy;

use App\Pos\Enum\StockMovementType;
use App\Pos\Value\StockAdjustment;

/**
 * Reglas de un ajuste manual de stock (RF-73, RF-74).
 *
 * Función pura: recibe la existencia actual, la cantidad, el tipo y el motivo,
 * y decide sin tocar la base de datos. Rechaza una cantidad no positiva, un
 * motivo vacío y una salida mayor que la existencia; el bloqueo real de la fila
 * ocurre en la transacción de `StockAdjustmentService`.
 */
final class StockAdjustmentPolicy
{
    public function evaluate(
        int $currentStock,
        int $quantity,
        StockMovementType $type,
        string $reason,
    ): StockAdjustment {
        if ($quantity < 1) {
            return StockAdjustment::reject(StockAdjustment::INVALID_QUANTITY, $currentStock);
        }

        if (trim($reason) === '') {
            return StockAdjustment::reject(StockAdjustment::REASON_REQUIRED, $currentStock);
        }

        if ($type->sign() < 0 && $quantity > $currentStock) {
            return StockAdjustment::reject(StockAdjustment::INSUFFICIENT_STOCK, $currentStock);
        }

        return StockAdjustment::accept(
            $currentStock + $type->sign() * $quantity,
            $currentStock,
        );
    }
}
