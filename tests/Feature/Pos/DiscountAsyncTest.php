<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T32 - RF-87: descuentos asíncronos (global y de línea).
 *
 * Aplicar y quitar descuentos responde JSON sin recargar la página.
 * Sobre el tope general (10%) se exige supervisor (Químico o Admin) y sus
 * credenciales viajan en la misma petición asíncrona (RF-83, RF-16).
 */
final class DiscountAsyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión.
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
    }

    /** RF-87: un descuento global bajo el tope se aplica en JSON. */
    public function test_a_global_discount_below_the_cap_is_applied_in_json(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->postJson('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => 5,
            'discount_reason' => 'Promoción de lanzamiento',
        ]);

        $response->assertOk();
        $this->assertSame(50, $response->json('totals.global_discount'));
        $this->assertSame(950, $response->json('totals.total'));
        $this->assertTrue($response->json('manual_discount'));
    }

    /** RF-87: un descuento global sobre el tope exige supervisor. */
    public function test_a_global_discount_above_the_cap_requires_a_supervisor(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->postJson('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => 20,
            'discount_reason' => 'Descuento grande',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['discount_supervisor']);
    }

    /** RF-87, RF-83: un descuento global sobre el tope con supervisor válido se aplica. */
    public function test_a_global_discount_above_the_cap_with_a_valid_supervisor_is_applied(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $supervisor = User::factory()->quimico()->create(['password' => 'password']);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->postJson('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => 20,
            'discount_reason' => 'Autorizado por supervisor',
            'supervisor_email' => $supervisor->email,
            'supervisor_password' => 'password',
        ]);

        $response->assertOk();
        $this->assertSame(200, $response->json('totals.global_discount'));
        $this->assertSame(800, $response->json('totals.total'));
    }

    /** RF-87: quitar el descuento global lo elimina en JSON. */
    public function test_removing_the_global_discount_clears_it_in_json(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $this->postJson('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => 5,
            'discount_reason' => 'Promoción',
        ]);

        $response = $this->deleteJson('/pos/discount');

        $response->assertOk();
        $this->assertSame(0, $response->json('totals.global_discount'));
        $this->assertSame(1000, $response->json('totals.total'));
        $this->assertFalse($response->json('manual_discount'));
    }

    /** RF-87: un descuento de línea se aplica en JSON. */
    public function test_a_line_discount_is_applied_in_json(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->postJson("/pos/cart/{$product->id}/discount", [
            'line_type' => 'percent',
            'line_value' => 10,
            'line_reason' => 'Descuento de línea',
        ]);

        $response->assertOk();
        $this->assertSame(100, $response->json('totals.line_discounts'));
        $this->assertSame(900, $response->json('totals.total'));
    }

    /** RF-87: quitar un descuento de línea lo elimina en JSON. */
    public function test_removing_a_line_discount_clears_it_in_json(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $this->postJson("/pos/cart/{$product->id}/discount", [
            'line_type' => 'percent',
            'line_value' => 10,
            'line_reason' => 'Descuento de línea',
        ]);

        $response = $this->deleteJson("/pos/cart/{$product->id}/discount");

        $response->assertOk();
        $this->assertSame(0, $response->json('totals.line_discounts'));
        $this->assertSame(1000, $response->json('totals.total'));
    }

    /** RF-87: un descuento de línea sobre el tope exige supervisor. */
    public function test_a_line_discount_above_the_cap_requires_a_supervisor(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->postJson("/pos/cart/{$product->id}/discount", [
            'line_type' => 'percent',
            'line_value' => 50,
            'line_reason' => 'Descuento grande de línea',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['line_discount_supervisor']);
    }

    /** RF-87: el carrito persiste en sesión tras aplicar descuentos asíncronos. */
    public function test_cart_persists_in_session_after_async_discounts(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $this->postJson('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => 5,
            'discount_reason' => 'Promoción',
        ]);
        $response = $this->getJson('/pos');

        $response->assertOk();
        $this->assertSame(1, $response->json('cart.totalQuantity'));
        $this->assertSame(50, $response->json('totals.global_discount'));
        $this->assertSame(950, $response->json('totals.total'));
    }
}
