<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T39 - RF-96: reporte de inventario.
 *
 * El Bodeguero ve productos bajo mínimo, sin stock y movimientos.
 * El Administrador ve todos; Técnico y Químico reciben 403.
 */
final class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bodeguero = User::factory()->bodeguero()->create();
        $this->admin = User::factory()->admin()->create();
    }

    /** RF-96: el bodeguero ve productos bajo mínimo y sin stock. */
    public function test_bodeguero_sees_inventory_report(): void
    {
        Product::factory()->create(['name' => 'Bajo mínimo', 'stock' => 2, 'min_stock' => 10]);
        Product::factory()->create(['name' => 'Sin stock', 'stock' => 0, 'min_stock' => 5]);
        Product::factory()->create(['name' => 'Bien', 'stock' => 50, 'min_stock' => 5]);

        $response = $this->actingAs($this->bodeguero)
            ->get('/admin/reportes/inventario');

        $response->assertOk();
        $response->assertSee('Bajo mínimo');
        $response->assertSee('Sin stock');
        $response->assertSee('Bien');
    }

    /** RF-96: el administrador ve el reporte de inventario. */
    public function test_admin_sees_inventory_report(): void
    {
        Product::factory()->create(['name' => 'Producto A', 'stock' => 10]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/inventario');

        $response->assertOk();
        $response->assertSee('Producto A');
    }

    /** RF-96: técnicos y químicos no pueden ver inventario. */
    public function test_tecnico_and_quimico_receive_forbidden(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $quimico = User::factory()->quimico()->create();

        foreach ([$tecnico, $quimico] as $user) {
            $response = $this->actingAs($user)
                ->get('/admin/reportes/inventario');

            $response->assertForbidden();
        }
    }

    /** RF-96: el bodeguero ve movimientos de stock del rango. */
    public function test_bodeguero_sees_stock_movements(): void
    {
        $product = Product::factory()->create(['name' => 'Producto con movimiento', 'stock' => 10]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 5,
            'resulting_stock' => 15,
            'note' => 'Reposición',
            'user_id' => $this->bodeguero->id,
            'created_at' => '2026-11-01 10:00:00',
        ]);

        $response = $this->actingAs($this->bodeguero)
            ->get('/admin/reportes/inventario');

        $response->assertOk();
        $response->assertSee('Reposición');
    }
}
