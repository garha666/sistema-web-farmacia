<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de caja (RF-56 a RF-61).
 *
 * Mientras `closed_at` es nulo el turno está abierto y el POS puede cobrar
 * contra él. Al cerrar se graba el arqueo completo: ventas, anulaciones,
 * efectivo esperado, monto declarado y diferencia.
 */
final class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'opening_amount',
        'opened_at',
        'closed_at',
        'total_sales',
        'total_voids',
        'cash_sales',
        'cash_voids',
        'expected_cash',
        'declared_cash',
        'difference',
        'close_reason',
        'closed_payment_breakdown',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_amount' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'total_sales' => 'integer',
            'total_voids' => 'integer',
            'cash_sales' => 'integer',
            'cash_voids' => 'integer',
            'expected_cash' => 'integer',
            'declared_cash' => 'integer',
            'difference' => 'integer',
            'closed_payment_breakdown' => 'array',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
