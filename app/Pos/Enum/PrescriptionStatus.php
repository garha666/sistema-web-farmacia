<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum PrescriptionStatus: string
{
    case valid = 'valid';
    case requiresSupervisor = 'requires_supervisor';
    case invalid = 'invalid';
}
