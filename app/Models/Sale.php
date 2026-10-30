<?php

declare(strict_types=1);

namespace App\Models;

use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use HasFactory;

    protected $fillable = [
        'token',
        'subtotal',
        'line_discounts',
        'global_discount',
        'net',
        'iva',
        'total',
        'payment_method',
        'received_amount',
        'change_amount',
        'customer_rut',
        'status',
        'sold_at',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'line_discounts' => 'integer',
            'global_discount' => 'integer',
            'net' => 'integer',
            'iva' => 'integer',
            'total' => 'integer',
            'received_amount' => 'integer',
            'change_amount' => 'integer',
            'sold_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'status' => SaleStatus::class,
        ];
    }

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /**
     * @return HasOne<Document, $this>
     */
    public function document(): HasOne
    {
        return $this->hasOne(Document::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isVoided(): bool
    {
        return $this->status === SaleStatus::voided;
    }
}
