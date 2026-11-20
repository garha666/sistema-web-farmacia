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

    /** RNF-30: cada producto del sondeo trae sólo id, stock y estado, nada más. */
    public function test_stock_poll_exposes_only_id_stock_and_active(): void
    {
        $product = Product::factory()->create(['stock' => 3, 'price' => 1990]);

        $item = $this->getJson('/pos/stock?product_ids[]='.$product->id)->json('products.0');

        $keys = array_keys($item);
        sort($keys);
        $this->assertSame(['active', 'id', 'stock'], $keys);
    }

    /** RF-88: el sondeo cubre varias líneas en una sola petición (una por intervalo, RNF-33). */
    public function test_stock_poll_answers_every_cart_line_in_one_request(): void
    {
        $first = Product::factory()->create(['stock' => 4]);
        $second = Product::factory()->create(['stock' => 0]);
        $third = Product::factory()->create(['stock' => 9, 'active' => false]);

        $response = $this->getJson('/pos/stock?product_ids[]='.$first->id.'&product_ids[]='.$second->id.'&product_ids[]='.$third->id);

        $byId = collect($response->json('products'))->keyBy('id');
        $this->assertCount(3, $byId);
        $this->assertSame(4, $byId[$first->id]['stock']);
        $this->assertSame(0, $byId[$second->id]['stock']);
        $this->assertFalse($byId[$third->id]['active']);
    }

    /** RF-88: los ids del carrito (cart.lines[].product_id) son los que el JS manda al sondeo. */
    public function test_stock_poll_accepts_the_ids_taken_from_the_cart_payload(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $cart = $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $ids = array_column($cart->json('cart.lines'), 'product_id');

        $product->forceFill(['stock' => 0])->save();

        $query = http_build_query(['product_ids' => $ids]);
        $response = $this->getJson('/pos/stock?'.$query);

        $this->assertSame(0, $response->json('products.0.stock'));
    }

    /** RF-88: un id que no es entero se rechaza con 422. */
    public function test_stock_poll_rejects_non_integer_ids(): void
    {
        $this->getJson('/pos/stock?product_ids[]=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_ids.0']);
    }

    /** Caso límite 5: con la sesión vencida el sondeo responde 401 y el JS manda al login. */
    public function test_stock_poll_requires_a_session(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/pos/stock')->assertStatus(401);
    }

    /** RF-42 / RNF-30: el Bodeguero no consulta el stock del POS. */
    public function test_stock_poll_is_closed_to_the_bodeguero(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'bodeguero']));

        $this->getJson('/pos/stock')->assertForbidden();
    }

    /** RF-88: sin IDs de producto, el sondeo devuelve una lista vacía. */
    public function test_stock_poll_without_product_ids_returns_empty_list(): void
    {
        $response = $this->getJson('/pos/stock');

        $response->assertOk();
        $this->assertSame([], $response->json('products'));
    }
}
