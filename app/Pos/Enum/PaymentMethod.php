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
        return __('pos.payment_method.'.$this->value);
    }

    public function requiresReceivedAmount(): bool
    {
        return $this === self::cash;
    }
}
