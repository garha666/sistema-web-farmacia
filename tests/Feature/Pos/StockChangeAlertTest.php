<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S5 - RF-29: si el stock cambió entre el armado del carrito y la
 * confirmación, el cobro informa la existencia actual y ofrece ajustar.
 */
final class StockChangeAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
    }

    public function test_checkout_alerts_when_the_stock_changed_since_the_cart_was_built(): void
    {
        $product = Product::factory()->create([
            'name' => 'Paracetamol 500 mg',
            'price' => 1500,
            'stock' => 10,
        ]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        // Otra caja vendió tres unidades mientras el carrito estaba armado.
        $product->update(['stock' => 7]);

        $this->get('/pos/checkout')
            ->assertOk()
            ->assertSee('Stock actual: 7')
            ->assertSee('Ajustar cantidades')
            ->assertSee('Paracetamol 500 mg');
    }

    public function test_checkout_does_not_alert_when_the_stock_is_unchanged(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->get('/pos/checkout')
            ->assertOk()
            ->assertDontSee('Stock actual:')
            ->assertDontSee('Ajustar cantidades');
    }

    public function test_adjusting_the_quantity_clears_the_alert(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $product->update(['stock' => 7]);

        // Ajustar la cantidad desde la caja fija el nuevo stock base.
        $this->patch(route('pos.cart.update', $product), ['quantity' => 2])
            ->assertSessionHasNoErrors();

        $this->get('/pos/checkout')
            ->assertOk()
            ->assertDontSee('Stock actual:')
            ->assertDontSee('Ajustar cantidades');
    }
}
