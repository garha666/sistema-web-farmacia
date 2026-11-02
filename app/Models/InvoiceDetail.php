<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos tributarios de la factura simulada (RF-23, S-2).
 */
final class InvoiceDetail extends Model
{
    protected $fillable = [
        'sale_id',
        'rut',
        'business_name',
        'business_activity',
        'address',
        'commune',
        'folio_from',
        'folio_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'folio_from' => 'integer',
            'folio_to' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
