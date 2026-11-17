<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T6 - RF-1 (búsqueda), RF-2 (agregar), RF-3 (cantidad y quitar),
 * RF-4 (totales), RF-5 (carrito vacío), RF-6 (carrito persistente).
 */
final class CartFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión.
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
    }

    public function test_cash_screen_shows_search_box_and_empty_cart(): void
    {
        $response = $this->get('/pos');

        $response->assertOk();
        $response->assertSee('Buscar producto');
        $response->assertSee('Carrito vacío');
    }

    public function test_search_finds_product_by_name(): void
    {
        Product::factory()->create(['name' => 'Paracetamol 500 mg']);

        $response = $this->get('/pos?q=paracetamol');

        $response->assertSee('Paracetamol 500 mg');
    }

    public function test_search_finds_product_by_active_ingredient(): void
    {
        Product::factory()->create([
            'name' => 'Tapsin',
            'active_ingredient' => 'Ibuprofeno',
        ]);

        $response = $this->get('/pos?q=ibuprofeno');

        $response->assertSee('Tapsin');
    }

    public function test_search_finds_product_by_barcode(): void
    {
        Product::factory()->create([
            'name' => 'Vitamina C',
            'barcode' => '7801234567890',
        ]);

        $response = $this->get('/pos?q=7801234567890');

        $response->assertSee('Vitamina C');
    }

    /**
     * B1 (RNF-3): el catálogo de demo se escanea en el mostrador, así que cada
     * producto trae un EAN-13 real, único y con dígito verificador correcto.
     */
    public function test_demo_catalog_products_have_unique_valid_ean13_barcodes(): void
    {
        $this->seed(ProductSeeder::class);

        $products = Product::query()->orderBy('id')->get();

        $this->assertCount(4, $products);
        $this->assertSame(4, $products->pluck('barcode')->unique()->count());

        foreach ($products as $product) {
            $this->assertNotNull($product->barcode, "El producto {$product->name} no tiene código de barras.");
            $this->assertTrue(
                $this->isValidEan13($product->barcode),
                "El código {$product->barcode} de {$product->name} no es un EAN-13 válido.",
            );
        }
    }

    private function isValidEan13(string $code): bool
    {
        if (preg_match('/^\d{13}$/', $code) !== 1) {
            return false;
        }

        $sum = 0;

        for ($position = 0; $position < 12; $position++) {
            $digit = (int) $code[$position];
            $sum += $position % 2 === 0 ? $digit : $digit * 3;
        }

        return (10 - $sum % 10) % 10 === (int) $code[12];
    }

    public function test_search_without_matches_shows_empty_message(): void
    {
        $response = $this->get('/pos?q=inexistente');

        $response->assertSee('Sin resultados');
    }

    public function test_adding_a_product_shows_it_in_the_cart_with_totals(): void
    {
        $product = Product::factory()->create(['price' => 1990]);

        $response = $this->post('/pos/cart', ['product_id' => $product->id]);

        $response->assertRedirect('/pos');
        $this->followRedirects($response)->assertSee('Paracetamol 500 mg');
        $this->followRedirects($response)->assertSee('$1.990');
    }

    public function test_adding_the_same_product_twice_increments_quantity(): void
    {
        $product = Product::factory()->create(['price' => 1990]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $response = $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->followRedirects($response)->assertSee('$3.980');
    }

    public function test_updating_quantity_recalculates_totals(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->patch("/pos/cart/{$product->id}", ['quantity' => 3]);

        $this->followRedirects($response)->assertSee('$3.000');
    }

    public function test_removing_a_line_leaves_the_cart_empty(): void
    {
        $product = Product::factory()->create();
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->delete("/pos/cart/{$product->id}");

        $this->followRedirects($response)->assertSee('Carrito vacío');
    }

    public function test_quantity_above_stock_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 2]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->patch("/pos/cart/{$product->id}", ['quantity' => 5]);

        $response->assertSessionHasErrors('quantity');
        $response->assertRedirect();
    }

    public function test_non_sellable_product_cannot_be_added(): void
    {
        $product = Product::factory()->create(['sellable' => false]);

        $response = $this->post('/pos/cart', ['product_id' => $product->id]);

        $response->assertSessionHasErrors('product_id');
    }

    public function test_cart_survives_a_page_reload(): void
    {
        $product = Product::factory()->create(['price' => 1500]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->get('/pos')
            ->assertSee($product->name)
            ->assertSee('$1.500');
    }

    public function test_checkout_is_blocked_when_cart_is_empty(): void
    {
        $response = $this->get('/pos');

        $response->assertSee('El carrito está vacío');
    }
}
