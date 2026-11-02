<?php

declare(strict_types=1);

namespace App\Pos\Emission;

use App\Pos\Contract\DocumentEmitter;
use App\Pos\Enum\DocumentType;

/**
 * Emisión simulada local (D-1, RF-23, RF-24).
 *
 * No contacta al SII ni a ningún proveedor: el ciclo de emisión vive dentro del
 * sistema. Un fallo real de esta simulación sólo puede provocarlo un doble de
 * prueba; en producción siempre confirma la emisión.
 */
final class SimulatedDocumentEmitter implements DocumentEmitter
{
    public function emit(DocumentType $type): void
    {
        // Sin llamada externa: la emisión es interna y simulada.
    }
}
