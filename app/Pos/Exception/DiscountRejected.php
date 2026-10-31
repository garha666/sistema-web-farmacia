<?php

declare(strict_types=1);

namespace App\Pos\Exception;

use DomainException;

/**
 * El descuento manual no permite confirmar la venta (RF-16, RF-17, RF-18).
 *
 * El campo indica qué input de la vista debe marcarse con el error.
 */
final class DiscountRejected extends DomainException
{
    public function __construct(
        private readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }
}
