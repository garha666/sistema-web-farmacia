<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del libro de controlados: una venta de medicamento controlado con su
 * receta, folio y datos congelados al momento de la venta (RF-10).
 */
final class ControlledRegisterEntry extends Model
{
    protected $table = 'controlled_register';

    protected $fillable = [
        'prescription_id',
        'sale_id',
        'sale_line_id',
        'medication',
        'quantity',
        'customer_rut',
        'folio',
        'sold_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'folio' => 'integer',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<SaleLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }
}
