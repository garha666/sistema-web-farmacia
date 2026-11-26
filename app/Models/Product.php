<?php

declare(strict_types=1);

namespace App\Models;

use App\Pos\Enum\SaleCategory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Producto del catálogo (RF-1, RF-2, RF-7).
 */
final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'active_ingredient',
        'barcode',
        'price',
        'sale_category',
        'stock',
        'min_stock',
        'sellable',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'sellable' => 'boolean',
            'active' => 'boolean',
            'sale_category' => SaleCategory::class,
        ];
    }

    /**
     * Productos vigentes en el catálogo (RF-72).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function scopeWithPosStock(Builder $query, string $date): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('products.*');
        }

        $query->selectRaw('GREATEST(0, products.stock - COALESCE((SELECT SUM(remaining_quantity)
            FROM stock_receipts WHERE product_id = products.id AND expires_on < ?), 0)) AS pos_stock', [$date]);
    }

    /**
     * Existencia menor o igual al mínimo (S-11, RF-48, RF-64).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeBelowMinimum(Builder $query): void
    {
        $query->whereColumn('stock', '<=', 'min_stock');
    }

    /**
     * Existencia agotada (RF-48, RF-63).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeOutOfStock(Builder $query): void
    {
        $query->where('stock', '<=', 0);
    }
}
