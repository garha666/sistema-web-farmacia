<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Pos\Enum\SaleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
final class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Paracetamol 500 mg',
            'active_ingredient' => 'Paracetamol',
            'barcode' => $this->faker->unique()->numerify('78012345678##'),
            'price' => 1990,
            'sale_category' => SaleCategory::free,
            'stock' => 50,
            'sellable' => true,
        ];
    }

    public function controlled(): self
    {
        return $this->state(fn (): array => ['sale_category' => SaleCategory::controlled]);
    }

    public function prescription(): self
    {
        return $this->state(fn (): array => ['sale_category' => SaleCategory::prescription]);
    }
}
