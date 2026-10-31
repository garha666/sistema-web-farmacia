<?php

declare(strict_types=1);

namespace App\Models;

use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'convention_id',
        'convention_name',
        'convention_percent',
        'prescription_id',
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
            'convention_percent' => 'float',
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
     * Cajero que confirmó la venta (RF-37).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenio aplicado en la venta (RF-40).
     *
     * @return BelongsTo<Convention, $this>
     */
    public function convention(): BelongsTo
    {
        return $this->belongsTo(Convention::class);
    }

    /**
     * Receta asociada a la venta (RF-8, RF-11).
     *
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * Documento de venta (boleta o factura); excluye la nota de crédito.
     *
     * @return HasOne<Document, $this>
     */
    public function document(): HasOne
    {
        return $this->hasOne(Document::class)->whereNull('reversal_of_id');
    }

    /**
     * Nota de crédito simulada que revierte el documento (RF-34).
     *
     * @return HasOne<Document, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(Document::class)->whereNotNull('reversal_of_id');
    }

    /**
     * Autorizaciones y anulaciones registradas sobre la venta (RF-36).
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
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

    /**
     * RF-22: si el servicio no recibe un cajero explícito, se toma el usuario
     * autenticado para que ninguna venta quede sin responsable.
     */
    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            if ($sale->user_id === null) {
                $sale->user_id = auth()->id();
            }
        });
    }
}
