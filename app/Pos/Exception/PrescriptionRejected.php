<?php

declare(strict_types=1);

namespace App\Pos\Exception;

use DomainException;

/**
 * La receta impide confirmar la venta (RF-8, RF-9, RF-11).
 *
 * `requiresSupervisor` distingue el caso de receta reutilizada, que no es un
 * error de datos sino una autorización pendiente.
 */
final class PrescriptionRejected extends DomainException
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        private readonly array $errors,
        private readonly bool $requiresSupervisor = false,
    ) {
        parent::__construct('La receta no permite confirmar la venta.');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function requiresSupervisor(): bool
    {
        return $this->requiresSupervisor;
    }
}
