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

        $response = $this->getJson('/pos?q=Paracetamol');

        $response->assertOk();
        $response->assertJsonStructure(['results' => ['*' => []]]);
        $this->assertNotEmpty($response->json('results'));
        $this->assertSame('Paracetamol 500 mg', $response->json('results.0.name'));
    }

    /** RF-86: el término `q` filtra; lo que no coincide no vuelve. */
    public function test_search_filters_by_the_q_term(): void
    {
        Product::factory()->create(['name' => 'Paracetamol 500 mg', 'active_ingredient' => 'Paracetamol']);
        Product::factory()->create(['name' => 'Ibuprofeno 400 mg', 'active_ingredient' => 'Ibuprofeno']);

        $response = $this->getJson('/pos?q=ibupro');

        $response->assertOk();
        $this->assertSame(['Ibuprofeno 400 mg'], array_column($response->json('results'), 'name'));
    }

    /** RF-86: la búsqueda también coincide por principio activo y por código de barras. */
    public function test_search_matches_active_ingredient_and_barcode(): void
    {
        $byIngredient = Product::factory()->create(['name' => 'Marca A', 'active_ingredient' => 'Loratadina', 'barcode' => '7801111111111']);
        Product::factory()->create(['name' => 'Marca B', 'active_ingredient' => 'Otro', 'barcode' => '7802222222222']);

        $this->assertSame(
            [$byIngredient->id],
            array_column($this->getJson('/pos?q=lorata')->json('results'), 'id'),
        );

        $this->assertSame(
            [$byIngredient->id],
            array_column($this->getJson('/pos?q=780111&scan=0')->json('results'), 'id'),
        );
    }

    /** RF-86 / RF-72: la lista sólo trae productos vendibles y vigentes. */
    public function test_search_excludes_inactive_and_non_sellable_products(): void
    {
        Product::factory()->create(['name' => 'Visible', 'stock' => 3]);
        Product::factory()->create(['name' => 'Desactivado', 'active' => false]);
        Product::factory()->create(['name' => 'No vendible', 'sellable' => false]);

        $names = array_column($this->getJson('/pos')->json('results'), 'name');

        $this->assertSame(['Visible'], $names);
    }

    /** RF-86: cada resultado trae nombre, principio activo, código, precio y stock, y nada más. */
    public function test_search_result_exposes_only_the_fields_the_list_shows(): void
    {
        $product = Product::factory()->create([
            'name' => 'Paracetamol 500 mg',
            'active_ingredient' => 'Paracetamol',
            'barcode' => '7801234567890',
            'price' => 1990,
            'stock' => 4,
        ]);

        $item = $this->getJson('/pos?q=Paracetamol')->json('results.0');

        $keys = array_keys($item);
        sort($keys);

        $this->assertSame(['active', 'active_ingredient', 'barcode', 'id', 'name', 'price', 'stock'], $keys);
        $this->assertSame($product->id, $item['id']);
        $this->assertSame(1990, $item['price']);
        $this->assertSame(4, $item['stock']);
        $this->assertTrue($item['active']);
    }

    /** RF-86: sin coincidencias la lista llega vacía y el fragmento explica por qué. */
    public function test_search_without_matches_returns_an_empty_list_and_a_message(): void
    {
        Product::factory()->create(['name' => 'Paracetamol 500 mg']);

        $response = $this->getJson('/pos?q=zzz%3Cb%3E');

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
        $html = $response->json('fragments.results');
        $this->assertStringContainsString('zzz&lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    /** RF-86: el fragmento de resultados trae el formulario de agregar que el JS intercepta. */
    public function test_search_fragment_carries_the_add_form_the_js_intercepts(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol 500 mg', 'stock' => 5]);
        $soldOut = Product::factory()->create(['name' => 'Agotado', 'stock' => 0]);

        $html = $this->getJson('/pos')->json('fragments.results');

        $this->assertStringContainsString('action="'.route('pos.cart.add').'"', $html);
        $this->assertStringContainsString('data-pos-async="cart"', $html);
        $this->assertStringContainsString('value="'.$product->id.'"', $html);
        $this->assertStringContainsString('Paracetamol 500 mg', $html);
        $this->assertMatchesRegularExpression(
            '/value="'.$soldOut->id.'".*?<button[^>]*disabled/s',
            $html,
            'Un producto sin stock no puede agregarse (RF-88).',
        );
    }

    /** RF-86 / RF-2: con `scan` activo, un código exacto se agrega y la búsqueda vuelve limpia. */
    public function test_exact_barcode_scan_adds_the_product_in_json(): void
    {
        $product = Product::factory()->create(['barcode' => '7801234567890', 'price' => 1990, 'stock' => 5]);
        Product::factory()->create(['name' => 'Otro producto', 'barcode' => '7809999999999']);

        $response = $this->getJson('/pos?q=7801234567890');

        $response->assertOk();
        $this->assertSame($product->id, $response->json('scanned_product_id'));
        $this->assertSame(1, $response->json('cart.totalQuantity'));
        $this->assertSame(1990, $response->json('totals.total'));
        $this->assertCount(2, $response->json('results'), 'El término se limpia: la lista vuelve completa.');
    }

    /** RF-86: la búsqueda al teclear (`scan=0`) nunca modifica el carrito. */
    public function test_live_search_with_scan_disabled_never_touches_the_cart(): void
    {
        $product = Product::factory()->create(['barcode' => '7801234567890', 'stock' => 5]);

        $response = $this->getJson('/pos?q=7801234567890&scan=0');

        $response->assertOk();
        $this->assertNull($response->json('scanned_product_id'));
        $this->assertTrue($response->json('cart.isEmpty'));
        $this->assertSame([$product->id], array_column($response->json('results'), 'id'));
    }

    /** RF-90: un código exacto sin stock se rechaza en JSON con el motivo y el carrito intacto. */
    public function test_barcode_scan_of_a_sold_out_product_is_rejected_in_json(): void
    {
        $inCart = Product::factory()->create(['stock' => 5]);
        $soldOut = Product::factory()->create(['barcode' => '7801234567890', 'stock' => 0]);
        $this->postJson('/pos/cart', ['product_id' => $inCart->id]);

        $response = $this->getJson('/pos?q='.$soldOut->barcode);

        $response->assertStatus(422);
        $this->assertSame(__('pos.errors.product_out_of_stock'), $response->json('errors.barcode.0'));
        $this->assertSame(1, $this->getJson('/pos')->json('cart.totalQuantity'));
    }

    /** RF-90: un código de un producto no vendible se rechaza en JSON con el motivo. */
    public function test_barcode_scan_of_a_non_sellable_product_is_rejected_in_json(): void
    {
        Product::factory()->create(['barcode' => '7801234567890', 'sellable' => false]);

        $response = $this->getJson('/pos?q=7801234567890');

        $response->assertStatus(422);
        $this->assertSame(__('pos.errors.product_not_sellable'), $response->json('errors.barcode.0'));
    }

    /** RF-85: el JSON del carrito trae las líneas y los totales con todos los campos que se anuncian. */
    public function test_cart_payload_has_the_line_and_totals_fields(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol 500 mg', 'price' => 1000, 'stock' => 10]);
        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->patchJson("/pos/cart/{$product->id}", ['quantity' => 2]);

        $line = $response->json('cart.lines.0');
        $this->assertSame(
            ['line_discount', 'line_subtotal', 'name', 'product_id', 'quantity', 'unit_price'],
            collect(array_keys($line))->sort()->values()->all(),
        );
        $this->assertSame(1000, $line['unit_price']);
        $this->assertSame(2000, $line['line_subtotal']);
        $response->assertJsonStructure(['totals' => [
            'subtotal', 'subtotal_after_line_discounts', 'line_discounts', 'global_discount', 'net', 'iva', 'total',
        ], 'manual_discount']);
    }

    /** RF-85: toda operación del carrito devuelve el fragmento del carrito ya pintado por el servidor. */
    public function test_cart_operations_return_the_rendered_cart_fragment(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol 500 mg', 'price' => 1990, 'stock' => 10]);

        $added = $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $html = $added->json('fragments.cart');

        $this->assertStringContainsString('Paracetamol 500 mg', $html);
        $this->assertStringContainsString('data-testid="cart-total">$1.990</dd>', $html);
        $added->assertJsonMissingPath('fragments.results');

        $updated = $this->patchJson("/pos/cart/{$product->id}", ['quantity' => 3]);
        $this->assertStringContainsString('$5.970', $updated->json('fragments.cart'));

        $removed = $this->deleteJson("/pos/cart/{$product->id}");
        $this->assertStringContainsString(__('pos.cash.empty'), $removed->json('fragments.cart'));
        $this->assertStringNotContainsString('Paracetamol 500 mg', $removed->json('fragments.cart'));
    }

    /** RF-85: las líneas del fragmento traen los ganchos que usan cart.js y stock.js. */
    public function test_cart_fragment_carries_the_hooks_the_js_relies_on(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol 500 mg', 'stock' => 10]);

        $html = $this->postJson('/pos/cart', ['product_id' => $product->id])->json('fragments.cart');

        $this->assertStringContainsString('data-cart-line', $html);
        $this->assertStringContainsString('data-product-id="'.$product->id.'"', $html);
        $this->assertStringContainsString('data-product-name="Paracetamol 500 mg"', $html);
        $this->assertStringContainsString('data-line-controls', $html);
        $this->assertStringContainsString('id="qty-'.$product->id.'"', $html);
        $this->assertStringContainsString('action="'.route('pos.cart.update', $product->id).'"', $html);
        $this->assertStringContainsString('action="'.route('pos.cart.remove', $product->id).'"', $html);
        $this->assertSame(2, substr_count($html, 'data-pos-async="cart"'));
        $this->assertStringContainsString('data-line-remove', $html);
    }

    /** RNF-30: el nombre de un producto nunca llega como HTML sin escapar. */
    public function test_cart_fragment_escapes_product_names(): void
    {
        $product = Product::factory()->create(['name' => '<img src=x onerror=alert(1)>', 'stock' => 10]);

        $html = $this->postJson('/pos/cart', ['product_id' => $product->id])->json('fragments.cart');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    /** RF-90: una cantidad rechazada deja el carrito exactamente como estaba y trae el motivo en texto. */
    public function test_rejected_update_keeps_the_cart_and_explains_why(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 2]);
        $this->postJson('/pos/cart', ['product_id' => $product->id]);

        $response = $this->patchJson("/pos/cart/{$product->id}", ['quantity' => 5]);

        $response->assertStatus(422);
        $this->assertSame(__('pos.errors.quantity_available', ['stock' => 2]), $response->json('errors.quantity.0'));
        $response->assertJsonMissingPath('fragments');

        $cart = $this->getJson('/pos');
        $this->assertSame(1, $cart->json('cart.totalQuantity'));
        $this->assertSame(1000, $cart->json('totals.total'));
    }

    /** RF-90: agregar un producto agotado no toca las líneas que ya había. */
    public function test_rejected_add_keeps_existing_lines(): void
    {
        $inCart = Product::factory()->create(['stock' => 5]);
        $soldOut = Product::factory()->create(['stock' => 0]);
        $this->postJson('/pos/cart', ['product_id' => $inCart->id]);

        $response = $this->postJson('/pos/cart', ['product_id' => $soldOut->id]);

        $response->assertStatus(422);
        $this->assertSame(__('pos.errors.product_out_of_stock'), $response->json('errors.product_id.0'));
        $lines = $this->getJson('/pos')->json('cart.lines');
        $this->assertCount(1, $lines);
        $this->assertSame($inCart->id, $lines[0]['product_id']);
    }

    /** RF-90: un producto que ya no existe responde 404 en JSON (el JS refresca el carrito). */
    public function test_unknown_product_in_the_cart_responds_404_in_json(): void
    {
        $this->patchJson('/pos/cart/99999', ['quantity' => 1])->assertNotFound();
        $this->deleteJson('/pos/cart/99999')->assertNotFound();
    }

    /** RF-88: una línea cuyo producto salió del catálogo se puede quitar (así desaparece del carrito). */
    public function test_a_line_whose_product_was_deactivated_can_still_be_removed(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $this->postJson('/pos/cart', ['product_id' => $product->id]);
        $product->forceFill(['active' => false])->save();

        $response = $this->deleteJson("/pos/cart/{$product->id}");

        $response->assertOk();
        $this->assertTrue($response->json('cart.isEmpty'));
    }

    /** RF-85: las peticiones AJAX clásicas (X-Requested-With) también reciben JSON. */
    public function test_xhr_requests_receive_json_without_an_accept_header(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post('/pos/cart', ['product_id' => $product->id]);

        $response->assertOk();
        $this->assertSame(1, $response->json('cart.totalQuantity'));
    }

    /** RF-91: sin JavaScript la misma ruta responde con redirección, como siempre. */
    public function test_html_requests_still_redirect_without_javascript(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->post('/pos/cart', ['product_id' => $product->id])->assertRedirect('/pos');
        $this->patch("/pos/cart/{$product->id}", ['quantity' => 2])->assertRedirect('/pos');
        $this->delete("/pos/cart/{$product->id}")->assertRedirect('/pos');
    }

    /** RF-91: la página trae formularios completos (método, acción y token) como respaldo. */
    public function test_page_keeps_working_forms_as_the_fallback(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol 500 mg', 'stock' => 5]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $html = $this->get('/pos')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<form method="GET" action="'.preg_quote(route('pos.index'), '/').'"/', $html);
        $this->assertMatchesRegularExpression('/<form[^>]*method="POST"[^>]*action="'.preg_quote(route('pos.cart.add'), '/').'"/', $html);
        $this->assertMatchesRegularExpression('/<form[^>]*method="POST"[^>]*action="'.preg_quote(route('pos.cart.update', $product->id), '/').'"/', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    /** RNF-32: el POS declara las zonas aria-live y las direcciones que usa el JS. */
    public function test_page_declares_live_regions_and_the_js_configuration(): void
    {
        $html = $this->get('/pos')->assertOk()->getContent();

        $this->assertStringContainsString('id="pos-root"', $html);
        $this->assertMatchesRegularExpression('/id="pos-cart"[^>]*aria-live="polite"/', $html);
        $this->assertMatchesRegularExpression('/id="pos-status"[^>]*role="status"/', $html);
        $this->assertMatchesRegularExpression('/id="pos-alert"[^>]*role="alert"/', $html);
        $this->assertStringContainsString('id="pos-results"', $html);
        $this->assertStringContainsString('data-stock-url="'.route('pos.stock').'"', $html);
        $this->assertStringContainsString('data-search-url="'.route('pos.index').'"', $html);
        $this->assertStringContainsString('data-login-url="'.route('login').'"', $html);
        $this->assertStringContainsString('data-search-delay="300"', $html);
        $this->assertStringContainsString('data-stock-interval="10000"', $html);
    }

    /** RNF-25: los textos del JS salen de lang/es/pos.php (`pos.async`) y llegan a la página. */
    public function test_async_messages_live_in_the_lang_file_and_reach_the_page(): void
    {
        $messages = __('pos.async');

        $this->assertIsArray($messages);
        foreach ([
            'network', 'server', 'generic', 'forbidden', 'not_found', 'session_expired', 'csrf',
            'results_none', 'results_one', 'results_many', 'scanned', 'stock_out', 'stock_low',
            'stock_inactive_removed', 'stock_note_out', 'stock_note_low', 'dismiss',
        ] as $key) {
            $this->assertArrayHasKey($key, $messages, "Falta pos.async.{$key}");
            $this->assertNotSame("pos.async.{$key}", $messages[$key]);
        }

        $html = $this->get('/pos')->assertOk()->getContent();
        $encoded = e(json_encode($messages, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('data-messages="'.$encoded.'"', $html);
    }

    /** RF-85: la página enlaza el script del POS asíncrono. */
    public function test_page_loads_the_async_pos_script(): void
    {
        $html = $this->get('/pos')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script type="module"[^>]*src="[^"]*/cart-[^"]+\.js"#', $html);
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
