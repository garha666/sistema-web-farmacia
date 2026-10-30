<?php

declare(strict_types=1);

namespace App\Pos\Enum;

enum PaymentMethod: string
{
    case cash = 'cash';
    case debit = 'debit';
    case credit = 'credit';
    case transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::cash => 'Efectivo',
            self::debit => 'Débito',
            self::credit => 'Crédito',
            self::transfer => 'Transferencia',
        };
    }

    public function requiresReceivedAmount(): bool
    {
        return $this === self::cash;
    }
}
