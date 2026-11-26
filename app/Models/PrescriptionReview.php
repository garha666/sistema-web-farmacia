<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PrescriptionReview extends Model
{
    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array', 'expires_at' => 'datetime'];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
