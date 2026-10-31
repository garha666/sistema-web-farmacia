<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Convention>
 */
final class ConventionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Convenio '.$this->faker->unique()->company(),
            'percent' => 10,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'valid_from' => now()->subMonths(2)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }
}
