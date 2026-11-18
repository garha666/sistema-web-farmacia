<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Pos\Enum\SaleStatus;
use App\Pos\Enum\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T18 - RNF-15: la carga de un panel o de una lista no ejecuta una consulta por
 * cada elemento. Cada pantalla se mide con una lista corta y con una lista
 * larga: el número de consultas debe ser el mismo.
 */
final class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private int $queries = 0;

    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (): void {
            $this->queries++;
        });
    }

    public function test_the_admin_panel_keeps_a_constant_query_count_as_the_lists_grow(): void
    {
        $admin = User::factory()->admin()->create();

        // Ambas cargas llevan datos: con listas vacías Eloquent omite sus
        // consultas de precarga y la comparación no mediría lo mismo.
        $this->createInventory(3);
        $this->createSales($admin, 3);
        $this->createAuditLogs();

        $small = $this->countFor(fn () => $this->actingAs($admin)->get(route('dashboard.admin'))->assertOk());

        $this->createInventory(40);
        $this->createSales($admin, 40);
        $this->createAuditLogs();

        $large = $this->countFor(fn () => $this->actingAs($admin)->get(route('dashboard.admin'))->assertOk());

        $this->assertSame($small, $large, "El panel del Administrador pasó de {$small} a {$large} consultas.");
    }

    public function test_the_warehouse_panel_keeps_a_constant_query_count_as_the_lists_grow(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();

        $this->createInventory(3);

        $small = $this->countFor(fn () => $this->actingAs($bodeguero)->get(route('dashboard.bodeguero'))->assertOk());

        $this->createInventory(40);

        $large = $this->countFor(fn () => $this->actingAs($bodeguero)->get(route('dashboard.bodeguero'))->assertOk());

        $this->assertSame($small, $large, "El panel del Bodeguero pasó de {$small} a {$large} consultas.");
    }

    public function test_the_sales_history_keeps_a_constant_query_count_as_the_list_grows(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createSales($admin, 3);

        $small = $this->countFor(fn () => $this->actingAs($admin)->get(route('admin.sales.index'))->assertOk());

        $this->createSales($admin, 45);

        $large = $this->countFor(fn () => $this->actingAs($admin)->get(route('admin.sales.index'))->assertOk());

        $this->assertSame($small, $large, "El historial pasó de {$small} a {$large} consultas.");
    }

    public function test_the_product_catalog_keeps_a_constant_query_count_as_the_list_grows(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(3)->create();

        $small = $this->countFor(fn () => $this->actingAs($admin)->get(route('inventory.products.index'))->assertOk());

        Product::factory()->count(60)->create();

        $large = $this->countFor(fn () => $this->actingAs($admin)->get(route('inventory.products.index'))->assertOk());

        $this->assertSame($small, $large, "El catálogo pasó de {$small} a {$large} consultas.");
    }

    public function test_the_movement_log_keeps_a_constant_query_count_as_the_list_grows(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $this->createInventory(3);

        $small = $this->countFor(fn () => $this->actingAs($bodeguero)->get(route('inventory.movements.index'))->assertOk());

        $this->createInventory(60);

        $large = $this->countFor(fn () => $this->actingAs($bodeguero)->get(route('inventory.movements.index'))->assertOk());

        $this->assertSame($small, $large, "Los movimientos pasaron de {$small} a {$large} consultas.");
    }

    private function countFor(callable $callback): int
    {
        $this->queries = 0;

        $callback();

        return $this->queries;
    }

    /**
     * Productos bajo el mínimo con su movimiento, para el panel del Bodeguero.
     */
    private function createInventory(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $product = Product::factory()->create(['stock' => 1, 'min_stock' => 5, 'active' => true]);

            StockMovement::query()->create([
                'product_id' => $product->id,
                'user_id' => null,
                'type' => StockMovementType::in,
                'quantity' => 1,
                'resulting_stock' => 1,
                'note' => 'Carga',
            ]);
        }
    }

    private function createSales(User $admin, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Sale::query()->create([
                'token' => (string) Str::uuid(),
                'subtotal' => 1000,
                'net' => 840,
                'iva' => 160,
                'total' => 1000,
                'payment_method' => 'cash',
                'status' => SaleStatus::confirmed,
                'sold_at' => now(),
                'user_id' => $admin->id,
            ]);
        }
    }

    private function createAuditLogs(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            AuditLog::query()->create([
                'user_id' => $user->id,
                'action' => 'sale.void',
                'reason' => 'Motivo '.$i,
            ]);
        }
    }
}
