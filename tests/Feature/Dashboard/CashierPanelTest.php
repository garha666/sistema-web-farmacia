<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T13 - RF-56 (estado de la caja), RF-57 (caja cerrada), RF-61 (resumen del
 * turno) y RF-62 (enlace al POS) en el panel del Técnico.
 */
final class CashierPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->tecnico()->create();
        $this->actingAs($this->cashier);
    }

    private function saleOf(
        CashRegister $register,
        int $total,
        string $method,
        SaleStatus $status = SaleStatus::confirmed,
    ): Sale {
        $net = (int) round($total * 100 / 119);

        return Sale::query()->create([
            'token' => (string) Str::uuid(),
            'subtotal' => $total,
            'net' => $net,
            'iva' => $total - $net,
            'total' => $total,
            'payment_method' => $method,
            'status' => $status,
            'sold_at' => now(),
            'user_id' => $this->cashier->id,
            'cash_register_id' => $register->id,
        ]);
    }

    public function test_without_an_open_register_the_panel_offers_to_open_one(): void
    {
        $response = $this->get(route('dashboard.tecnico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.cash_register.open_warning'));
        $response->assertSeeText(__('pos.cash_register.no_turn'));
        $response->assertSee('name="opening_amount"', false);
        $response->assertSee(route('cash.open'), false);
    }

    public function test_the_panel_always_links_to_the_pos(): void
    {
        $this->get(route('dashboard.tecnico'))
            ->assertOk()
            ->assertSee(route('pos.index'), false)
            ->assertSeeText(__('pos.cash_register.go_pos'));
    }

    public function test_after_closing_the_register_the_panel_shows_the_difference(): void
    {
        $register = $this->openCashRegister($this->cashier, 50000);

        $this->patch(route('cash.close', $register), ['declared_amount' => 47000, 'reason' => 'Faltante tras el recuento'])
            ->assertRedirect(route('dashboard.tecnico'));

        $this->get(route('dashboard.tecnico'))
            ->assertOk()
            ->assertSeeText(__('pos.cash_register.status_closed', [
                'amount' => '$3.000',
                'kind' => __('pos.cash_register.kind.short'),
            ]))
            ->assertSeeText('$3.000')
            ->assertSeeText(__('pos.cash_register.kind.short'));
    }

    public function test_the_panel_shows_the_open_register_and_the_turn_summary(): void
    {
        $register = $this->openCashRegister($this->cashier, 50000);
        $this->saleOf($register, 10000, 'cash');
        $this->saleOf($register, 6000, 'debit');
        $this->saleOf($register, 4000, 'cash', SaleStatus::voided);

        $response = $this->get(route('dashboard.tecnico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.cash_register.status_open'));
        $response->assertSee($register->opened_at->format('d-m-Y H:i'));
        $response->assertSee('$50.000');
        $response->assertSee('$16.000');
        $response->assertSee('$10.000');
        $response->assertSee('$6.000');
        $response->assertSee('$4.000');
        $response->assertSee(route('cash.close.show'), false);
        $response->assertSeeText(__('pos.cash_register.go_close'));
    }

    public function test_a_closed_register_is_shown_as_no_turn(): void
    {
        CashRegister::factory()->closed(70000)->create(['user_id' => $this->cashier->id]);

        $response = $this->get(route('dashboard.tecnico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.cash_register.open_warning'));
        $response->assertDontSee('$70.000');
    }

    public function test_another_cashiers_register_is_not_shown_in_my_panel(): void
    {
        $this->openCashRegister(User::factory()->tecnico()->create(), 70000);

        $response = $this->get(route('dashboard.tecnico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.cash_register.open_warning'));
        $response->assertDontSee('$70.000');
    }

    public function test_the_pos_links_back_to_my_panel(): void
    {
        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee(route('dashboard.tecnico'), false)
            ->assertSeeText(__('pos.nav.my_panel'));
    }
}
