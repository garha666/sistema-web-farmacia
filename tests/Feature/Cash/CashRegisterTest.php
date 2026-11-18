<?php

declare(strict_types=1);

namespace Tests\Feature\Cash;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T12 - RF-58 y RF-59: apertura, cierre y arqueo del turno.
 *
 * Casos límite 4 (cierre sin ventas), 5 (diferencia a favor y en contra),
 * 6 (cierre con anulaciones), 7 (anulación posterior al cierre), 8 (segunda
 * apertura) y 20 (sesión expirada durante el cierre).
 */
final class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->tecnico()->create();
        $this->actingAs($this->cashier);
    }

    private function openRegister(int $amount = 50000): CashRegister
    {
        return CashRegister::factory()->create([
            'user_id' => $this->cashier->id,
            'opening_amount' => $amount,
        ]);
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

    public function test_opening_a_register_records_amount_user_and_local_time(): void
    {
        $response = $this->post(route('cash.open'), ['opening_amount' => 50000]);

        $response->assertRedirect(route('dashboard.tecnico'));
        $response->assertSessionHasNoErrors();

        $register = CashRegister::query()->sole();

        $this->assertSame(50000, $register->opening_amount);
        $this->assertSame($this->cashier->id, $register->user_id);
        $this->assertTrue($register->isOpen());
        $this->assertNotNull($register->opened_at);
    }

    public function test_a_negative_opening_amount_is_rejected(): void
    {
        $this->post(route('cash.open'), ['opening_amount' => -1])
            ->assertSessionHasErrors('opening_amount');

        $this->assertSame(0, CashRegister::query()->count());
    }

    public function test_a_second_open_register_is_rejected_for_the_same_cashier(): void
    {
        $this->openRegister(30000);

        $response = $this->post(route('cash.open'), ['opening_amount' => 50000]);

        $response->assertSessionHasErrors('opening_amount');
        $this->assertSame(
            __('pos.cash_register.error.already_open'),
            session('errors')->first('opening_amount'),
        );
        $this->assertSame(1, CashRegister::query()->count());
    }

    public function test_each_cashier_can_open_their_own_register(): void
    {
        $this->openRegister();

        $other = User::factory()->tecnico()->create();

        $this->actingAs($other)
            ->post(route('cash.open'), ['opening_amount' => 20000])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, CashRegister::query()->count());
        $this->assertSame(1, CashRegister::query()->where('user_id', $other->id)->count());
    }

    public function test_only_a_cashier_can_open_a_register(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('cash.open'), ['opening_amount' => 50000])
            ->assertForbidden();

        $this->assertSame(0, CashRegister::query()->count());
    }

    public function test_closing_without_sales_reports_a_zero_difference(): void
    {
        $register = $this->openRegister(50000);

        $response = $this->patch(route('cash.close', $register), ['declared_amount' => 50000]);

        $response->assertRedirect(route('dashboard.tecnico'));
        $response->assertSessionHasNoErrors();

        $register->refresh();

        $this->assertNotNull($register->closed_at);
        $this->assertSame(0, $register->total_sales);
        $this->assertSame(0, $register->total_voids);
        $this->assertSame(0, $register->cash_sales);
        $this->assertSame(0, $register->cash_voids);
        $this->assertSame(50000, $register->expected_cash);
        $this->assertSame(50000, $register->declared_cash);
        $this->assertSame(0, $register->difference);
    }

    public function test_the_close_screen_shows_the_turn_totals_before_confirming(): void
    {
        $register = $this->openRegister(50000);
        $this->saleOf($register, 10000, 'cash');
        $this->saleOf($register, 4000, 'cash', SaleStatus::voided);
        $this->saleOf($register, 6000, 'debit');

        $response = $this->get(route('cash.close.show'));

        $response->assertOk();
        $response->assertSee('$56.000');
        $response->assertSee('$10.000');
        $response->assertSee('$6.000');
        $response->assertSee(__('pos.cash_register.declared_amount'));
    }

    public function test_the_close_screen_requires_an_open_register(): void
    {
        $this->get(route('cash.close.show'))
            ->assertRedirect(route('dashboard.tecnico'))
            ->assertSessionHasErrors('cash');
    }

    public function test_closing_subtracts_cash_voids_from_the_expected_cash(): void
    {
        $register = $this->openRegister(50000);
        $this->saleOf($register, 10000, 'cash');
        $this->saleOf($register, 4000, 'cash', SaleStatus::voided);
        $this->saleOf($register, 6000, 'debit');

        $this->patch(route('cash.close', $register), ['declared_amount' => 56000])
            ->assertSessionHasNoErrors();

        $register->refresh();

        $this->assertSame(16000, $register->total_sales);
        $this->assertSame(4000, $register->total_voids);
        $this->assertSame(10000, $register->cash_sales);
        $this->assertSame(4000, $register->cash_voids);
        $this->assertSame(56000, $register->expected_cash);
        $this->assertSame(56000, $register->declared_cash);
        $this->assertSame(0, $register->difference);
    }

    public function test_a_shortfall_is_recorded_and_the_close_is_allowed(): void
    {
        $register = $this->openRegister(50000);

        $this->patch(route('cash.close', $register), ['declared_amount' => 47000])
            ->assertRedirect(route('dashboard.tecnico'))
            ->assertSessionHasNoErrors();

        $register->refresh();

        $this->assertSame(50000, $register->expected_cash);
        $this->assertSame(47000, $register->declared_cash);
        $this->assertSame(-3000, $register->difference);
        $this->assertNotNull($register->closed_at);
    }

    public function test_a_surplus_is_recorded_and_the_close_is_allowed(): void
    {
        $register = $this->openRegister(50000);

        $this->patch(route('cash.close', $register), ['declared_amount' => 53000])
            ->assertSessionHasNoErrors();

        $register->refresh();

        $this->assertSame(3000, $register->difference);
        $this->assertNotNull($register->closed_at);
    }

    public function test_a_negative_declared_amount_is_rejected(): void
    {
        $register = $this->openRegister(50000);

        $this->patch(route('cash.close', $register), ['declared_amount' => -1])
            ->assertSessionHasErrors('declared_amount');

        $this->assertTrue($register->fresh()->isOpen());
    }

    public function test_another_cashier_cannot_close_my_register(): void
    {
        $register = $this->openRegister();
        $other = User::factory()->tecnico()->create();

        $this->actingAs($other)
            ->patch(route('cash.close', $register), ['declared_amount' => 50000])
            ->assertForbidden();

        $this->assertTrue($register->fresh()->isOpen());
    }

    public function test_a_closed_register_cannot_be_closed_again(): void
    {
        $register = CashRegister::factory()->closed(50000)->create([
            'user_id' => $this->cashier->id,
        ]);

        $this->patch(route('cash.close', $register), ['declared_amount' => 40000])
            ->assertRedirect(route('dashboard.tecnico'))
            ->assertSessionHasErrors('cash');

        $this->assertSame(50000, $register->fresh()->declared_cash);
    }

    public function test_a_guest_cannot_close_a_register(): void
    {
        $register = $this->openRegister();

        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->patch(route('cash.close', $register), ['declared_amount' => 50000])
            ->assertRedirect('/login');

        $this->assertTrue($register->fresh()->isOpen());
    }

    public function test_a_void_after_closing_does_not_rewrite_the_register(): void
    {
        $register = $this->openRegister(50000);

        $product = Product::factory()->create(['stock' => 10, 'price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();
        $sale->update(['cash_register_id' => $register->id]);

        $this->patch(route('cash.close', $register), ['declared_amount' => 50000])
            ->assertSessionHasNoErrors();

        $register->refresh();
        $closedAt = $register->closed_at->toDateTimeString();

        $this->assertSame(1000, $register->total_sales);
        $this->assertSame(50000, $register->expected_cash);

        $supervisor = User::factory()->quimico()->create();
        $this->actingAs($supervisor)
            ->post(route('pos.sales.void', $sale), ['reason' => 'Error de cobro'])
            ->assertSessionHasNoErrors();

        $register->refresh();

        $this->assertSame($closedAt, $register->closed_at->toDateTimeString());
        $this->assertSame(1000, $register->total_sales);
        $this->assertSame(0, $register->total_voids);
        $this->assertSame(50000, $register->expected_cash);
        $this->assertSame(0, $register->difference);
        $this->assertSame(SaleStatus::voided, $sale->fresh()->status);
    }
}
