<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T15 - RF-52 a RF-55 del panel del Químico.
 */
final class ChemistPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $chemist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chemist = User::factory()->quimico()->create(['name' => 'Supervisora Demo']);
        $this->actingAs($this->chemist);
    }

    public function test_pending_feed_counts_all_active_reviews_and_is_restricted_to_the_chemist(): void
    {
        $cashier = User::factory()->tecnico()->create();

        for ($i = 0; $i < 22; $i++) {
            \App\Models\PrescriptionReview::query()->forceCreate([
                'cashier_id' => $cashier->id,
                'checkout_token' => (string) Str::uuid(),
                'fingerprint' => str_repeat('a', 64),
                'expires_at' => $i === 21 ? now()->subMinute() : now()->addMinutes(5),
                'snapshot' => ['prescription' => [
                    'prescription_number' => 'RX-DEMO-'.$i,
                    'prescription_prescriber_name' => 'Profesional Demo',
                    'prescription_prescriber_rut' => '1-9',
                    'prescription_issued_at' => '2026-11-25',
                ], 'products' => []],
            ]);
        }

        $url = '/panel/quimico/recetas/pendientes';
        $response = $this->getJson($url)->assertOk()->assertJsonPath('count', 21);
        $this->assertStringContainsString('RX-DEMO-0<', $response->json('html'));
        $this->assertStringNotContainsString('RX-DEMO-21<', $response->json('html'));
        $this->actingAs($cashier)->getJson($url)->assertForbidden();
    }

    public function test_the_panel_shows_the_days_sales_and_payment_breakdown(): void
    {
        $this->saleOf(12000, 'cash');
        $this->saleOf(8000, 'debit');
        $this->saleOf(5000, 'credit', SaleStatus::confirmed, now()->subDay());

        $response = $this->get(route('dashboard.quimico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.quimico.sales_heading'));
        $response->assertSee('$20.000');
        $response->assertSee('$12.000');
        $response->assertSee('$8.000');
        $response->assertDontSee('$5.000');
        $response->assertSeeText(__('pos.payment_method.cash'));
        $response->assertSeeText(__('pos.payment_method.debit'));
    }

    public function test_the_header_precedes_the_queue_and_reflects_the_pin_status(): void
    {
        $this->get(route('dashboard.quimico'))->assertOk()->assertSeeTextInOrder([
            'Panel del Químico', 'Pendiente de configurar', 'Recetas pendientes de revisión',
        ]);

        $this->chemist->forceFill(['pharmacist_pin_hash' => bcrypt('12345678')])->save();
        $this->get(route('dashboard.quimico'))->assertSeeText('Configurado')
            ->assertDontSeeText('Pendiente de configurar');
    }

    /**
     * RF-53: autorizaciones otorgadas hoy por el Químico en sesión.
     */
    public function test_the_panel_lists_only_my_authorizations_from_today(): void
    {
        $global = $this->saleOf(10000, 'cash', globalDiscount: 900);
        $lineSale = $this->saleOf(2000, 'debit');
        $prescriptionSale = $this->saleOf(3000, 'cash');

        $line = SaleLine::query()->create([
            'sale_id' => $lineSale->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'Ibuprofeno 400 mg',
            'unit_price' => 2000,
            'quantity' => 1,
            'discount_amount' => 500,
            'subtotal' => 1500,
        ]);

        AuditLog::query()->create([
            'user_id' => $this->chemist->id,
            'action' => 'sale.discount_authorized',
            'auditable_type' => $global->getMorphClass(),
            'auditable_id' => $global->id,
            'reason' => 'Descuento global autorizado para cliente frecuente.',
        ]);
        AuditLog::query()->create([
            'user_id' => $this->chemist->id,
            'action' => 'sale.line_discount_authorized',
            'auditable_type' => $line->getMorphClass(),
            'auditable_id' => $line->id,
            'reason' => 'Descuento de línea autorizado.',
            'discount_type' => 'amount',
            'discount_value' => 500,
        ]);
        AuditLog::query()->create([
            'user_id' => $this->chemist->id,
            'action' => 'sale.prescription_reuse',
            'auditable_type' => $prescriptionSale->getMorphClass(),
            'auditable_id' => $prescriptionSale->id,
            'reason' => 'Receta REC-777 reutilizada con autorización.',
        ]);

        $other = AuditLog::query()->create([
            'user_id' => User::factory()->quimico()->create()->id,
            'action' => 'sale.discount_authorized',
            'auditable_type' => $global->getMorphClass(),
            'auditable_id' => $global->id,
            'reason' => 'Autorización de otro supervisor.',
        ]);

        $yesterday = AuditLog::query()->create([
            'user_id' => $this->chemist->id,
            'action' => 'sale.discount_authorized',
            'auditable_type' => $global->getMorphClass(),
            'auditable_id' => $global->id,
            'reason' => 'Autorización de ayer.',
        ]);
        $yesterday->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->get(route('dashboard.quimico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.quimico.authorizations_heading'));
        $response->assertSeeText(__('pos.panel.audit_action.sale.discount_authorized'));
        $response->assertSeeText(__('pos.panel.audit_action.sale.line_discount_authorized'));
        $response->assertSeeText(__('pos.panel.audit_action.sale.prescription_reuse'));
        $response->assertSeeText('#'.$global->id);
        $response->assertSeeText('#'.$lineSale->id);
        $response->assertSeeText('#'.$prescriptionSale->id);
        $response->assertSee('$900');
        $response->assertSee('$500');
        $response->assertSeeText('Descuento global autorizado para cliente frecuente.');
        $response->assertDontSeeText('Autorización de otro supervisor.');
        $response->assertDontSeeText('Autorización de ayer.');
    }

    /**
     * RF-54: anulaciones registradas hoy con su motivo y su autor.
     */
    public function test_the_panel_lists_the_days_voids_with_reason_and_author(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrador Demo']);
        $voided = $this->saleOf(4000, 'cash', SaleStatus::voided);

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'sale.void',
            'auditable_type' => $voided->getMorphClass(),
            'auditable_id' => $voided->id,
            'reason' => 'Cliente desistió de la compra.',
        ]);

        $oldSale = $this->saleOf(1000, 'cash', SaleStatus::voided);
        $old = AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'sale.void',
            'auditable_type' => $oldSale->getMorphClass(),
            'auditable_id' => $oldSale->id,
            'reason' => 'Anulación de ayer.',
        ]);
        $old->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->get(route('dashboard.quimico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.quimico.voids_heading'));
        $response->assertSeeText('#'.$voided->id);
        $response->assertSeeText('Cliente desistió de la compra.');
        $response->assertSeeText('Administrador Demo');
        $response->assertDontSeeText('Anulación de ayer.');
    }

    public function test_the_panel_omits_administrative_links_but_keeps_direct_read_access(): void
    {
        $response = $this->get(route('dashboard.quimico'));

        $response->assertOk();
        $response->assertDontSee(route('admin.conventions.index'), false);
        $response->assertDontSee(route('admin.audit.index'), false);
        $response->assertDontSeeText('Consultas de sólo lectura');
        $response->assertDontSeeText('Ver convenios');
        $response->assertDontSeeText('Ver auditoría');
        $response->assertDontSee(route('admin.conventions.create'), false);
        $this->get(route('admin.conventions.index'))->assertOk();
        $this->get(route('admin.audit.index'))->assertOk();
        $this->get(route('admin.conventions.create'))->assertForbidden();
        // RF-55: el panel no ofrece acciones de escritura sobre datos. Los
        // únicos formularios son los de cerrar sesión (spec 003, RF-81), que no
        // escriben nada del negocio.
        $html = (string) $response->getContent();
        $this->assertSame(
            substr_count($html, '<form'),
            substr_count($html, 'action="'.route('logout').'"'),
            'El panel del Químico sólo debe tener formularios de cerrar sesión.',
        );
    }

    public function test_the_panel_without_activity_shows_zeros_and_messages(): void
    {
        $response = $this->get(route('dashboard.quimico'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.quimico.no_sales'));
        $response->assertSeeText(__('pos.panel.quimico.authorizations_empty'));
        $response->assertSeeText(__('pos.panel.quimico.voids_empty'));
        $response->assertSee('$0');
    }

    private function saleOf(
        int $total,
        string $method = 'cash',
        SaleStatus $status = SaleStatus::confirmed,
        ?Carbon $soldAt = null,
        int $globalDiscount = 0,
    ): Sale {
        $net = (int) round($total * 100 / 119);

        return Sale::query()->create([
            'token' => (string) Str::uuid(),
            'subtotal' => $total,
            'global_discount' => $globalDiscount,
            'net' => $net,
            'iva' => $total - $net,
            'total' => $total,
            'payment_method' => $method,
            'status' => $status,
            'sold_at' => $soldAt ?? now(),
            'user_id' => $this->chemist->id,
        ]);
    }
}
