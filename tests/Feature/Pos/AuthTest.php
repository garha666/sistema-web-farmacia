<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * S1 - RF-22: el POS exige sesión y la venta registra al cajero autenticado.
 */
final class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_anonymous_sale_is_rejected(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/pos')->assertStatus(401);

        $this->postJson('/pos/cart', ['product_id' => $product->id])
            ->assertStatus(401);

        $this->postJson('/pos/checkout', ['payment_method' => 'debit'])
            ->assertStatus(401);

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_an_authenticated_sale_stores_the_cashier(): void
    {
        $cashier = User::factory()->create(['role' => 'tecnico']);
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($cashier)
            ->post('/pos/cart', ['product_id' => $product->id])
            ->assertRedirect('/pos');

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame($cashier->id, $sale->user_id);
    }

    public function test_the_sale_model_fills_the_cashier_from_the_session(): void
    {
        $cashier = User::factory()->create(['role' => 'tecnico']);

        $this->actingAs($cashier);

        $sale = Sale::query()->create([
            'token' => (string) Str::uuid(),
            'subtotal' => 1000,
            'net' => 840,
            'iva' => 160,
            'total' => 1000,
            'payment_method' => 'debit',
            'sold_at' => now(),
        ]);

        $this->assertSame($cashier->id, $sale->user_id);
    }
}
