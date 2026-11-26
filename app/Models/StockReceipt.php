<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'controlled' => 'boolean',
            'expires_on' => 'date',
            'reviewed_at' => 'datetime',
            'expected_quantity' => 'integer',
            'received_quantity' => 'integer',
            'remaining_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function difference(): int
    {
        return $this->received_quantity - $this->expected_quantity;
    }
}
