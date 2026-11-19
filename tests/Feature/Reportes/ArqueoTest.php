<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T37 - RF-94: arqueo de caja por turno.
 *
 * El Técnico ve su propio arqueo; el Administrador ve el de cualquier cajero.
 * Químico y Bodeguero reciben 403.
 */
final class ArqueoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tecnico = User::factory()->tecnico()->create();
        $this->admin = User::factory()->admin()->create();
        $this->quimico = User::factory()->quimico()->create();
    }

    /** RF-94: el técnico ve su propio arqueo de caja. */
    public function test_technician_sees_own_register_summary(): void
    {
        $register = CashRegister::factory()->create([
            'user_id' => $this->tecnico->id,
            'opening_amount' => 10000,
            'opened_at' => now()->subDay(),
            'closed_at' => null,
        ]);

        $this->createSale($register->id, '2026-11-01 10:00:00', 1990, 'cash');
        $this->createSale($register->id, '2026-11-01 11:00:00', 2990, 'debit');

        $response = $this->actingAs($this->tecnico)
            ->get('/admin/reportes/arqueo');

        $response->assertOk();
        $response->assertSee('$10.000');
        $response->assertSee('$4.980');
        $response->assertSee('2');
    }

    /** RF-94: el administrador ve el arqueo de cualquier cajero. */
    public function test_admin_sees_any_register_summary(): void
    {
        $register = CashRegister::factory()->create([
            'user_id' => $this->tecnico->id,
            'opening_amount' => 5000,
            'opened_at' => now()->subDay(),
            'closed_at' => null,
        ]);

        $this->createSale($register->id, '2026-11-01 09:00:00', 1500, 'cash');

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/arqueo');

        $response->assertOk();
        $response->assertSee('$1.500');
    }

    /** RF-94: químico y bodeguero no pueden ver arqueos de caja. */
    public function test_non_cashier_roles_receive_forbidden(): void
    {
        foreach ([$this->quimico, User::factory()->bodeguero()->create()] as $user) {
            $response = $this->actingAs($user)
                ->get('/admin/reportes/arqueo');

            $response->assertForbidden();
        }
    }

    /** RF-94: turno cerrado muestra los datos guardados al cerrar. */
    public function test_closed_register_shows_declared_and_difference(): void
    {
        $register = CashRegister::factory()->create([
            'user_id' => $this->tecnico->id,
            'opening_amount' => 10000,
            'opened_at' => now()->subDay(),
            'closed_at' => now(),
            'total_sales' => 1990,
            'cash_sales' => 1990,
            'expected_cash' => 11990,
            'declared_cash' => 12000,
            'difference' => 10,
        ]);

        $response = $this->actingAs($this->tecnico)
            ->get('/admin/reportes/arqueo');

        $response->assertOk();
        $response->assertSee('$11.990');
        $response->assertSee('$12.000');
        $response->assertSee('+$10');
    }

    private function createSale(int $cashRegisterId, string $soldAt, int $total, string $method): void
    {
        Sale::query()->create([
            'token' => Str::uuid(),
            'subtotal' => $total,
            'net' => (int) round($total * 100 / 119),
            'iva' => $total - (int) round($total * 100 / 119),
            'total' => $total,
            'payment_method' => $method,
            'status' => 'confirmed',
            'sold_at' => $soldAt,
            'user_id' => $this->tecnico->id,
            'cash_register_id' => $cashRegisterId,
        ]);
    }
}
