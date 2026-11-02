<?php

declare(strict_types=1);

namespace App\Pos\Value;

/**
 * Datos tributarios del cliente para la factura simulada (RF-23, S-2).
 *
 * Objeto puro: no toca base de datos ni HTTP.
 */
final class InvoiceData
{
    public function __construct(
        public readonly string $rut,
        public readonly string $businessName,
        public readonly string $businessActivity,
        public readonly string $address,
        public readonly string $commune,
    ) {
    }
}
