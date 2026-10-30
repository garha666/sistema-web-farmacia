<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum DocumentType: string
{
    case receipt = 'receipt';
    case invoice = 'invoice';
    case creditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::receipt => 'Boleta',
            self::invoice => 'Factura',
            self::creditNote => 'Nota de crédito',
        };
    }
}
