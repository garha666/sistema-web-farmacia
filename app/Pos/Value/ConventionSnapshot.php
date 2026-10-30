<?php

declare(strict_types=1);

namespace App\Pos\Value;

use DateTimeImmutable;

/**
 * Convenio de salud configurado por el administrador (RF-39, RF-40).
 */
final readonly class ConventionSnapshot
{
    public function __construct(
        public int $id,
        public string $name,
        public float $percent,
        public bool $active,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
    ) {
    }

    public function isApplicableOn(DateTimeImmutable $today): bool
    {
        $day = $today->setTime(0, 0, 0);

        return $this->active
            && $day >= $this->validFrom->setTime(0, 0, 0)
            && $day <= $this->validUntil->setTime(0, 0, 0);
    }
}
