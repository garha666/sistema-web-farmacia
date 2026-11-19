<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T33 - RF-88: stock vigente por sondeo.
 *
 * El POS consulta periódicamente el stock de los productos del carrito y
 * avisa al cajero cuando uno se agota o sale del catálogo, sin recargar la
 * página.
 */
final class PosStockPollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión.
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
    }

    /** RF-88: el sondeo devuelve el stock y el estado de cada producto. */
    public function test_stock_poll_returns_stock_and_active_for_each_product(): void
    {
        $product = Product::factory()->create(['stock' => 7, 'active' => true]);

        $response = $this->getJson('/pos/stock?product_ids[]='.$product->id);

        $response->assertOk();
        $response->assertJsonStructure(['products' => ['*' => ['id', 'stock', 'active']]]);
        $this->assertSame($product->id, $response->json('products.0.id'));
        $this->assertSame(7, $response->json('products.0.stock'));
        $this->assertTrue($response->json('products.0.active'));
    }

    /** RF-88: el sondeo refleja cambios de stock sin recargar la página. */
    public function test_stock_poll_reflects_stock_changes(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'active' => true]);

        $first = $this->getJson('/pos/stock?product_ids[]='.$product->id);
        $this->assertSame(5, $first->json('products.0.stock'));

        $product->forceFill(['stock' => 2])->save();

        $second = $this->getJson('/pos/stock?product_ids[]='.$product->id);
        $this->assertSame(2, $second->json('products.0.stock'));
    }

    /** RF-88: un producto desactivado se marca como inactivo en el sondeo. */
    public function test_stock_poll_marks_inactive_product(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'active' => true]);

        $product->forceFill(['active' => false])->save();

        $response = $this->getJson('/pos/stock?product_ids[]='.$product->id);
        $this->assertFalse($response->json('products.0.active'));
    }

    /** RF-88: un producto sin stock se reporta con stock cero. */
    public function test_stock_poll_reports_out_of_stock_product(): void
    {
        $product = Product::factory()->create(['stock' => 0, 'active' => true]);

        $response = $this->getJson('/pos/stock?product_ids[]='.$product->id);
        $this->assertSame(0, $response->json('products.0.stock'));
    }

    /** RF-88: un producto inexistente no se incluye en el sondeo. */
    public function test_stock_poll_skips_missing_products(): void
    {
        $response = $this->getJson('/pos/stock?product_ids[]=99999');

        $response->assertOk();
        $this->assertSame([], $response->json('products'));
    }

    /** RF-88: sin IDs de producto, el sondeo devuelve una lista vacía. */
    public function test_stock_poll_without_product_ids_returns_empty_list(): void
    {
        $response = $this->getJson('/pos/stock');

        $response->assertOk();
        $this->assertSame([], $response->json('products'));
    }
}
