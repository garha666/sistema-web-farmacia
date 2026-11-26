<?php

namespace Tests;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function pharmacistCredentials(?User $chemist = null): array
    {
        $chemist ??= User::factory()->quimico()->create();
        $chemist->forceFill(['pharmacist_pin_hash' => Hash::make('843719205')])->save();

        return ['chemist_email' => $chemist->email, 'pharmacist_pin' => '843719205'];
    }

    protected function discountCredentials(bool $line = false, ?User $supervisor = null): array
    {
        $supervisor ??= User::factory()->quimico()->create();
        $supervisor->forceFill(['pharmacist_pin_hash' => Hash::make('843719205')])->save();
        $prefix = $line ? 'line_' : '';

        return [$prefix.'supervisor_email' => $supervisor->email, $prefix.'supervisor_password' => '843719205'];
    }

    /**
     * T13 - RF-60: declara la precondición de una caja abierta.
     *
     * Los tests que cobran necesitan un turno abierto del cajero en sesión.
     * Sin argumentos usa el usuario autenticado, para que el setUp de cada
     * suite lo invoque con una sola línea.
     */
    protected function openCashRegister(?User $user = null, int $openingAmount = 50000): CashRegister
    {
        $cashier = $user ?? auth()->user();

        if (! $cashier instanceof User) {
            throw new RuntimeException('openCashRegister() necesita un cajero autenticado.');
        }

        return CashRegister::query()->create([
            'user_id' => $cashier->getKey(),
            'opening_amount' => $openingAmount,
            'opened_at' => now(config('pos.timezone'))->format('Y-m-d H:i:s'),
        ]);
    }
}
