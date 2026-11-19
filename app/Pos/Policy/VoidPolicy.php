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
        // T29: la política es pura; lanza la clave y el controlador la traduce.
        if ($status === SaleStatus::voided) {
            throw new DomainException('pos.errors.sale_already_voided');
        }

        if (trim($reason) === '') {
            throw new DomainException('pos.errors.void_reason_required');
        }
    }
}
