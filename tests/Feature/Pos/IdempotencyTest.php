<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * T35 - RF-22: doble clic e idempotencia del cobro con checkout_token.
 *
 * Un doble clic rápido en «Pagar» envía dos requests POST con el mismo
 * checkout_token. El sistema debe crear una sola venta y devolver el mismo
 * comprobante en ambas respuestas, sin error 500 ni venta duplicada.
 */
final class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión y la venta guarda al cajero.
        $this->cashier = User::factory()->create(['role' => 'tecnico']);
        $this->actingAs($this->cashier);
        $this->openCashRegister($this->cashier);
    }

    /** RF-22: dos cobros con el mismo carrito y token crean una sola venta. */
    public function test_double_click_creates_only_one_sale(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        // Primer cobro: crea la venta con el token de sesión.
        $response1 = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $response1->assertRedirect();
        $this->assertSame(1, Sale::query()->count());

        $sale = Sale::query()->firstOrFail();
        $token = $sale->token;

        // Simula el segundo clic del doble clic: restaura el carrito y el mismo
        // token de idempotencia en la sesión, como si ambas requests hubieran
        // partido del mismo estado antes de que el primero limpiara nada.
        $this->post('/pos/cart', ['product_id' => $product->id]);
        Session::put('pos.checkout_token', $token);

        $response2 = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $response2->assertRedirect();

        // Solo debe existir una venta, y ambas respuestas apuntan al mismo comprobante.
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(
            $response1->headers->get('Location'),
            $response2->headers->get('Location'),
            'Ambas respuestas deben redirigir al mismo comprobante.'
        );

        $this->assertSame(1, $sale->fresh()->lines()->count());
        $this->assertSame(9, $product->fresh()->stock);
    }

    /** RF-22: el carrito se limpia tras un cobro exitoso; un segundo cobro sin carrito se rechaza. */
    public function test_checkout_without_cart_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $this->assertSame(1, Sale::query()->count());

        // Sin carrito, el segundo cobro debe ser rechazado.
        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertSame(1, Sale::query()->count());
    }
}
