<?php

declare(strict_types=1);

namespace Tests\Feature\Cash;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T13 - RF-60: mientras el cajero no tenga una caja abierta, el sistema no
 * confirma el cobro y le indica cómo abrirla (caso límite 9).
 */
final class CheckoutGateTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->tecnico()->create();
        $this->actingAs($this->cashier);
    }

    public function test_checkout_without_an_open_register_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 1990]);

        $this->post('/pos/cart', ['product_id' => $product->id])->assertRedirect('/pos');

        $response = $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'cash' => __('pos.cash_register.error.open_to_charge'),
        ]);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_the_checkout_screen_explains_that_an_open_register_is_required(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 1990]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->get('/pos/checkout');

        $response->assertOk();
        $response->assertSeeText(__('pos.cash_register.open_warning'));
        $response->assertSee(route('dashboard.tecnico'), false);
        $response->assertDontSee('name="payment_method"', false);
    }

    public function test_a_sale_is_sealed_with_the_open_register(): void
    {
        $register = $this->openCashRegister($this->cashier, 30000);
        $product = Product::factory()->create(['stock' => 10, 'price' => 1990]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame($register->id, $sale->cash_register_id);
        $this->assertSame($this->cashier->id, $sale->user_id);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_a_closed_register_does_not_authorize_a_charge(): void
    {
        CashRegister::factory()->closed(50000)->create(['user_id' => $this->cashier->id]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 1990]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasErrors('cash');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_another_cashiers_open_register_does_not_authorize_my_charge(): void
    {
        $this->openCashRegister(User::factory()->tecnico()->create(), 50000);
        $product = Product::factory()->create(['stock' => 10, 'price' => 1990]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasErrors('cash');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, $product->fresh()->stock);
    }
}
