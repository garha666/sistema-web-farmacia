<?php

namespace Tests;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
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
