<?php

declare(strict_types=1);

namespace App\Pos\Value;

use DateTimeImmutable;

/**
 * Datos de receta ingresados en el POS (RF-8).
 */
final readonly class PrescriptionInput
{
    public function __construct(
        public string $number,
        public string $prescriberName,
        public string $prescriberRut,
        public DateTimeImmutable $issuedAt,
    ) {
    }

    public function hasBlankField(): bool
    {
        return trim($this->number) === ''
            || trim($this->prescriberName) === ''
            || trim($this->prescriberRut) === '';
    }
}
