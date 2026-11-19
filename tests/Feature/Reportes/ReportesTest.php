<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T36 - RF-92, RF-93: reporte de ventas y exportación CSV.
 *
 * El Administrador ve un reporte de ventas por rango de fechas con totales
 * y puede exportarlo a CSV. Otros roles reciben 403.
 */
final class ReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->quimico = User::factory()->quimico()->create();
    }

    /** RF-92: el administrador ve el reporte de ventas por rango de fechas. */
    public function test_admin_sees_sales_report_with_totals(): void
    {
        $this->createSale('2026-11-01 10:00:00', 1990, 'cash');
        $this->createSale('2026-11-01 11:00:00', 4990, 'debit');

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01');

        $response->assertOk();
        $response->assertSee('6.980');
        $response->assertSee('2');
    }

    /** RF-92: roles que no son Admin reciben 403. */
    public function test_non_admin_roles_receive_forbidden(): void
    {
        $response = $this->actingAs($this->quimico)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01');

        $response->assertForbidden();
    }

    /** RF-93: el administrador exporta el reporte a CSV. */
    public function test_admin_exports_sales_report_to_csv(): void
    {
        $this->createSale('2026-11-01 10:00:00', 1990, 'cash');

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01&export=1');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $response->assertSee('$1.990');
    }

    /** RF-92: sin ventas en el rango, el reporte muestra ceros. */
    public function test_report_shows_zeros_when_no_sales(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01');

        $response->assertOk();
        $response->assertSee('0');
    }

    private function createSale(string $soldAt, int $total, string $method): void
    {
        Sale::query()->create([
            'token' => Str::uuid(),
            'subtotal' => $total,
            'net' => (int) round($total * 100 / 119),
            'iva' => $total - (int) round($total * 100 / 119),
            'total' => $total,
            'payment_method' => $method,
            'status' => 'confirmed',
            'sold_at' => $soldAt,
            'user_id' => $this->admin->id,
        ]);
    }
}
