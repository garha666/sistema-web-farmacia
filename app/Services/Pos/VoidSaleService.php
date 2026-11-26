<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\SaleStatus;
use App\Pos\Enum\StockMovementType;
use App\Pos\Policy\VoidPolicy;
use App\Services\Inventory\LotStock;
use Illuminate\Support\Facades\DB;

/**
 * Anula una venta confirmada (RF-31 a RF-36).
 *
 * La venta original nunca se borra ni se modifica salvo su estado: la
 * anulación se guarda como un hecho separado en `audit_logs` con autor,
 * motivo y fecha local. Todo ocurre en una sola transacción: estado, stock,
 * movimientos, auditoría y nota de crédito simulada.
 */
final class VoidSaleService
{
    public function __construct(
        private readonly VoidPolicy $policy,
        private readonly AuditService $audit,
        private readonly DocumentService $documents,
        private readonly LotStock $lots,
    ) {}

    public function void(Sale $sale, string $reason, ?int $userId = null): Sale
    {
        $reason = trim($reason);

        return DB::transaction(function () use ($sale, $reason, $userId): Sale {
            /** @var Sale $locked */
            $locked = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->policy->assertCanVoid($locked->status, $reason);

            $lines = $locked->lines()->get();

            // Bloqueo pesimista en orden de id: mismo orden que el cobro.
            $productIds = $lines->pluck('product_id')->unique()->sort()->values()->all();

            $lockedProducts = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lines as $line) {
                $this->lots->restore($line);
                // RF-74: la existencia resultante se toma de la fila bloqueada.
                $resultingStock = (int) $lockedProducts->get($line->product_id)?->stock + $line->quantity;

                Product::query()
                    ->whereKey($line->product_id)
                    ->increment('stock', $line->quantity);

                StockMovement::query()->create([
                    'product_id' => $line->product_id,
                    'sale_id' => $locked->id,
                    'user_id' => $userId,
                    'type' => StockMovementType::void,
                    'quantity' => $line->quantity,
                    'resulting_stock' => $resultingStock,
                ]);
            }

            $locked->update(['status' => SaleStatus::voided]);

            $this->audit->record('sale.void', $locked, $userId, $reason);
            $this->createCreditNote($locked);

            return $locked->load('lines', 'document', 'reversal');
        }, 3);
    }

    /**
     * Nota de crédito simulada por el documento emitido (RF-34, S-5).
     *
     * Comparte la asignación de folios con el resto del sistema (S9). Si la
     * venta se anuló con el documento aún pendiente no hay nada que revertir:
     * no se crea nota de crédito.
     */
    private function createCreditNote(Sale $sale): void
    {
        $original = $sale->document;

        if ($original === null || $original->status !== DocumentStatus::issued) {
            return;
        }

        $folio = $this->documents->allocateFolio(DocumentType::creditNote);

        $sale->reversal()->create([
            'reversal_of_id' => $original->id,
            'type' => DocumentType::creditNote,
            'folio' => $folio,
            'status' => $folio === null ? DocumentStatus::pending : DocumentStatus::issued,
            'issued_at' => $folio === null ? null : now(),
        ]);
    }
}
