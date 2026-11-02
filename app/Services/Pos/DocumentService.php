<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\ControlledRegisterEntry;
use App\Models\Document;
use App\Pos\Contract\DocumentEmitter;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\SaleStatus;
use App\Pos\Folio\FolioAllocator;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Emisión simulada, reintento y asignación de folios (RF-23, RF-24, RF-26).
 *
 * Toda la asignación de folios del sistema pasa por aquí. En PostgreSQL cada
 * tipo de documento toma un lock de asesoría transaccional antes de leer el
 * máximo: dos cajas no pueden calcular el mismo folio a la vez. El índice
 * único (type, folio) sigue siendo la última defensa.
 *
 * Si la transacción se revierte, el lock se libera y el folio no se consume:
 * no se promete ausencia de huecos entre transacciones exitosas, pero sí que un
 * fallo no quema un número.
 */
final class DocumentService
{
    /**
     * Espacio de nombres del lock de asesoría: nunca colisiona con otros usos.
     */
    private const FOLIO_LOCK_NAMESPACE = 20261102;

    public function __construct(
        private readonly DocumentEmitter $emitter,
        private readonly FolioAllocator $allocator,
    ) {
    }

    /**
     * Intenta la emisión simulada de un documento ya confirmado (RF-24).
     *
     * Un rango agotado deja el documento pendiente sin lanzar error. Un fallo
     * del simulador (EmissionFailed) se propaga para que el llamador decida.
     */
    public function emit(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $locked = $this->lock($document);

            if ($locked->status === DocumentStatus::issued) {
                return $locked;
            }

            if ($this->saleIsVoided($locked)) {
                return $locked;
            }

            // Punto de simulación sustituible (D-1). Un fallo esperado deja el
            // documento pendiente; el llamador decide si lo convierte en error.
            $this->emitter->emit($locked->type);

            $folio = $locked->folio ?? $this->allocateFolio($locked->type);

            if ($folio === null) {
                return $locked;
            }

            $this->issue($locked, $folio);

            return $locked;
        }, 3);
    }

    /**
     * Reintento manual desde administración (RF-26).
     *
     * Conserva el folio del documento rechazado; si no tenía, asigna el
     * siguiente libre. Un documento emitido o de una venta anulada se rechaza.
     */
    public function retry(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $locked = $this->lock($document);

            if ($locked->status === DocumentStatus::issued) {
                throw new DomainException('Sólo se pueden reintentar documentos pendientes o rechazados.');
            }

            if ($this->saleIsVoided($locked)) {
                throw new DomainException(__('pos.errors.voided_sale_document'));
            }

            $this->emitter->emit($locked->type);

            $folio = $locked->folio ?? $this->allocateFolio($locked->type);

            if ($folio === null) {
                throw new DomainException('No quedan folios disponibles para este tipo de documento.');
            }

            $this->issue($locked, $folio);

            return $locked;
        }, 3);
    }

    /**
     * Siguiente folio libre del rango del tipo; null si se agotó (RF-24).
     *
     * Debe invocarse dentro de una transacción: el lock de asesoría es
     * transaccional y se libera al confirmar o revertir.
     */
    public function allocateFolio(DocumentType $type): ?int
    {
        [$from, $to] = $this->range($type);

        $this->lockFolioSequence($type);

        $last = Document::query()
            ->where('type', $type)
            ->whereNotNull('folio')
            ->max('folio');

        return $this->allocator->next($from, $to, $last === null ? null : (int) $last);
    }

    private function issue(Document $document, int $folio): void
    {
        $document->update([
            'folio' => $folio,
            'status' => DocumentStatus::issued,
            'issued_at' => now(),
        ]);

        // El libro de control guarda el folio del documento original; al emitir
        // después de confirmar la venta, se completa aquí (RF-10).
        if ($document->reversal_of_id === null) {
            ControlledRegisterEntry::query()
                ->where('sale_id', $document->sale_id)
                ->whereNull('folio')
                ->update(['folio' => $folio]);
        }
    }

    private function lock(Document $document): Document
    {
        /** @var Document $locked */
        $locked = Document::query()
            ->whereKey($document->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    private function saleIsVoided(Document $document): bool
    {
        return $document->sale()
            ->where('status', SaleStatus::voided)
            ->exists();
    }

    /**
     * Serializa la asignación por tipo en PostgreSQL (RF-27/RF-28).
     *
     * El lock es transaccional: se libera al confirmar o revertir, y el rango
     * agotado o un fallo no consumen un folio. En otros motores se apoya sólo
     * en el índice único (type, folio); los tests y la producción usan
     * PostgreSQL 16 (T9).
     */
    private function lockFolioSequence(DocumentType $type): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $key = match ($type) {
            DocumentType::receipt => 1,
            DocumentType::invoice => 2,
            DocumentType::creditNote => 3,
        };

        DB::select(sprintf(
            'SELECT pg_advisory_xact_lock(%d, %d)',
            self::FOLIO_LOCK_NAMESPACE,
            $key,
        ));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function range(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::receipt => [
                (int) config('pos.folio.receipt_from'),
                (int) config('pos.folio.receipt_to'),
            ],
            DocumentType::invoice => [
                (int) config('pos.folio.invoice_from'),
                (int) config('pos.folio.invoice_to'),
            ],
            DocumentType::creditNote => [
                (int) config('pos.folio.credit_note_from'),
                (int) config('pos.folio.credit_note_to'),
            ],
        };
    }
}
