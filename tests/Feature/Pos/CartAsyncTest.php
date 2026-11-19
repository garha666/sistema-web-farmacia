<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T31 - RF-85, RF-86, RF-90, RF-91: carrito y búsqueda asíncronos.
 *
 * Las operaciones del carrito y la búsqueda responden JSON cuando la
 * petición lo pide, sin recargar la página. El carrito en sesión es la
 * fuente de verdad; el cliente solo refleja lo que el servidor devuelve.
 * Sin JavaScript, los formularios originales siguen funcionando (RF-91).
 */
final class CartAsyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión.
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
    }

    /** RF-85: agregar un producto devuelve el carrito en JSON sin recargar. */
    public function test_adding_a_product_returns_the_cart_in_json(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $response = $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response->assertOk();
        $response->assertJsonStructure([
            'cart' => ['isEmpty', 'totalQuantity', 'lines' => ['*' => []]],
            'totals' => ['subtotal', 'net', 'iva', 'total'],
        ]);
        $this->assertSame(1, $response->json('cart.totalQuantity'));
        $this->assertSame(1990, $response->json('totals.total'));
        $this->assertSame($product->id, $response->json('cart.lines.0.product_id'));
        $this->assertSame(1, $response->json('cart.lines.0.quantity'));
    }

    /** RF-85: agregar el mismo producto dos veces incrementa la cantidad. */
    public function test_adding_the_same_product_twice_increments_quantity(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $response = $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response->assertOk();
        $this->assertSame(2, $response->json('cart.totalQuantity'));
        $this->assertSame(3980, $response->json('totals.total'));
    }

    /** RF-85: cambiar la cantidad recalcula los totales sin recargar. */
    public function test_updating_quantity_recalculates_totals_in_json(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $response = $this->patchJson("/pos/cart/{$product->id}", ['quantity' => 3]);

        $response->assertOk();
        $this->assertSame(3, $response->json('cart.totalQuantity'));
        $this->assertSame(3000, $response->json('totals.total'));
    }

    /** RF-85: quitar una línea deja el carrito vacío en JSON. */
    public function test_removing_a_line_leaves_the_cart_empty_in_json(): void
    {
        $product = Product::factory()->create();
        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->deleteJson("/pos/cart/{$product->id}");

        $response->assertOk();
        $this->assertTrue($response->json('cart.isEmpty'));
        $this->assertSame(0, $response->json('cart.totalQuantity'));
        $this->assertSame(0, $response->json('totals.total'));
    }

    /** RF-86: la búsqueda devuelve coincidencias en JSON sin recargar. */
    public function test_search_returns_matches_in_json(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol 500 mg', 'stock' => 10]);

        $response = $this->getJson('/pos', ['q' => 'Paracetamol']);

        $response->assertOk();
        $response->assertJsonStructure(['results' => ['*' => []]]);
        $this->assertNotEmpty($response->json('results'));
        $this->assertSame('Paracetamol 500 mg', $response->json('results.0.name'));
    }

    /** RF-90: un producto sin stock se rechaza en JSON y el carrito se conserva. */
    public function test_out_of_stock_product_is_rejected_in_json(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $response = $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
        $this->assertDatabaseMissing('sales', []);
    }

    /** RF-90: un producto no vendible se rechaza en JSON. */
    public function test_non_sellable_product_is_rejected_in_json(): void
    {
        $product = Product::factory()->create(['active' => false]);

        $response = $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
    }

    /** RF-90: una cantidad superior al stock se rechaza en JSON. */
    public function test_quantity_above_stock_is_rejected_in_json(): void
    {
        $product = Product::factory()->create(['stock' => 2]);
        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->patchJson("/pos/cart/{$product->id}", ['quantity' => 5]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    /** RF-90: el carrito persiste en sesión tras una operación asíncrona. */
    public function test_cart_persists_in_session_after_async_operations(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $response = $this->getJson('/pos');

        $response->assertOk();
        $this->assertSame(1, $response->json('cart.totalQuantity'));
        $this->assertSame(1990, $response->json('totals.total'));
    }
}
