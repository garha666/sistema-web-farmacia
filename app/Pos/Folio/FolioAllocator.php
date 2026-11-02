<?php

declare(strict_types=1);

namespace App\Pos\Folio;

/**
 * Siguiente folio de un rango, sin repetir ni salirse de él (RF-23, RF-24).
 *
 * Función pura: no consulta la base de datos. La serialización entre cajas y
 * el índice único (type, folio) son responsabilidad de DocumentService.
 */
final class FolioAllocator
{
    public function next(int $from, int $to, ?int $lastUsed): ?int
    {
        $next = $lastUsed === null ? $from : $lastUsed + 1;

        if ($next < $from || $next > $to) {
            return null;
        }

        return $next;
    }
}
