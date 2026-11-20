<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T39 - RF-96: reporte de inventario.
 *
 * El Bodeguero y el Administrador ven productos bajo el mínimo, sin stock,
 * la valorización del inventario y los movimientos de un rango de fechas.
 * Técnico y Químico reciben 403. El reporte es de solo lectura (RF-98).
 */
final class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    private User $bodeguero;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bodeguero = User::factory()->bodeguero()->create();
        $this->admin = User::factory()->admin()->create();
    }

    /** RF-96: el Bodeguero y el Administrador abren el reporte. */
    public function test_bodeguero_and_admin_open_the_inventory_report(): void
    {
        foreach ([$this->bodeguero, $this->admin] as $user) {
            $this->actingAs($user)
                ->get('/admin/reportes/inventario')
                ->assertOk();
        }
    }

    /** RNF-37: Técnico y Químico no ven el inventario. */
    public function test_tecnico_and_quimico_receive_forbidden(): void
    {
        foreach ([User::factory()->tecnico()->create(), User::factory()->quimico()->create()] as $user) {
            $this->actingAs($user)
                ->get('/admin/reportes/inventario')
                ->assertForbidden();
        }
    }

    /** RNF-37: sin sesión no se muestra ningún dato. */
    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/admin/reportes/inventario')->assertRedirect('/login');
    }

    /** RF-96: bajo el mínimo y sin stock salen en listas separadas; los productos sanos no salen. */
    public function test_products_below_minimum_and_out_of_stock_are_listed_apart(): void
    {
        Product::factory()->create(['name' => 'Producto Escaso', 'stock' => 2, 'min_stock' => 10]);
        Product::factory()->create(['name' => 'Producto Agotado', 'stock' => 0, 'min_stock' => 5]);
        Product::factory()->create(['name' => 'Producto Sano', 'stock' => 50, 'min_stock' => 5]);

        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $response->assertOk();
        $this->assertSame(['Producto Escaso'], $response->viewData('belowMinimum')->pluck('name')->all());
        $this->assertSame(['Producto Agotado'], $response->viewData('outOfStock')->pluck('name')->all());
        $response->assertSee('Producto Escaso');
        $response->assertSee('Producto Agotado');
        $response->assertDontSee('Producto Sano');
    }

    /** RF-96: un producto con existencia igual al mínimo cuenta como bajo el mínimo (S-11). */
    public function test_a_product_at_its_minimum_counts_as_below_minimum(): void
    {
        Product::factory()->create(['name' => 'En el límite', 'stock' => 5, 'min_stock' => 5]);

        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $this->assertSame(['En el límite'], $response->viewData('belowMinimum')->pluck('name')->all());
    }

    /** RF-96: los productos dados de baja del catálogo no entran al reporte. */
    public function test_inactive_products_are_left_out(): void
    {
        Product::factory()->create(['name' => 'Producto de Baja', 'stock' => 0, 'min_stock' => 5, 'active' => false]);

        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $response->assertDontSee('Producto de Baja');
        $this->assertCount(0, $response->viewData('outOfStock'));
    }

    /** RF-96, S-20: la valorización es existencia × precio de venta de los productos vigentes. */
    public function test_valuation_is_stock_times_sale_price(): void
    {
        Product::factory()->create(['stock' => 10, 'price' => 1500]);
        Product::factory()->create(['stock' => 3, 'price' => 2000]);
        Product::factory()->create(['stock' => 0, 'price' => 9999]);
        Product::factory()->create(['stock' => 100, 'price' => 100000, 'active' => false]);

        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $this->assertSame(21000, $response->viewData('valuation'));
        $response->assertSee('$21.000');
    }

    /** RF-96: lista los movimientos del rango con producto, tipo, cantidad, existencia, motivo y autor. */
    public function test_movements_of_the_range_are_listed_with_their_details(): void
    {
        $product = Product::factory()->create(['name' => 'Producto Movido', 'stock' => 15]);
        $this->movement($product, 'in', 5, 15, '2026-11-10 10:30:00', 'Reposición semanal');

        $response = $this->actingAs($this->bodeguero)
            ->get('/admin/reportes/inventario?from=2026-11-10&to=2026-11-10');

        $response->assertOk();
        $response->assertSeeInOrder([
            '10-11-2026 10:30',
            'Producto Movido',
            'Entrada',
            '+5',
            '15',
            'Reposición semanal',
            $this->bodeguero->name,
        ]);
    }

    /** RF-96: los movimientos fuera del rango no aparecen y los bordes del rango sí. */
    public function test_movements_outside_the_range_are_excluded(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->movement($product, 'in', 1, 11, '2026-11-09 23:59:59', 'Motivo víspera');
        $this->movement($product, 'in', 1, 12, '2026-11-10 00:00:00', 'Motivo inicio');
        $this->movement($product, 'out', 1, 11, '2026-11-12 23:59:59', 'Motivo cierre');
        $this->movement($product, 'in', 1, 12, '2026-11-13 00:00:00', 'Motivo después');

        $response = $this->actingAs($this->bodeguero)
            ->get('/admin/reportes/inventario?from=2026-11-10&to=2026-11-12');

        $response->assertSee('Motivo inicio');
        $response->assertSee('Motivo cierre');
        $response->assertDontSee('Motivo víspera');
        $response->assertDontSee('Motivo después');
    }

    /** Caso límite 1: sin fechas el reporte muestra los movimientos de hoy (RNF-36). */
    public function test_without_dates_the_report_shows_today(): void
    {
        $today = now((string) config('pos.timezone'));
        $product = Product::factory()->create(['stock' => 10]);
        $this->movement($product, 'in', 1, 11, $today->format('Y-m-d').' 09:00:00', 'Motivo de hoy');
        $this->movement($product, 'in', 1, 12, $today->copy()->subDay()->format('Y-m-d').' 09:00:00', 'Motivo de ayer');

        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $response->assertSee('Motivo de hoy');
        $response->assertDontSee('Motivo de ayer');
        $response->assertSee('value="'.$today->format('Y-m-d').'"', false);
    }

    /** Caso límite 2: `from` posterior a `to` se informa como error de validación. */
    public function test_from_after_to_is_a_validation_error(): void
    {
        $response = $this->actingAs($this->bodeguero)
            ->from('/admin/reportes/inventario')
            ->get('/admin/reportes/inventario?from=2026-11-12&to=2026-11-10');

        $response->assertSessionHasErrors('to');
    }

    /** Caso límite 3: sin datos el reporte muestra ceros y mensajes de lista vacía, sin error. */
    public function test_an_empty_database_shows_zeros_and_empty_messages(): void
    {
        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $response->assertOk();
        $this->assertSame(0, $response->viewData('valuation'));
        $response->assertSee(__('pos.reportes.inventario.empty_below_minimum'));
        $response->assertSee(__('pos.reportes.inventario.empty_out_of_stock'));
        $response->assertSee(__('pos.reportes.inventario.empty_movements'));
    }

    /** RNF-37: la pantalla usa textos en español, no claves de traducción sin resolver. */
    public function test_the_screen_has_no_untranslated_keys(): void
    {
        $product = Product::factory()->create(['stock' => 0, 'min_stock' => 5]);
        $this->movement($product, 'sale', -1, 0, now((string) config('pos.timezone'))->format('Y-m-d H:i:s'), null);

        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $response->assertOk();
        $response->assertDontSee('pos.reportes');
        $response->assertDontSee('pos.common');
    }

    /** RNF-34, RNF-35: el número de consultas no crece con los productos ni con los movimientos. */
    public function test_the_query_count_does_not_grow_with_the_data(): void
    {
        $today = now((string) config('pos.timezone'))->format('Y-m-d H:i:s');
        Product::factory()->count(25)->create(['stock' => 0, 'min_stock' => 5]);
        foreach (Product::query()->get() as $product) {
            $this->movement($product, 'in', 1, 1, $today, 'Motivo');
        }

        $this->actingAs($this->bodeguero);
        DB::enableQueryLog();
        $this->get('/admin/reportes/inventario')->assertOk();

        $this->assertLessThanOrEqual(10, count(DB::getQueryLog()));
    }

    /** RF-96, RF-98: el reporte no crea ni modifica ventas, movimientos, productos ni auditoría. */
    public function test_the_report_is_read_only(): void
    {
        $product = Product::factory()->create(['stock' => 3, 'min_stock' => 5]);
        $this->movement($product, 'in', 3, 3, now((string) config('pos.timezone'))->format('Y-m-d H:i:s'), null);
        $before = [
            Sale::query()->count(),
            StockMovement::query()->count(),
            AuditLog::query()->count(),
            (int) $product->fresh()->stock,
        ];

        $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario')->assertOk();

        $this->assertSame($before, [
            Sale::query()->count(),
            StockMovement::query()->count(),
            AuditLog::query()->count(),
            (int) $product->fresh()->stock,
        ]);
    }

    /** RF-96: la pantalla no ofrece crear ni editar productos. */
    public function test_the_screen_offers_no_editing(): void
    {
        Product::factory()->create(['stock' => 0, 'min_stock' => 5]);

        $response = $this->actingAs($this->bodeguero)->get('/admin/reportes/inventario');

        $response->assertDontSee('name="_method"', false);
        $response->assertDontSee('inventory/adjustments', false);
    }

    private function movement(Product $product, string $type, int $quantity, int $resulting, string $at, ?string $note): StockMovement
    {
        $movement = new StockMovement([
            'product_id' => $product->id,
            'user_id' => $this->bodeguero->id,
            'type' => $type,
            'quantity' => $quantity,
            'resulting_stock' => $resulting,
            'note' => $note,
        ]);
        $movement->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        return $movement;
    }
}
