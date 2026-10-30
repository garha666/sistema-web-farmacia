<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\SaleStatus;
use App\Pos\Policy\VoidPolicy;
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
    ) {
    }

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

            $this->audit->record('sale.void', $locked, $userId, $reason);
            $this->createCreditNote($locked);

            return $locked->load('lines', 'document', 'reversal');
        }, 3);
    }

    /**
     * Nota de crédito simulada por el documento emitido (RF-34, S-5).
     */
    private function createCreditNote(Sale $sale): void
    {
        $original = $sale->document;

        if ($original === null || $original->status !== DocumentStatus::issued) {
            return;
        }

        $folio = $this->nextCreditNoteFolio();

        $sale->reversal()->create([
            'reversal_of_id' => $original->id,
            'type' => DocumentType::creditNote,
            'folio' => $folio,
            'status' => $folio === null ? DocumentStatus::pending : DocumentStatus::issued,
            'issued_at' => $folio === null ? null : now(),
        ]);
    }

    /**
     * Siguiente folio de nota de crédito; null si el rango se agotó (RF-24).
     */
    private function nextCreditNoteFolio(): ?int
    {
        $from = (int) config('pos.folio.credit_note_from');
        $to = (int) config('pos.folio.credit_note_to');

        $last = Document::query()
            ->where('type', DocumentType::creditNote)
            ->whereNotNull('folio')
            ->max('folio');

        $next = $last === null ? $from : ((int) $last) + 1;

        return $next > $to ? null : $next;
    }
}
