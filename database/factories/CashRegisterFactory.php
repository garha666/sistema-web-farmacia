<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->tecnico(),
            'opening_amount' => 50000,
            'opened_at' => now(),
            'closed_at' => null,
            'total_sales' => null,
            'total_voids' => null,
            'cash_sales' => null,
            'cash_voids' => null,
            'expected_cash' => null,
            'declared_cash' => null,
            'difference' => null,
        ];
    }

    /**
     * Turno ya cerrado con su arqueo grabado.
     */
    public function closed(int $declaredCash): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
            'total_sales' => 0,
            'total_voids' => 0,
            'cash_sales' => 0,
            'cash_voids' => 0,
            'expected_cash' => (int) $attributes['opening_amount'],
            'declared_cash' => $declaredCash,
            'difference' => $declaredCash - (int) $attributes['opening_amount'],
        ]);
    }
}
