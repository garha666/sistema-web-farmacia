<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Document;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Reintento de documentos pendientes o rechazados (RF-26).
 *
 * El folio ya asignado se conserva al reintentar un documento rechazado; un
 * documento pendiente recibe el siguiente folio libre del rango de su tipo.
 * El índice único (type, folio) impide duplicados.
 */
final class DocumentService
{
    public function retry(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            /** @var Document $locked */
            $locked = Document::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [DocumentStatus::pending, DocumentStatus::rejected], true)) {
                throw new DomainException('Sólo se pueden reintentar documentos pendientes o rechazados.');
            }

            $folio = $locked->folio ?? $this->nextFolio($locked->type);

            if ($folio === null) {
                throw new DomainException('No quedan folios disponibles para este tipo de documento.');
            }

            $locked->update([
                'folio' => $folio,
                'status' => DocumentStatus::issued,
                'issued_at' => now(),
            ]);

            return $locked;
        }, 3);
    }

    /**
     * Siguiente folio libre del rango configurado; null si se agotó (RF-24).
     */
    private function nextFolio(DocumentType $type): ?int
    {
        [$from, $to] = $this->range($type);

        $last = Document::query()
            ->where('type', $type)
            ->whereNotNull('folio')
            ->orderByDesc('folio')
            ->lockForUpdate()
            ->first();

        $next = $last === null ? $from : ((int) $last->folio) + 1;

        return $next > $to ? null : $next;
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
