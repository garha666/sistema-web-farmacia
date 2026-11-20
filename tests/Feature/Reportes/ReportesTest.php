<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Reportes\CsvExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $response->assertSee('1990');
    }

    /** RF-92: sin ventas en el rango, el reporte muestra ceros. */
    public function test_report_shows_zeros_when_no_sales(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01');

        $response->assertOk();
        $response->assertSee('0');
    }

    /** RF-93: el CSV trae todas las ventas del rango, no sólo la primera página. */
    public function test_csv_contains_every_sale_of_the_range(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createSale('2026-11-01 10:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00', 1000 + $i, 'cash');
        }

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01&export=1');

        $lines = array_values(array_filter(explode("\n", $response->getContent())));

        $this->assertCount(26, $lines);
    }

    /** RF-93, RNF-38: encabezados pedidos por la spec y total numérico sin formato. */
    public function test_csv_has_spec_columns_and_plain_numeric_total(): void
    {
        $this->createSale('2026-11-01 10:00:00', 1990, 'cash');

        $content = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01&export=1')
            ->getContent();

        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", $content))));

        $this->assertSame(['Fecha', 'Cajero', 'Medio de pago', 'Estado', 'Total'], $rows[0]);
        $this->assertSame('1990', $rows[1][4]);
        $this->assertSame('Efectivo', $rows[1][2]);
    }

    /** RF-93 / D-24: también se exporta pidiendo text/csv. */
    public function test_csv_is_served_when_client_accepts_text_csv(): void
    {
        $this->createSale('2026-11-01 10:00:00', 1990, 'cash');

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01', ['Accept' => 'text/csv']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    /** RF-93: la exportación queda en la bitácora con el usuario que la pidió. */
    public function test_export_is_recorded_in_audit_log(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-02&export=1')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'report.exported',
        ]);
    }

    /** Ver el reporte en pantalla no es exportar: no deja registro. */
    public function test_viewing_the_report_is_not_audited(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01')
            ->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'report.exported']);
    }

    /** RF-92: las ventas anuladas no suman al total vendido ni a las transacciones. */
    public function test_voided_sales_are_not_counted_in_totals(): void
    {
        $this->createSale('2026-11-01 10:00:00', 1990, 'cash');
        $this->createSale('2026-11-01 11:00:00', 5000, 'cash', 'voided');

        $response = $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01');

        $response->assertOk();
        $response->assertViewHas('totalSales', 1);
        $response->assertViewHas('totalAmount', 1990);
        $response->assertViewHas('byPaymentMethod', ['cash' => ['count' => 1, 'total' => 1990]]);
    }

    /** RF-92: el desglose por medio de pago se muestra con su etiqueta en español. */
    public function test_payment_breakdown_shows_spanish_labels(): void
    {
        $this->createSale('2026-11-01 10:00:00', 1990, 'transfer');

        $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01')
            ->assertSee('Transferencia')
            ->assertDontSeeHtml('<td class="px-4 py-3">transfer</td>');
    }

    /** RNF-36: el límite del día se interpreta en la zona horaria del negocio. */
    public function test_range_includes_the_whole_last_day(): void
    {
        $this->createSale('2026-11-01 23:59:30', 1990, 'cash');

        $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-01&to=2026-11-01')
            ->assertViewHas('totalSales', 1);
    }

    /** Caso límite: sin fechas, el reporte abre con el día de hoy en vez de fallar. */
    public function test_report_defaults_to_today_without_dates(): void
    {
        $today = now(config('pos.timezone'))->format('Y-m-d');

        $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas')
            ->assertOk()
            ->assertViewHas('from', $today)
            ->assertViewHas('to', $today);
    }

    /** Caso límite: desde posterior a hasta es un error de validación. */
    public function test_from_after_to_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-05&to=2026-11-01')
            ->assertSessionHasErrors('to');
    }

    /**
     * T40 - RF-97, RNF-39: cada reporte exportable con su rol, su tipo y su rango.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: ?string, 4: ?string}>
     */
    public static function exportableReports(): array
    {
        return [
            'ventas' => ['admin', '/admin/reportes/ventas?from=2026-11-01&to=2026-11-02&export=1', 'ventas', '2026-11-01', '2026-11-02'],
            'arqueo' => ['tecnico', '/admin/reportes/arqueo?export=1', 'arqueo', null, null],
            'descuentos' => ['quimico', '/admin/reportes/descuentos-anulaciones?from=2026-11-01&to=2026-11-02&export=1', 'descuentos-anulaciones', '2026-11-01', '2026-11-02'],
            'inventario' => ['bodeguero', '/admin/reportes/inventario?from=2026-11-01&to=2026-11-02&export=1', 'inventario', '2026-11-01', '2026-11-02'],
        ];
    }

    /** RF-97, RNF-39: toda exportación deja usuario, tipo, rango y marca de tiempo. */
    #[DataProvider('exportableReports')]
    public function test_every_export_is_audited_with_user_type_and_range(string $role, string $url, string $type, ?string $from, ?string $to): void
    {
        $user = User::factory()->{$role}()->create();

        $response = $this->actingAs($user)->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $log = AuditLog::query()->where('action', AuditLog::REPORT_EXPORTED)->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->created_at);
        $expected = $from === null ? "Reporte {$type}: sin rango" : "Reporte {$type}: {$from} a {$to}";
        $this->assertSame($expected, $log->reason);
    }

    /** RF-97: el archivo lleva el tipo de reporte y, cuando hay, el rango. */
    #[DataProvider('exportableReports')]
    public function test_the_file_name_carries_the_report_type_and_range(string $role, string $url, string $type, ?string $from, ?string $to): void
    {
        $response = $this->actingAs(User::factory()->{$role}()->create())->get($url);

        $name = $from === null ? "reporte-{$type}.csv" : "reporte-{$type}-{$from}_a_{$to}.csv";
        $this->assertStringContainsString('filename="'.$name.'"', $response->headers->get('Content-Disposition'));
    }

    /** Caso límite 4: exportar un reporte vacío entrega sólo los encabezados y queda auditado. */
    #[DataProvider('exportableReports')]
    public function test_exporting_an_empty_report_returns_only_headers(string $role, string $url): void
    {
        $response = $this->actingAs(User::factory()->{$role}()->create())->get($url);

        $response->assertOk();
        $rows = $this->csvRows($response->getContent());
        $this->assertCount(1, $rows);
        $this->assertGreaterThan(3, count($rows[0]));
        $this->assertSame(1, AuditLog::query()->where('action', AuditLog::REPORT_EXPORTED)->count());
    }

    /** RNF-37 / RF-97: un rol sin permiso de lectura no exporta ni deja registro. */
    public function test_a_role_without_read_access_cannot_export(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())->get('/admin/reportes/ventas?export=1')->assertForbidden();
        $this->actingAs(User::factory()->bodeguero()->create())->get('/admin/reportes/arqueo?export=1')->assertForbidden();
        $this->actingAs(User::factory()->tecnico()->create())->get('/admin/reportes/descuentos-anulaciones?export=1')->assertForbidden();
        $this->actingAs(User::factory()->quimico()->create())->get('/admin/reportes/inventario?export=1')->assertForbidden();

        $this->assertSame(0, AuditLog::query()->where('action', AuditLog::REPORT_EXPORTED)->count());
    }

    /** Caso límite 5: un rol no reconocido no accede a ningún reporte. */
    public function test_an_unknown_role_cannot_open_or_export_any_report(): void
    {
        $user = User::factory()->create(['role' => 'invitado']);

        foreach (['ventas', 'arqueo', 'descuentos-anulaciones', 'inventario'] as $report) {
            $this->actingAs($user)->get("/admin/reportes/{$report}")->assertForbidden();
            $this->actingAs($user)->get("/admin/reportes/{$report}?export=1")->assertForbidden();
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    /** Caso límite 2: un rango invertido se rechaza al exportar, sin archivo ni auditoría. */
    public function test_an_inverted_range_is_rejected_without_exporting(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reportes/ventas?from=2026-11-05&to=2026-11-01&export=1')
            ->assertSessionHasErrors('to');
        $this->actingAs(User::factory()->bodeguero()->create())
            ->get('/admin/reportes/inventario?from=2026-11-05&to=2026-11-01&export=1')
            ->assertSessionHasErrors('to');
        $this->actingAs($this->quimico)
            ->get('/admin/reportes/descuentos-anulaciones?from=2026-11-05&to=2026-11-01&export=1')
            ->assertSessionHasErrors('to');

        $this->assertSame(0, AuditLog::query()->where('action', AuditLog::REPORT_EXPORTED)->count());
    }

    /** Caso límite 7: dos exportaciones del mismo reporte son dos archivos y dos registros. */
    public function test_two_exports_of_the_same_report_are_both_audited(): void
    {
        $this->createSale('2026-11-01 10:00:00', 1990, 'cash');
        $url = '/admin/reportes/ventas?from=2026-11-01&to=2026-11-01&export=1';

        $first = $this->actingAs($this->admin)->get($url)->getContent();
        $second = $this->actingAs($this->admin)->get($url)->getContent();

        $this->assertSame($first, $second);
        $this->assertSame(2, AuditLog::query()->where('action', AuditLog::REPORT_EXPORTED)->count());
    }

    /** RF-98, RNF-34: ver o exportar no toca ventas, stock, turnos ni productos; sólo suma auditoría al exportar. */
    #[DataProvider('exportableReports')]
    public function test_reports_never_change_transactional_data(string $role, string $url): void
    {
        $user = User::factory()->{$role}()->create();
        $this->createSale('2026-11-01 10:00:00', 1990, 'cash');
        $product = Product::factory()->create(['stock' => 2, 'min_stock' => 5]);
        CashRegister::factory()->create(['user_id' => $user->id, 'opened_at' => now(), 'closed_at' => null]);
        $snapshot = fn (): array => [
            DB::table('sales')->get()->toArray(),
            DB::table('sale_lines')->get()->toArray(),
            DB::table('stock_movements')->get()->toArray(),
            DB::table('products')->get()->toArray(),
            DB::table('cash_registers')->get()->toArray(),
        ];
        $before = $snapshot();

        $this->actingAs($user)->get(str_replace('&export=1', '', str_replace('?export=1', '', $url)))->assertOk();
        $this->assertSame(0, AuditLog::query()->where('action', AuditLog::REPORT_EXPORTED)->count());

        $this->actingAs($user)->get($url)->assertOk();

        $this->assertEquals($before, $snapshot());
        $this->assertSame(1, AuditLog::query()->where('action', AuditLog::REPORT_EXPORTED)->count());
        $this->assertSame(2, $product->fresh()->stock);
    }

    /** RF-97: el CSV de arqueo trae las columnas del reporte; el técnico exporta sólo su turno. */
    public function test_arqueo_csv_has_the_report_columns_and_only_own_registers(): void
    {
        $tecnico = User::factory()->tecnico()->create(['name' => 'Cajera Uno']);
        $other = User::factory()->tecnico()->create(['name' => 'Cajero Ajeno']);
        CashRegister::factory()->create([
            'user_id' => $tecnico->id,
            'opening_amount' => 10000,
            'opened_at' => '2026-11-01 09:00:00',
            'closed_at' => '2026-11-01 18:00:00',
            'expected_cash' => 11990,
            'declared_cash' => 12000,
            'difference' => 10,
        ]);
        CashRegister::factory()->create(['user_id' => $other->id, 'opening_amount' => 5000, 'opened_at' => '2026-11-01 09:00:00', 'closed_at' => null]);

        $rows = $this->csvRows($this->actingAs($tecnico)->get('/admin/reportes/arqueo?export=1')->getContent());

        $this->assertSame(['Turno', 'Cajero', 'Apertura', 'Cierre', 'Estado', 'Monto inicial', 'Ventas', 'Total vendido', 'Ventas en efectivo', 'Efectivo esperado', 'Efectivo declarado', 'Diferencia'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('Cajera Uno', $rows[1][1]);
        $this->assertSame('01-11-2026 09:00', $rows[1][2]);
        $this->assertSame('cerrada', $rows[1][4]);
        $this->assertSame('10000', $rows[1][5]);
        $this->assertSame('11990', $rows[1][9]);
        $this->assertSame('12000', $rows[1][10]);
        $this->assertSame('10', $rows[1][11]);
    }

    /** Caso límite 6: un turno abierto se exporta como «abierta», sin declarado ni diferencia. */
    public function test_arqueo_csv_marks_an_open_register(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        CashRegister::factory()->create(['user_id' => $tecnico->id, 'opening_amount' => 10000, 'opened_at' => '2026-11-01 09:00:00', 'closed_at' => null]);

        $rows = $this->csvRows($this->actingAs($tecnico)->get('/admin/reportes/arqueo?export=1')->getContent());

        $this->assertSame('abierta', $rows[1][4]);
        $this->assertSame('', $rows[1][3]);
        $this->assertSame('', $rows[1][10]);
        $this->assertSame('', $rows[1][11]);
    }

    /** RF-97, RF-95: el CSV de descuentos y anulaciones trae autorizador, acción, monto y motivo del rango. */
    public function test_discounts_csv_has_the_report_columns_and_respects_the_range(): void
    {
        $this->logAt([
            'user_id' => $this->quimico->id,
            'action' => 'sale.discount_authorized',
            'reason' => 'Descuento global 15%',
            'discount_type' => 'percent',
            'discount_value' => 150,
        ], '2026-11-01 10:00:00');
        $this->logAt([
            'user_id' => $this->quimico->id,
            'action' => 'sale.discount_authorized',
            'reason' => 'Fuera de rango',
            'discount_type' => 'percent',
            'discount_value' => 50,
        ], '2026-10-01 10:00:00');

        $content = $this->actingAs($this->quimico)
            ->get('/admin/reportes/descuentos-anulaciones?from=2026-11-01&to=2026-11-02&export=1')
            ->getContent();
        $rows = $this->csvRows($content);

        $this->assertSame(['Fecha', 'Autorizador', 'Acción', 'Monto', 'Motivo'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('01-11-2026 10:00', $rows[1][0]);
        $this->assertSame($this->quimico->name, $rows[1][1]);
        $this->assertSame('150', $rows[1][3]);
        $this->assertSame('Descuento global 15%', $rows[1][4]);
    }

    /** RF-97, RF-96: el CSV de inventario junta las tres secciones, con todos los movimientos del rango. */
    public function test_inventory_csv_lists_all_sections_without_pagination(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $low = Product::factory()->create(['name' => 'Producto Bajo', 'stock' => 2, 'min_stock' => 5, 'price' => 1000]);
        $none = Product::factory()->create(['name' => 'Producto Agotado', 'stock' => 0, 'min_stock' => 5, 'price' => 2000]);
        for ($i = 0; $i < 55; $i++) {
            $movement = new StockMovement(['product_id' => $low->id, 'user_id' => $bodeguero->id, 'type' => 'in', 'quantity' => 1, 'resulting_stock' => 2, 'note' => 'Lote '.$i]);
            $movement->forceFill(['created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00'])->save();
        }

        $rows = $this->csvRows($this->actingAs($bodeguero)
            ->get('/admin/reportes/inventario?from=2026-11-01&to=2026-11-01&export=1')
            ->getContent());

        $this->assertSame(['Sección', 'Producto', 'Existencia', 'Mínimo', 'Precio', 'Fecha', 'Tipo', 'Cantidad', 'Existencia resultante', 'Motivo', 'Autor'], $rows[0]);
        $this->assertCount(1 + 2 + 55, $rows);
        $this->assertSame(['Bajo el mínimo', 'Producto Bajo', '2', '5', '1000'], array_slice($rows[1], 0, 5));
        $this->assertSame(['Sin stock', 'Producto Agotado', '0', '5', '2000'], array_slice($rows[2], 0, 5));
        $this->assertSame('Movimientos', $rows[3][0]);
        $this->assertSame($bodeguero->name, $rows[3][10]);
    }

    /** @return array<string, array{string}> */
    public static function formulaPrefixes(): array
    {
        return ['igual' => ['='], 'más' => ['+'], 'menos' => ['-'], 'arroba' => ['@']];
    }

    /** RF-97: un motivo que empieza con =, +, - o @ sale con comilla simple al inicio (inyección de fórmulas). */
    #[DataProvider('formulaPrefixes')]
    public function test_csv_reason_starting_with_formula_character_is_prefixed_with_a_quote(string $prefix): void
    {
        $this->logAt([
            'user_id' => $this->quimico->id,
            'action' => 'sale.discount_authorized',
            'reason' => $prefix.'HYPERLINK("http://malo.example","clic")',
            'discount_type' => 'percent',
            'discount_value' => 150,
        ], '2026-11-01 10:00:00');

        $rows = $this->csvRows($this->actingAs($this->quimico)
            ->get('/admin/reportes/descuentos-anulaciones?from=2026-11-01&to=2026-11-02&export=1')
            ->getContent());

        $this->assertSame("'".$prefix.'HYPERLINK("http://malo.example","clic")', $rows[1][4]);
    }

    /** RF-97: un nombre de producto que empieza con =, +, - o @ sale con comilla simple al inicio. */
    #[DataProvider('formulaPrefixes')]
    public function test_csv_product_name_starting_with_formula_character_is_prefixed_with_a_quote(string $prefix): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        Product::factory()->create(['name' => $prefix.'CMD|calc', 'stock' => 0, 'min_stock' => 5, 'price' => 2000]);

        $rows = $this->csvRows($this->actingAs($bodeguero)
            ->get('/admin/reportes/inventario?from=2026-11-01&to=2026-11-01&export=1')
            ->getContent());

        $this->assertSame("'".$prefix.'CMD|calc', $rows[1][1]);
        $this->assertSame('2000', $rows[1][4]);
    }

    /** RF-97: sólo se neutralizan los textos; los números negativos y los textos normales no cambian. */
    public function test_csv_does_not_alter_numbers_or_regular_text(): void
    {
        $csv = (new CsvExporter)->export(['A', 'B', 'C', 'D'], [[-5, -1.5, 'Paracetamol', null]]);

        $this->assertSame("A,B,C,D\n-5,-1.5,Paracetamol,\n", $csv);
    }

    /** RF-97: cada pantalla de reporte ofrece su enlace de exportación. */
    #[DataProvider('exportableReports')]
    public function test_every_report_screen_offers_the_export_link(string $role, string $url): void
    {
        $screen = str_replace(['&export=1', '?export=1'], '', $url);

        $this->actingAs(User::factory()->{$role}()->create())
            ->get($screen)
            ->assertOk()
            ->assertSee('export=1', false)
            ->assertSee(__('pos.reportes.ventas.export'));
    }

    /** Ver una pantalla de reporte nunca se audita; sólo exportar. */
    public function test_viewing_any_report_is_not_audited(): void
    {
        foreach ([
            [$this->admin, '/admin/reportes/ventas'],
            [User::factory()->tecnico()->create(), '/admin/reportes/arqueo'],
            [$this->quimico, '/admin/reportes/descuentos-anulaciones'],
            [User::factory()->bodeguero()->create(), '/admin/reportes/inventario'],
        ] as [$user, $url]) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    /** RNF-39: la bitácora del administrador muestra la exportación con una etiqueta legible. */
    public function test_the_audit_screen_labels_the_export(): void
    {
        AuditLog::recordExport($this->admin, 'ventas', '2026-11-01', '2026-11-02');

        $this->actingAs($this->admin)
            ->get('/admin/audit')
            ->assertOk()
            ->assertSeeText(__('pos.panel.audit_action.report.exported'))
            ->assertDontSeeText('report.exported');
    }

    /** @param  array<string, mixed>  $attributes */
    private function logAt(array $attributes, string $at): void
    {
        $log = new AuditLog($attributes);
        $log->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    /** @return array<int, array<int, string|null>> */
    private function csvRows(string $content): array
    {
        return array_map('str_getcsv', array_values(array_filter(explode("\n", $content))));
    }

    private function createSale(string $soldAt, int $total, string $method, string $status = 'confirmed'): void
    {
        Sale::query()->create([
            'token' => Str::uuid(),
            'subtotal' => $total,
            'net' => (int) round($total * 100 / 119),
            'iva' => $total - (int) round($total * 100 / 119),
            'total' => $total,
            'payment_method' => $method,
            'status' => $status,
            'sold_at' => $soldAt,
            'user_id' => $this->admin->id,
        ]);
    }
}
