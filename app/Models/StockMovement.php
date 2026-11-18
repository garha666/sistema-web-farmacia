<?php

declare(strict_types=1);

namespace App\Models;

use App\Pos\Enum\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de stock trazable (RF-65, RF-74).
 *
 * Cada fila guarda el tipo, la cantidad con signo, la existencia resultante y
 * el autor, de modo que la existencia de un producto se reconstruye como la
 * existencia inicial más la suma de sus movimientos.
 */
final class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'sale_id',
        'user_id',
        'type',
        'quantity',
        'resulting_stock',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'resulting_stock' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
