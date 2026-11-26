<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class SaleLine extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'name',
        'unit_price',
        'quantity',
        'discount_amount',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'discount_amount' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function lots(): BelongsToMany
    {
        return $this->belongsToMany(StockReceipt::class, 'sale_line_lots')->withPivot('quantity');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
