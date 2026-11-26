<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T16 - RF-68 a RF-72: catálogo, alta, edición, código de barras único y
 * válido, y baja de catálogo sin borrado.
 */
final class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_the_catalog_lists_price_category_stock_minimum_and_sellable(): void
    {
        Product::factory()->create([
            'name' => 'Tapsin Día',
            'active_ingredient' => 'Paracetamol',
            'barcode' => '4006381333931',
            'price' => 1990,
            'sale_category' => 'free',
            'stock' => 12,
            'min_stock' => 4,
            'sellable' => true,
            'active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Ibuprofeno 400 mg',
            'active_ingredient' => 'Ibuprofeno',
            'barcode' => '7801234567894',
            'price' => 2500,
            'sale_category' => 'controlled',
            'stock' => 3,
            'min_stock' => 5,
            'sellable' => false,
            'active' => true,
        ]);

        $response = $this->get(route('inventory.products.index'));

        $response->assertOk();
        $response->assertSeeText('Tapsin Día');
        $response->assertSeeText('Ibuprofeno 400 mg');
        $response->assertSee('$1.990');
        $response->assertSee('$2.500');
        $response->assertSeeText(__('pos.inventory.products.category.free'));
        $response->assertSeeText(__('pos.inventory.products.category.controlled'));
        $response->assertSeeText('12');
        $response->assertSeeText('3');
        $response->assertSeeText(__('pos.inventory.products.yes'));
        $response->assertSeeText(__('pos.inventory.products.no'));
    }

    public function test_the_catalog_searches_by_name_active_ingredient_and_barcode(): void
    {
        Product::factory()->create([
            'name' => 'Tapsin Día',
            'active_ingredient' => 'Paracetamol',
            'barcode' => '4006381333931',
        ]);
        Product::factory()->create([
            'name' => 'Ibuprofeno 400 mg',
            'active_ingredient' => 'Ibuprofeno',
            'barcode' => '7801234567894',
        ]);

        $this->get(route('inventory.products.index', ['q' => 'tapsin']))
            ->assertOk()
            ->assertSeeText('Tapsin Día')
            ->assertDontSeeText('Ibuprofeno 400 mg');

        $this->get(route('inventory.products.index', ['q' => 'paracetamol']))
            ->assertOk()
            ->assertSeeText('Tapsin Día')
            ->assertDontSeeText('Ibuprofeno 400 mg');

        $this->get(route('inventory.products.index', ['q' => '7801234567894']))
            ->assertOk()
            ->assertSeeText('Ibuprofeno 400 mg')
            ->assertDontSeeText('Tapsin Día');
    }

    /**
     * RF-69: alta de producto con sus datos comerciales y existencia inicial.
     */
    public function test_the_admin_creates_a_product_with_its_initial_stock(): void
    {
        $response = $this->post(route('inventory.products.store'), $this->storePayload());

        $response->assertRedirect(route('inventory.products.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', [
            'name' => 'Paracetamol 500 mg',
            'active_ingredient' => 'Paracetamol',
            'barcode' => '4006381333931',
            'price' => 1990,
            'sale_category' => 'free',
            'min_stock' => 5,
            'stock' => 10,
            'sellable' => true,
            'active' => true,
        ]);
    }

    public function test_a_product_can_be_created_without_a_barcode(): void
    {
        $this->post(route('inventory.products.store'), $this->storePayload([
            'name' => 'Sin código A',
            'barcode' => '',
        ]))->assertSessionHasNoErrors();

        $this->post(route('inventory.products.store'), $this->storePayload([
            'name' => 'Sin código B',
            'barcode' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Product::query()->whereNull('barcode')->count());
    }

    public function test_a_price_of_zero_or_less_is_rejected(): void
    {
        $this->post(route('inventory.products.store'), $this->storePayload(['price' => 0]))
            ->assertSessionHasErrors('price');

        $this->post(route('inventory.products.store'), $this->storePayload(['price' => -10]))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_a_duplicate_barcode_is_rejected_with_its_reason(): void
    {
        Product::factory()->create(['barcode' => '4006381333931']);

        $response = $this->from(route('inventory.products.create'))
            ->post(route('inventory.products.store'), $this->storePayload(['name' => 'Otro producto']));

        $response->assertSessionHasErrors([
            'barcode' => __('pos.inventory.products.error.barcode_duplicate'),
        ]);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_a_barcode_with_a_wrong_check_digit_is_rejected_with_its_reason(): void
    {
        $response = $this->from(route('inventory.products.create'))
            ->post(route('inventory.products.store'), $this->storePayload(['barcode' => '4006381333932']));

        $response->assertSessionHasErrors([
            'barcode' => __('pos.inventory.products.error.barcode_invalid'),
        ]);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_editing_the_price_does_not_change_confirmed_sales(): void
    {
        $product = Product::factory()->create([
            'barcode' => '4006381333931',
            'price' => 1990,
        ]);
        [$sale, $line] = $this->soldProduct($product, 1990);

        $response = $this->patch(
            route('inventory.products.update', $product),
            $this->updatePayload($product, ['price' => 2500]),
        );

        $response->assertRedirect(route('inventory.products.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(2500, $product->fresh()->price);
        $this->assertSame(1990, $sale->fresh()->total);
        $this->assertSame(1990, $line->fresh()->unit_price);
    }

    public function test_removing_sellable_blocks_new_sales_without_touching_history(): void
    {
        $product = Product::factory()->create([
            'barcode' => '4006381333931',
            'sellable' => true,
        ]);
        [$sale, $line] = $this->soldProduct($product, 1990);

        $this->patch(
            route('inventory.products.update', $product),
            $this->updatePayload($product, ['sellable' => 0]),
        )->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->sellable);
        $this->assertTrue($product->fresh()->active);

        $this->post('/pos/cart', ['product_id' => $product->id])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(1990, $sale->fresh()->total);
        $this->assertSame(1990, $line->fresh()->unit_price);
    }

    public function test_removing_from_the_catalog_deactivates_and_hides_it_from_the_cashier(): void
    {
        $product = Product::factory()->create([
            'name' => 'Tapsin Día',
            'barcode' => '4006381333931',
            'stock' => 5,
            'sellable' => true,
            'active' => true,
        ]);

        $this->patch(
            route('inventory.products.update', $product),
            $this->updatePayload($product, ['active' => 0]),
        )->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->active);
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        // Sigue en el catálogo para poder reactivarlo, marcado como fuera de catálogo.
        $this->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSeeText('Tapsin Día')
            ->assertSeeText(__('pos.inventory.products.out_of_catalog'));

        // Ya no aparece en la búsqueda de la caja ni se agrega al escanear.
        $this->get('/pos?q=Tapsin')->assertOk()->assertDontSeeText('Tapsin Día');
        $this->get('/pos?q=4006381333931')->assertOk()->assertDontSeeText('Tapsin Día');

        // Tampoco se puede agregar por un envío directo.
        $this->post('/pos/cart', ['product_id' => $product->id])
            ->assertSessionHasErrors('product_id');
    }

    public function test_the_warehouse_role_reads_the_catalog_but_cannot_create_or_edit(): void
    {
        $product = Product::factory()->create(['barcode' => '4006381333931']);
        $bodeguero = User::factory()->bodeguero()->create();

        $this->actingAs($bodeguero)
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertDontSee(route('inventory.products.create'), false);

        $this->actingAs($bodeguero)->get(route('inventory.products.create'))->assertForbidden();
        $this->actingAs($bodeguero)->post(route('inventory.products.store'), $this->storePayload())->assertForbidden();
        $this->actingAs($bodeguero)->get(route('inventory.products.edit', $product))->assertForbidden();
        $this->actingAs($bodeguero)
            ->patch(route('inventory.products.update', $product), $this->updatePayload($product))
            ->assertForbidden();
    }

    public function test_the_actions_column_only_appears_when_the_user_has_actions(): void
    {
        Product::factory()->create();
        $bodeguero = User::factory()->bodeguero()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($bodeguero)
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertDontSee('<th scope="col" class="px-4 py-3 text-right">'.__('pos.inventory.products.actions').'</th>', false);

        $this->actingAs($admin)
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSee('<th scope="col" class="px-4 py-3 text-right">'.__('pos.inventory.products.actions').'</th>', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Paracetamol 500 mg',
            'active_ingredient' => 'Paracetamol',
            'barcode' => '4006381333931',
            'price' => 1990,
            'sale_category' => 'free',
            'min_stock' => 5,
            'stock' => 10,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name,
            'active_ingredient' => $product->active_ingredient,
            'barcode' => $product->barcode,
            'price' => $product->price,
            'sale_category' => $product->sale_category->value,
            'min_stock' => $product->min_stock,
            'sellable' => $product->sellable ? 1 : 0,
            'active' => $product->active ? 1 : 0,
        ], $overrides);
    }

    /**
     * Venta confirmada con una línea del producto, para comprobar que editar el
     * precio no reescribe el historial (RF-70).
     *
     * @return array{0: Sale, 1: SaleLine}
     */
    private function soldProduct(Product $product, int $price): array
    {
        $sale = Sale::query()->create([
            'token' => (string) Str::uuid(),
            'subtotal' => $price,
            'net' => (int) round($price * 100 / 119),
            'iva' => $price - (int) round($price * 100 / 119),
            'total' => $price,
            'payment_method' => 'cash',
            'status' => SaleStatus::confirmed,
            'sold_at' => now(),
            'user_id' => $this->admin->id,
        ]);

        $line = SaleLine::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => $price,
            'quantity' => 1,
            'discount_amount' => 0,
            'subtotal' => $price,
        ]);

        return [$sale, $line];
    }
}
