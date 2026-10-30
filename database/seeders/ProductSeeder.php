<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Pos\Enum\SaleCategory;
use Illuminate\Database\Seeder;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Paracetamol 500 mg', 'active_ingredient' => 'Paracetamol', 'price' => 990, 'stock' => 120, 'sale_category' => SaleCategory::free],
            ['name' => 'Ibuprofeno 400 mg', 'active_ingredient' => 'Ibuprofeno', 'price' => 1490, 'stock' => 80, 'sale_category' => SaleCategory::free],
            ['name' => 'Amoxicilina 500 mg', 'active_ingredient' => 'Amoxicilina', 'price' => 4990, 'stock' => 40, 'sale_category' => SaleCategory::prescription],
            ['name' => 'Clonazepam 0,5 mg', 'active_ingredient' => 'Clonazepam', 'price' => 3990, 'stock' => 15, 'sale_category' => SaleCategory::controlled],
        ];

        foreach ($products as $product) {
            Product::query()->create($product + ['sellable' => true]);
        }
    }
}
