<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Enum\PrescriptionStatus;

/**
 * Resultado de validar una receta (RF-9, RF-11).
 *
 * Los errores van indexados por campo para que la vista pueda marcarlos.
 */
final readonly class PrescriptionResult
{
    /**
     * @param  array<string, string>  $errors
     */
    private function __construct(
        private PrescriptionStatus $status,
        private array $errors,
    ) {
    }

    public static function valid(): self
    {
        return new self(PrescriptionStatus::valid, []);
    }

    /**
     * @param  array<string, string>  $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(PrescriptionStatus::invalid, $errors);
    }

    /**
     * @param  array<string, string>  $errors
     */
    public static function forSupervisor(array $errors = []): self
    {
        return new self(PrescriptionStatus::requiresSupervisor, $errors);
    }

    public function status(): PrescriptionStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return $this->status === PrescriptionStatus::valid;
    }

    public function isInvalid(): bool
    {
        return $this->status === PrescriptionStatus::invalid;
    }

    public function requiresSupervisor(): bool
    {
        return $this->status === PrescriptionStatus::requiresSupervisor;
    }
}
