<?php

declare(strict_types=1);

namespace App\Models;

use App\Pos\Enum\SaleCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Producto del catálogo (RF-1, RF-2, RF-7).
 */
final class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'active_ingredient',
        'barcode',
        'price',
        'sale_category',
        'stock',
        'sellable',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'sellable' => 'boolean',
            'sale_category' => SaleCategory::class,
        ];
    }
}
