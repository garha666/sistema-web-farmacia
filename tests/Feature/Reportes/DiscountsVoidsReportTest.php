<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T38 - RF-95: reporte de descuentos y anulaciones.
 *
 * El Químico ve sus propias autorizaciones; el Administrador ve todas.
 * Químico y Bodeguero reciben 403.
 */
final class DiscountsVoidsReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quimico = User::factory()->quimico()->create();
        $this->admin = User::factory()->admin()->create();
    }

    /** RF-95: el químico ve sus propias autorizaciones de descuento. */
    public function test_quimico_sees_own_discount_authorizations(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->quimico->id,
            'action' => 'sale.discount_authorized',
            'reason' => 'Descuento global 15%',
            'discount_type' => 'percent',
            'discount_value' => 150,
            'created_at' => '2026-11-01 10:00:00',
        ]);

        AuditLog::query()->create([
            'user_id' => $this->quimico->id,
            'action' => 'sale.line_discount_authorized',
            'reason' => 'Descuento de línea 10%',
            'discount_type' => 'percent',
            'discount_value' => 100,
            'created_at' => '2026-11-01 11:00:00',
        ]);

        // Otra autorización de un químico diferente no debe aparecer.
        $other = User::factory()->quimico()->create();
        AuditLog::query()->create([
            'user_id' => $other->id,
            'action' => 'sale.discount_authorized',
            'reason' => 'Otro químico',
            'discount_type' => 'percent',
            'discount_value' => 200,
            'created_at' => '2026-11-01 12:00:00',
        ]);

        $response = $this->actingAs($this->quimico)
            ->get('/admin/reportes/descuentos-anulaciones');

        $response->assertOk();
        $response->assertSee('Descuento global 15%');
        $response->assertSee('Descuento de línea 10%');
        $response->assertDontSee('Otro químico');
    }

    /** RF-95: el administrador ve todas las autorizaciones. */
    public function test_admin_sees_all_discount_authorizations(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->quimico->id,
            'action' => 'sale.discount_authorized',
            'reason' => 'Descuento del químico',
            'discount_type' => 'percent',
            'discount_value' => 100,
            'created_at' => '2026-11-01 10:00:00',
        ]);

        $other = User::factory()->quimico()->create();
        AuditLog::query()->create([
            'user_id' => $other->id,
            'action' => 'sale.line_discount_authorized',
            'reason' => 'Descuento de otro químico',
            'discount_type' => 'percent',
            'discount_value' => 200,
            'created_at' => '2026-11-01 11:00:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/descuentos-anulaciones');

        $response->assertOk();
        $response->assertSee('Descuento del químico');
        $response->assertSee('Descuento de otro químico');
    }

    /** RF-95: roles que no son químico ni admin reciben 403. */
    public function test_tecnico_and_bodeguero_receive_forbidden(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $bodeguero = User::factory()->bodeguero()->create();

        foreach ([$tecnico, $bodeguero] as $user) {
            $response = $this->actingAs($user)
                ->get('/admin/reportes/descuentos-anulaciones');

            $response->assertForbidden();
        }
    }

    /** RF-95: sin autorizaciones, el reporte muestra cero. */
    public function test_report_shows_zero_when_no_authorizations(): void
    {
        $response = $this->actingAs($this->quimico)
            ->get('/admin/reportes/descuentos-anulaciones');

        $response->assertOk();
        $response->assertSee('0');
    }
}
