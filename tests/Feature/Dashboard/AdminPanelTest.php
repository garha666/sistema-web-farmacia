<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Convention;
use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Contract\DocumentEmitter;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\SaleStatus;
use App\Pos\Exception\EmissionFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T14 - RF-46 a RF-51 (resumen, documentos, inventario, auditoría, convenios y
 * cortes) y RF-77 (panel sin datos) en el panel del Administrador.
 */
final class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Administradora Uno']);
        $this->actingAs($this->admin);
    }

    public function test_the_panel_shows_the_days_sales_without_mixing_voided_amounts(): void
    {
        $this->saleOf(10000, 'cash');
        $this->saleOf(6000, 'debit');
        $this->saleOf(4000, 'cash', SaleStatus::voided);
        $this->saleOf(5000, 'debit', SaleStatus::confirmed, now()->subDay());

        $response = $this->get(route('dashboard.admin'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.admin.confirmed_count'));
        $response->assertSeeText(__('pos.panel.admin.voided_count'));
        $response->assertSee('$16.000');
        $response->assertSee('$4.000');
        $response->assertDontSee('$20.000');
        $response->assertDontSee('$5.000');
        $response->assertSeeText(__('pos.payment_method.cash'));
        $response->assertSeeText(__('pos.payment_method.debit'));
    }

    public function test_voids_registered_today_count_even_when_the_sale_is_from_yesterday(): void
    {
        // D-11: una venta de ayer anulada hoy no es «venta anulada del día»,
        // pero sí es una «anulación registrada hoy».
        $yesterday = $this->saleOf(4000, 'cash', SaleStatus::voided, now()->subDay());

        AuditLog::query()->create([
            'user_id' => $this->admin->id,
            'action' => 'sale.void',
            'auditable_type' => $yesterday->getMorphClass(),
            'auditable_id' => $yesterday->id,
            'reason' => 'Anulación de una venta de ayer.',
        ]);

        $content = $this->get(route('dashboard.admin'))->assertOk()->getContent();

        // «Ventas anuladas del día» no la cuenta: la venta es de ayer.
        $this->assertMatchesRegularExpression('/id="admin-voided-count"[^>]*>\s*0\s*</', (string) $content);
        // «Anulaciones registradas hoy» sí la cuenta.
        $this->assertMatchesRegularExpression('/id="admin-registered-voids"[^>]*>\s*1\s*</', (string) $content);

        // El panel del Químico lista la misma anulación (misma fuente, D-11).
        $this->actingAs(User::factory()->quimico()->create())
            ->get(route('dashboard.quimico'))
            ->assertOk()
            ->assertSeeText(__('pos.panel.quimico.voids_heading'))
            ->assertSeeText('Anulación de una venta de ayer.');
    }

    public function test_documents_pending_and_rejected_are_listed_with_retry_while_issued_are_not(): void
    {
        $pending = $this->documentOf($this->saleOf(1000), DocumentStatus::pending);
        $rejected = $this->documentOf($this->saleOf(2000), DocumentStatus::rejected);
        $issued = $this->documentOf($this->saleOf(3000), DocumentStatus::issued);

        $response = $this->get(route('dashboard.admin'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.admin.documents_heading'));
        $response->assertSee(route('admin.documents.retry', $pending), false);
        $response->assertSee(route('admin.documents.retry', $rejected), false);
        $response->assertDontSee(route('admin.documents.retry', $issued), false);
        $response->assertSeeText('#'.$pending->sale_id);
        $response->assertSeeText('#'.$rejected->sale_id);
        $response->assertSeeText($pending->type->label());
    }

    public function test_a_rejected_document_that_fails_again_is_reported_without_leaving_the_panel(): void
    {
        $this->failEmission();

        $document = $this->documentOf($this->saleOf(2000), DocumentStatus::rejected);

        $response = $this->from(route('dashboard.admin'))
            ->post(route('admin.documents.retry', $document));

        $response->assertRedirect(route('dashboard.admin'));
        $response->assertSessionHasErrors('document');
        $this->assertSame(DocumentStatus::rejected, $document->fresh()->status);

        $this->get(route('dashboard.admin'))
            ->assertOk()
            ->assertSeeText(__('pos.errors.emission_failed'));
    }

    public function test_inventory_alerts_count_products_below_the_minimum_and_out_of_stock(): void
    {
        Product::factory()->create(['stock' => 2, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['stock' => 0, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['stock' => 10, 'min_stock' => 5, 'active' => true]);
        Product::factory()->create(['stock' => 0, 'min_stock' => 5, 'active' => false]);

        $response = $this->get(route('dashboard.admin'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.admin.below_minimum'));
        $response->assertSeeText(__('pos.panel.admin.out_of_stock'));
        $response->assertSeeText('2');
        $response->assertSeeText('1');
    }

    public function test_recent_audit_movements_show_author_action_sale_and_reason(): void
    {
        $sale = $this->saleOf(4000, 'cash');

        AuditLog::query()->create([
            'user_id' => $this->admin->id,
            'action' => 'sale.void',
            'auditable_type' => $sale->getMorphClass(),
            'auditable_id' => $sale->id,
            'reason' => 'Cliente desistió de la compra.',
        ]);

        $response = $this->get(route('dashboard.admin'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.admin.audit_heading'));
        $response->assertSeeText($this->admin->name);
        $response->assertSeeText(__('pos.panel.audit_action.sale.void'));
        $response->assertSeeText('#'.$sale->id);
        $response->assertSeeText('Cliente desistió de la compra.');
    }

    public function test_conventions_expiring_soon_and_expired_are_listed(): void
    {
        $expiring = Convention::factory()->create([
            'name' => 'Convenio Próximo',
            'valid_until' => now()->addDays(10)->toDateString(),
        ]);
        Convention::factory()->expired()->create(['name' => 'Convenio Vencido']);
        Convention::factory()->create([
            'name' => 'Convenio Lejano',
            'valid_until' => now()->addDays(90)->toDateString(),
        ]);
        Convention::factory()->inactive()->create([
            'name' => 'Convenio Inactivo Lejano',
            'valid_until' => now()->addDays(90)->toDateString(),
        ]);

        $response = $this->get(route('dashboard.admin'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.admin.conventions_expiring'));
        $response->assertSeeText(__('pos.panel.admin.conventions_expired'));
        $response->assertSeeText('Convenio Próximo');
        $response->assertSeeText('Convenio Vencido');
        $response->assertDontSeeText('Convenio Lejano');
        $response->assertDontSeeText('Convenio Inactivo Lejano');
        $response->assertSee(route('admin.conventions.toggle', $expiring), false);
    }

    public function test_the_admin_deactivates_a_convention_from_the_panel(): void
    {
        $convention = Convention::factory()->create(['name' => 'Convenio Activo']);

        $response = $this->from(route('dashboard.admin'))
            ->patch(route('admin.conventions.toggle', $convention));

        $response->assertRedirect(route('dashboard.admin'));
        $response->assertSessionHasNoErrors();
        $this->assertFalse($convention->fresh()->active);
    }

    public function test_an_expired_convention_cannot_be_activated(): void
    {
        $convention = Convention::factory()->expired()->inactive()->create(['name' => 'Convenio Vencido']);

        $response = $this->from(route('dashboard.admin'))
            ->patch(route('admin.conventions.toggle', $convention));

        $response->assertRedirect(route('dashboard.admin'));
        $response->assertSessionHasErrors('convention');
        $this->assertFalse($convention->fresh()->active);

        $this->get(route('dashboard.admin'))
            ->assertOk()
            ->assertSeeText(__('pos.panel.conventions.error.expired'));
    }

    public function test_cash_cuts_of_the_day_show_expected_declared_and_difference(): void
    {
        $cashier = User::factory()->tecnico()->create(['name' => 'Cajera Turno']);

        CashRegister::factory()->closed(46000)->create([
            'user_id' => $cashier->id,
            'opened_at' => now()->setTime(9, 0),
            'closed_at' => now()->setTime(14, 0),
        ]);
        CashRegister::factory()->create([
            'user_id' => $cashier->id,
            'opening_amount' => 30000,
            'opened_at' => now()->setTime(15, 0),
        ]);
        CashRegister::factory()->closed(10000)->create([
            'user_id' => $cashier->id,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->get(route('dashboard.admin'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.admin.cash_cuts_heading'));
        $response->assertSeeText('Cajera Turno');
        $response->assertSee('$50.000');
        $response->assertSee('$46.000');
        $response->assertSee('-$4.000');
        $response->assertSeeText(__('pos.panel.admin.open_register'));
        $response->assertSeeText(__('pos.panel.admin.closed_register'));
        $response->assertDontSee('$10.000');
    }

    public function test_the_panel_without_activity_shows_zeros_and_an_explanation(): void
    {
        $response = $this->get(route('dashboard.admin'));

        $response->assertOk();
        $response->assertSeeText(__('pos.panel.admin.no_sales'));
        $response->assertSeeText(__('pos.panel.admin.documents_empty'));
        $response->assertSeeText(__('pos.panel.admin.audit_empty'));
        $response->assertSeeText(__('pos.panel.admin.cash_cuts_empty'));
        $response->assertSee('$0');
        $response->assertSeeText('0');
    }

    private function saleOf(
        int $total,
        string $method = 'debit',
        SaleStatus $status = SaleStatus::confirmed,
        ?Carbon $soldAt = null,
    ): Sale {
        $net = (int) round($total * 100 / 119);

        return Sale::query()->create([
            'token' => (string) Str::uuid(),
            'subtotal' => $total,
            'net' => $net,
            'iva' => $total - $net,
            'total' => $total,
            'payment_method' => $method,
            'status' => $status,
            'sold_at' => $soldAt ?? now(),
            'user_id' => $this->admin->id,
        ]);
    }

    private function documentOf(
        Sale $sale,
        DocumentStatus $status,
        DocumentType $type = DocumentType::receipt,
    ): Document {
        return Document::query()->create([
            'sale_id' => $sale->id,
            'type' => $type,
            'status' => $status,
            'folio' => $status === DocumentStatus::issued ? 100 : null,
            'issued_at' => $status === DocumentStatus::issued ? now() : null,
        ]);
    }

    private function failEmission(): void
    {
        $this->app->instance(DocumentEmitter::class, new class implements DocumentEmitter
        {
            public function emit(DocumentType $type): void
            {
                throw new EmissionFailed('Falla simulada de emisión.');
            }
        });

        $this->forgetResolvedControllers();
    }

    /**
     * Laravel cachea la instancia del controlador dentro de la ruta; en un test
     * con varias peticiones hay que soltarla para que tome el emisor vigente.
     */
    private function forgetResolvedControllers(): void
    {
        foreach ($this->app->make('router')->getRoutes() as $route) {
            $route->flushController();
        }
    }
}
