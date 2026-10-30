<?php

declare(strict_types=1);

namespace App\Pos\Policy;

use App\Pos\Enum\SaleStatus;
use DomainException;

/**
 * Reglas puras de anulación (RF-32, RF-35).
 */
final class VoidPolicy
{
    public function assertCanVoid(SaleStatus $status, string $reason): void
    {
        if ($status === SaleStatus::voided) {
            throw new DomainException('La venta ya está anulada.');
        }

        if (trim($reason) === '') {
            throw new DomainException('El motivo de la anulación es obligatorio.');
        }
    }
}
