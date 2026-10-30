<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum DocumentStatus: string
{
    case pending = 'pending';
    case issued = 'issued';
    case rejected = 'rejected';
}
