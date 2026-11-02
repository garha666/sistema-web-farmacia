<?php

declare(strict_types=1);

namespace App\Pos\Contract;

use App\Pos\Enum\DocumentType;
use App\Pos\Exception\EmissionFailed;

/**
 * Punto de simulación de la emisión tributaria (D-1, RF-24).
 *
 * No hay servicio externo ni SII real: la implementación por defecto sólo
 * confirma que la emisión se intentó. En pruebas se sustituye por un doble que
 * lanza EmissionFailed, sin exponer ningún interruptor en la interfaz.
 */
interface DocumentEmitter
{
    /**
     * @throws EmissionFailed cuando la emisión simulada falla.
     */
    public function emit(DocumentType $type): void;
}
