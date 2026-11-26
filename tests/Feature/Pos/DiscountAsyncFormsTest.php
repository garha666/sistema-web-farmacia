<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T32 - RF-87: los cuatro formularios de descuento están marcados con
 * `data-pos-async` y las operaciones responden JSON con los fragmentos
 * que el cliente asíncrono pinta sin recargar la página.
 *
 * Sobre el tope exige supervisor (RF-83); el bloque de credenciales
 * aparece dentro del carrito sin vaciarlo (RF-87).
 */
final class DiscountAsyncFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión.
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
    }

    /** RF-87: aplicar y quitar descuentos responden JSON con fragmentos del carrito. */
    public function test_discount_operations_return_json_with_fragments(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $global = $this->postJson('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => 5,
            'discount_reason' => 'Promoción',
            ...$this->discountCredentials(),
        ]);

        $global->assertOk();
        $this->assertIsArray($global->json('fragments'));
        $this->assertIsString($global->json('fragments.cart'));
        $this->assertStringContainsString('data-testid="cart-total"', $global->json('fragments.cart'));

        $line = $this->postJson("/pos/cart/{$product->id}/discount", [
            'line_type' => 'percent',
            'line_value' => 10,
            'line_reason' => 'Descuento de línea',
            ...$this->discountCredentials(true),
        ]);

        $line->assertOk();
        $this->assertIsString($line->json('fragments.cart'));
        $this->assertStringContainsString('data-cart-line', $line->json('fragments.cart'));

        $removeGlobal = $this->deleteJson('/pos/discount');

        $removeGlobal->assertOk();
        $this->assertIsString($removeGlobal->json('fragments.cart'));
        $this->assertStringContainsString('data-testid="cart-total"', $removeGlobal->json('fragments.cart'));

        $removeLine = $this->deleteJson("/pos/cart/{$product->id}/discount");

        $removeLine->assertOk();
        $this->assertIsString($removeLine->json('fragments.cart'));
        $this->assertStringContainsString('data-cart-line', $removeLine->json('fragments.cart'));
    }

    /**
     * RF-87: los cuatro formularios de descuento — global aplicar, global
     * quitar, línea aplicar y línea quitar — están marcados con
     * `data-pos-async`.
     */
    public function test_the_four_discount_forms_carry_data_pos_async(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        // Global aplicar y de línea aplicar (carrito con una línea sin descuento).
        $html = $this->get('/pos')->getContent();
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*\/pos\/discount"[^>]*data-pos-async="discount"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*\/pos\/cart\/'.$product->id.'\/discount"[^>]*data-pos-async="discount"/',
            $html,
        );

        // Global quitar (después de aplicar un descuento global).
        $this->postJson('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => 5,
            'discount_reason' => 'Promoción',
            ...$this->discountCredentials(),
        ]);

        $html = $this->get('/pos')->getContent();
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*\/pos\/discount"[^>]*data-pos-async="discount-remove"/',
            $html,
        );

        // Línea quitar (después de aplicar un descuento de línea).
        $this->postJson("/pos/cart/{$product->id}/discount", [
            'line_type' => 'percent',
            'line_value' => 10,
            'line_reason' => 'Descuento de línea',
            ...$this->discountCredentials(true),
        ]);

        $html = $this->get('/pos')->getContent();
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*\/pos\/cart\/'.$product->id.'\/discount"[^>]*data-pos-async="discount-remove"/',
            $html,
        );
    }
}
