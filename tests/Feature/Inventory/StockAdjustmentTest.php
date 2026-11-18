<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Pos\Enum\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T17 - RF-63 a RF-67, RF-73 y RF-74: panel del Bodeguero, prioridad de
 * reposición, movimientos trazables y ajuste de stock.
 */
final class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $bodeguero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bodeguero = User::factory()->bodeguero()->create(['name' => 'Bodeguero Uno']);
        $this->actingAs($this->bodeguero);
    }

    public function test_the_panel_summarizes_active_below_minimum_out_of_stock_and_movements_today(): void
    {
        Product::factory()->create(['name' => 'Agotado', 'stock' => 0, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'Bajo mínimo', 'stock' => 2, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'Suficiente', 'stock' => 9, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'Fuera de catálogo', 'stock' => 0, 'min_stock' => 5, 'active' => false]);

        $response = $this->get(route('dashboard.bodeguero'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.bodeguero.summary_heading'));
        $response->assertSeeText(__('pos.panel.bodeguero.active_count'));
        $response->assertSeeText(__('pos.panel.bodeguero.below_minimum'));
        $response->assertSeeText(__('pos.panel.bodeguero.out_of_stock'));
        $response->assertSeeText(__('pos.panel.bodeguero.movements_today'));

        // 3 activos, 2 bajo el mínimo (0 y 2) y 1 sin stock.
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/id="inventory-active"[^>]*>\s*3\s*</', $html);
        $this->assertMatchesRegularExpression('/id="inventory-below"[^>]*>\s*2\s*</', $html);
        $this->assertMatchesRegularExpression('/id="inventory-out"[^>]*>\s*1\s*</', $html);
        $this->assertMatchesRegularExpression('/id="inventory-movements"[^>]*>\s*0\s*</', $html);
        $response->assertDontSeeText('Fuera de catálogo');
    }

    public function test_the_replenishment_priority_lists_products_ascending_by_stock(): void
    {
        Product::factory()->create(['name' => 'Mayor existencia', 'stock' => 4, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'Menor existencia', 'stock' => 1, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'Existencia media', 'stock' => 3, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'Producto holgado', 'stock' => 90, 'min_stock' => 5, 'active' => true]);

        $response = $this->get(route('dashboard.bodeguero'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.bodeguero.replenishment_heading'));
        $response->assertSeeInOrder([
            'Menor existencia',
            'Existencia media',
            'Mayor existencia',
            __('pos.inventory.unit'),
        ]);

        // El producto holgado no está en la tabla de prioridad (sí puede
        // aparecer en el selector del ajuste, que ofrece todo el catálogo).
        $table = $this->section($response->getContent(), 'warehouse-replenishment');
        $this->assertStringNotContainsString('Producto holgado', $table);
    }

    public function test_the_panel_lists_recent_movements_with_type_reason_author_and_date(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol', 'stock' => 8, 'min_stock' => 2]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'user_id' => $this->bodeguero->id,
            'type' => StockMovementType::in,
            'quantity' => 6,
            'resulting_stock' => 8,
            'note' => 'Reposición de bodega',
        ]);

        // Un movimiento de ayer no cuenta en el resumen del día ni se lista.
        $old = Product::factory()->create(['name' => 'Producto de ayer', 'stock' => 3, 'min_stock' => 1]);
        StockMovement::query()->create([
            'product_id' => $old->id,
            'user_id' => $this->bodeguero->id,
            'type' => StockMovementType::out,
            'quantity' => -1,
            'resulting_stock' => 3,
            'note' => 'Movimiento de ayer',
        ])->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $response = $this->get(route('dashboard.bodeguero'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.bodeguero.movements_heading'));
        $response->assertSeeText('Paracetamol');
        $response->assertSeeText(__('pos.inventory.movement_type.in'));
        $response->assertSeeText('Reposición de bodega');
        $response->assertSeeText('Bodeguero Uno');
        $response->assertSeeText(now()->format('d-m-Y H:i'));
        $response->assertSeeText(__('pos.inventory.unit'));

        $this->assertMatchesRegularExpression('/id="inventory-movements"[^>]*>\s*1\s*</', $response->getContent());

        // RF-65 lista los últimos movimientos sin corte de día, así que el de
        // ayer sigue visible; sólo el contador del día (RF-63) lo excluye.
        $table = $this->section($response->getContent(), 'warehouse-movements');
        $this->assertStringContainsString('Producto de ayer', $table);
        $this->assertStringContainsString('Movimiento de ayer', $table);
    }

    public function test_the_panel_offers_the_catalog_and_the_controlled_register_in_read_mode(): void
    {
        $response = $this->get(route('dashboard.bodeguero'));

        $response->assertOk();
        $response->assertSee(route('inventory.products.index'), false);
        $response->assertSee(route('admin.controlled-register.index'), false);
    }

    public function test_the_bodeguero_can_read_the_controlled_register(): void
    {
        $this->get(route('admin.controlled-register.index'))
            ->assertOk()
            ->assertSeeText('Libro de controlados');
    }

    public function test_an_inbound_adjustment_writes_the_movement_and_raises_the_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'min_stock' => 2]);

        $response = $this->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 5,
            'reason' => 'Compra a proveedor',
        ]);

        $response->assertRedirect(route('dashboard.bodeguero'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(15, $product->fresh()->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $this->bodeguero->id,
            'type' => 'in',
            'quantity' => 5,
            'resulting_stock' => 15,
            'note' => 'Compra a proveedor',
        ]);
    }

    public function test_an_outbound_adjustment_lowers_the_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'min_stock' => 2]);

        $this->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 4,
            'reason' => 'Merma por vencimiento',
        ])->assertSessionHasNoErrors();

        $this->assertSame(6, $product->fresh()->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => -4,
            'resulting_stock' => 6,
            'note' => 'Merma por vencimiento',
        ]);
    }

    public function test_an_outbound_adjustment_beyond_the_stock_is_rejected_with_the_available_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3, 'min_stock' => 1]);

        $response = $this->from(route('dashboard.bodeguero'))->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 5,
            'reason' => 'Merma',
        ]);

        $response->assertSessionHasErrors([
            'quantity' => __('pos.panel.bodeguero.error.insufficient_stock', ['available' => 3]),
        ]);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_missing_reason_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'min_stock' => 2]);

        $this->from(route('dashboard.bodeguero'))->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 5,
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_non_positive_quantity_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'min_stock' => 2]);

        $this->from(route('dashboard.bodeguero'))->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 0,
            'reason' => 'Compra',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_the_stock_is_rebuilt_as_the_initial_stock_plus_the_movements(): void
    {
        $product = Product::factory()->create(['stock' => 20, 'min_stock' => 2]);
        $initial = 20;

        $this->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 12,
            'reason' => 'Compra',
        ])->assertSessionHasNoErrors();

        $this->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 5,
            'reason' => 'Merma',
        ])->assertSessionHasNoErrors();

        $movements = StockMovement::query()->where('product_id', $product->id)->get();

        $this->assertSame($initial + (int) $movements->sum('quantity'), $product->fresh()->stock);
        $this->assertSame(27, $product->fresh()->stock);
        $this->assertSame(27, (int) $movements->sortBy('id')->last()->resulting_stock);
    }

    public function test_the_movements_are_consultable_in_their_own_screen(): void
    {
        $product = Product::factory()->create(['name' => 'Ibuprofeno', 'stock' => 7, 'min_stock' => 2]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'user_id' => $this->bodeguero->id,
            'type' => StockMovementType::out,
            'quantity' => -2,
            'resulting_stock' => 7,
            'note' => 'Ajuste de conteo',
        ]);

        $response = $this->get(route('inventory.movements.index'));

        $response->assertOk();
        $response->assertSeeText(__('pos.inventory.movements.heading'));
        $response->assertSeeText('Ibuprofeno');
        $response->assertSeeText(__('pos.inventory.movement_type.out'));
        $response->assertSeeText('Ajuste de conteo');
        $response->assertSeeText('Bodeguero Uno');
        $response->assertSeeText(now()->format('d-m-Y H:i'));
    }

    public function test_the_tecnico_cannot_adjust_stock_nor_read_the_movements(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'min_stock' => 2]);
        $tecnico = User::factory()->create(['role' => 'tecnico']);

        $this->actingAs($tecnico)->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 1,
            'reason' => 'No permitido',
        ])->assertForbidden();

        $this->actingAs($tecnico)->get(route('inventory.movements.index'))->assertForbidden();

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_the_admin_can_adjust_stock(): void
    {
        $product = Product::factory()->create(['stock' => 4, 'min_stock' => 2]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 6,
            'reason' => 'Compra',
        ])->assertSessionHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_the_adjustment_form_is_completable_with_the_keyboard(): void
    {
        $product = Product::factory()->create(['name' => 'Paracetamol', 'stock' => 10, 'min_stock' => 2]);

        $html = $this->get(route('dashboard.bodeguero'))->getContent();

        $this->assertStringContainsString(route('inventory.adjustments.store'), $html);
        foreach (['product_id', 'type', 'quantity', 'reason'] as $field) {
            $this->assertMatchesRegularExpression('/<label[^>]+for="'.$field.'"/', $html);
            $this->assertMatchesRegularExpression('/<(input|select)[^>]+name="'.$field.'"/', $html);
        }
        $this->assertMatchesRegularExpression('/<button[^>]+type="submit"/', $html);
        $this->assertSame(1, substr_count($html, 'name="product_id"'));
    }

    /**
     * HTML de la sección identificada por su `aria-labelledby`, para acotar
     * las comprobaciones a un bloque concreto del panel.
     */
    private function section(string $html, string $id): string
    {
        return Str::between($html, 'aria-labelledby="'.$id.'"', '</section>');
    }
}
