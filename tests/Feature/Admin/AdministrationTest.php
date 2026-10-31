<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Convention;
use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T10 - Administración: convenios (RF-39, RF-40), historial (RF-37),
 * detalle de venta (RF-38), documentos (RF-26) y permisos por rol.
 */
final class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Convenios (RF-39, RF-40)
    // ------------------------------------------------------------------

    public function test_admin_creates_a_convention(): void
    {
        $this->signIn('admin');

        $response = $this->post(route('admin.conventions.store'), [
            'name' => 'Banmédica',
            'percent' => 15,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);

        $response->assertRedirect(route('admin.conventions.index'));
        $response->assertSessionHasNoErrors();

        $convention = Convention::query()->where('name', 'Banmédica')->firstOrFail();

        $this->assertSame(15.0, $convention->percent);
        $this->assertTrue($convention->active);
        $this->assertSame('2026-01-01', $convention->valid_from->toDateString());
        $this->assertSame('2026-12-31', $convention->valid_until->toDateString());
    }

    public function test_a_convention_requires_name_percent_and_a_coherent_validity(): void
    {
        $this->signIn('admin');

        $response = $this->post(route('admin.conventions.store'), [
            'name' => '',
            'percent' => 150,
            'valid_from' => '2026-12-31',
            'valid_until' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors(['name', 'percent', 'valid_until']);
        $this->assertSame(0, Convention::query()->count());
    }

    public function test_admin_updates_a_convention(): void
    {
        $this->signIn('admin');
        $convention = Convention::factory()->create(['percent' => 10]);

        $this->patch(route('admin.conventions.update', $convention), [
            'name' => $convention->name,
            'percent' => 20,
            'valid_from' => $convention->valid_from->toDateString(),
            'valid_until' => $convention->valid_until->toDateString(),
            'active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(20.0, $convention->fresh()->percent);
    }

    public function test_admin_deactivates_a_convention(): void
    {
        $this->signIn('admin');
        $convention = Convention::factory()->create();

        $this->delete(route('admin.conventions.destroy', $convention))
            ->assertSessionHasNoErrors();

        $this->assertFalse($convention->fresh()->active);
        $this->assertNotNull(Convention::query()->find($convention->id));
    }

    public function test_an_active_convention_applies_its_percentage_and_is_recorded(): void
    {
        $this->signIn('tecnico');

        $convention = Convention::factory()->create([
            'name' => 'Banmédica',
            'percent' => 10,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
        ]);

        $this->addToCart();

        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'convention_id' => $convention->id,
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();

        $this->assertSame($convention->id, $sale->convention_id);
        $this->assertSame('Banmédica', $sale->convention_name);
        $this->assertSame(10.0, $sale->convention_percent);
        $this->assertSame(100, $sale->global_discount); // 10% de $1.000
        $this->assertSame(900, $sale->total);
        $this->assertSame('debit', $sale->payment_method->value);

        $this->get(route('pos.sales.receipt', $sale))
            ->assertOk()
            ->assertSee('Banmédica');
    }

    public function test_an_inactive_convention_is_rejected_at_checkout(): void
    {
        $this->signIn('tecnico');

        $convention = Convention::factory()->create(['active' => false]);
        $this->addToCart();

        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'convention_id' => $convention->id,
        ])->assertSessionHasErrors('convention_id');

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_a_convention_out_of_its_validity_is_rejected_at_checkout(): void
    {
        $this->signIn('tecnico');

        $convention = Convention::factory()->create([
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $this->addToCart();

        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'convention_id' => $convention->id,
        ])->assertSessionHasErrors('convention_id');

        $this->assertSame(0, Sale::query()->count());
    }

    // ------------------------------------------------------------------
    // Historial y detalle de venta (RF-37, RF-38)
    // ------------------------------------------------------------------

    public function test_admin_sees_the_sales_history(): void
    {
        $this->signIn('admin');
        $first = $this->sell();
        $second = $this->sell();

        $response = $this->get(route('admin.sales.index'));

        $response->assertOk();
        $response->assertSee('Historial');
        $this->assertSame(2, $this->countSales($response->getContent()));
        $response->assertSee("data-sale-id=\"{$first->id}\"", false);
        $response->assertSee("data-sale-id=\"{$second->id}\"", false);
    }

    public function test_history_filters_by_payment_method(): void
    {
        $this->signIn('admin');
        $cash = $this->sell('cash');
        $this->sell('debit');

        $response = $this->get(route('admin.sales.index', ['payment_method' => 'cash']));

        $response->assertOk();
        $this->assertSame(1, $this->countSales($response->getContent()));
        $response->assertSee("data-sale-id=\"{$cash->id}\"", false);
    }

    public function test_history_filters_by_date_range(): void
    {
        $this->signIn('admin');
        $old = $this->sell();
        $old->update(['sold_at' => now()->subDays(10)]);
        $today = $this->sell();

        $response = $this->get(route('admin.sales.index', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertSame(1, $this->countSales($response->getContent()));
        $response->assertSee("data-sale-id=\"{$today->id}\"", false);
    }

    public function test_history_filters_by_cashier(): void
    {
        $this->signIn('admin');
        $cashier = User::factory()->create(['role' => 'tecnico']);
        $other = User::factory()->create(['role' => 'tecnico']);
        $mine = $this->sell('debit', $cashier->id);
        $this->sell('debit', $other->id);

        $response = $this->get(route('admin.sales.index', ['user_id' => $cashier->id]));

        $response->assertOk();
        $this->assertSame(1, $this->countSales($response->getContent()));
        $response->assertSee("data-sale-id=\"{$mine->id}\"", false);
    }

    public function test_history_filters_by_sale_status(): void
    {
        $this->signIn('admin');
        $voided = $this->sell();
        $voided->update(['status' => SaleStatus::voided]);
        $this->sell();

        $response = $this->get(route('admin.sales.index', ['status' => 'voided']));

        $response->assertOk();
        $this->assertSame(1, $this->countSales($response->getContent()));
        $response->assertSee("data-sale-id=\"{$voided->id}\"", false);
    }

    public function test_history_filters_by_document_status(): void
    {
        $this->signIn('admin');
        $pending = $this->sell();
        $pending->document()->update(['folio' => null, 'status' => DocumentStatus::pending]);
        $this->sell();

        $response = $this->get(route('admin.sales.index', ['document_status' => 'pending']));

        $response->assertOk();
        $this->assertSame(1, $this->countSales($response->getContent()));
        $response->assertSee("data-sale-id=\"{$pending->id}\"", false);
    }

    public function test_history_offers_a_retry_button_for_failed_documents(): void
    {
        $this->signIn('admin');
        $sale = $this->sell();
        $sale->document()->update(['folio' => null, 'status' => DocumentStatus::pending]);

        $this->get(route('admin.sales.index'))
            ->assertOk()
            ->assertSee(route('admin.documents.retry', $sale->document), false)
            ->assertSee('Reintentar');
    }

    public function test_admin_sees_the_sale_detail(): void
    {
        $this->signIn('admin');
        $sale = $this->sell();
        $sale->update(['status' => SaleStatus::voided]);

        $response = $this->get(route('admin.sales.show', $sale));

        $response->assertOk();
        $response->assertSee('Paracetamol 500 mg');
        $response->assertSee('$1.000');
        $response->assertSee('Anulada');
        $response->assertSee($sale->document->folio);
    }

    // ------------------------------------------------------------------
    // Documentos y reintento (RF-26)
    // ------------------------------------------------------------------

    public function test_admin_lists_documents_and_retries_a_pending_one(): void
    {
        $this->signIn('admin');
        $sale = $this->sell();
        $sale->document()->update([
            'folio' => null,
            'status' => DocumentStatus::pending,
            'issued_at' => null,
        ]);

        $this->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSee('Pendiente');

        $this->post(route('admin.documents.retry', $sale->document))
            ->assertSessionHasNoErrors();

        $document = $sale->document()->firstOrFail();

        $this->assertSame(DocumentStatus::issued, $document->status);
        $this->assertNotNull($document->folio);
        $this->assertNotNull($document->issued_at);
        $this->assertSame(1, Document::query()->whereKey($document->id)->count());
    }

    public function test_retry_reissues_a_rejected_document_with_its_folio(): void
    {
        $this->signIn('admin');
        $sale = $this->sell();
        $originalFolio = $sale->document->folio;
        $sale->document()->update(['status' => DocumentStatus::rejected, 'issued_at' => null]);

        $this->post(route('admin.documents.retry', $sale->document))
            ->assertSessionHasNoErrors();

        $document = $sale->document()->firstOrFail();

        $this->assertSame(DocumentStatus::issued, $document->status);
        $this->assertSame($originalFolio, $document->folio);
        $this->assertNotNull($document->issued_at);
    }

    public function test_retry_does_not_duplicate_folios(): void
    {
        $this->signIn('admin');
        $saleA = $this->sell();
        $saleB = $this->sell();

        $saleA->document()->update(['status' => DocumentStatus::rejected]);
        $saleB->document()->update(['folio' => null, 'status' => DocumentStatus::pending]);

        $this->post(route('admin.documents.retry', $saleA->document));
        $this->post(route('admin.documents.retry', $saleB->document));

        $folios = Document::query()
            ->where('type', DocumentType::receipt)
            ->whereNotNull('folio')
            ->pluck('folio');

        $this->assertSame(2, $folios->count());
        $this->assertSame(2, $folios->unique()->count());
    }

    public function test_an_issued_document_cannot_be_retried(): void
    {
        $this->signIn('admin');
        $sale = $this->sell();
        $document = $sale->document;
        $this->assertSame(DocumentStatus::issued, $document->status);

        $this->post(route('admin.documents.retry', $document))
            ->assertSessionHasErrors();

        $this->assertSame(DocumentStatus::issued, $document->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Permisos por rol (RF-37 a RF-40; Técnico sin anulación ni admin)
    // ------------------------------------------------------------------

    public function test_tecnico_cannot_access_the_admin_panel(): void
    {
        $this->signIn('tecnico');

        $this->get(route('admin.sales.index'))->assertForbidden();
        $this->get(route('admin.conventions.index'))->assertForbidden();
        $this->get(route('admin.documents.index'))->assertForbidden();
        $this->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_tecnico_cannot_void_a_sale(): void
    {
        $tecnico = $this->signIn('tecnico');
        $sale = $this->sell('debit', $tecnico->id);

        $this->post(route('pos.sales.void', $sale), [
            'reason' => 'Intento del cajero',
        ])->assertForbidden();

        $this->assertSame(SaleStatus::confirmed, $sale->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_quimico_can_void_and_read_conventions_but_cannot_manage_them(): void
    {
        $this->signIn('quimico');
        $sale = $this->sell();

        $this->post(route('pos.sales.void', $sale), [
            'reason' => 'Error de cobro',
        ])->assertSessionHasNoErrors();

        $this->assertSame(SaleStatus::voided, $sale->fresh()->status);

        $this->get(route('admin.conventions.index'))
            ->assertOk()
            ->assertSee('Convenios');

        $this->get(route('admin.conventions.create'))->assertForbidden();

        $this->post(route('admin.conventions.store'), [
            'name' => 'No permitido',
            'percent' => 5,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ])->assertForbidden();

        $this->assertSame(0, Convention::query()->count());
    }

    public function test_quimico_and_admin_read_the_audit_log_and_tecnico_cannot(): void
    {
        $this->signIn('quimico');
        $sale = $this->sell();
        $this->post(route('pos.sales.void', $sale), ['reason' => 'Error de cobro']);

        $this->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('sale.void')
            ->assertSee('Error de cobro');

        $this->signIn('admin');
        $this->get(route('admin.audit.index'))->assertOk();

        $this->signIn('tecnico');
        $this->get(route('admin.audit.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private function signIn(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);

        return $user;
    }

    private function addToCart(int $price = 1000, int $stock = 10): Product
    {
        $product = Product::factory()->create(['stock' => $stock, 'price' => $price]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        return $product;
    }

    private function sell(string $method = 'debit', ?int $userId = null): Sale
    {
        $this->addToCart();

        $payload = ['payment_method' => $method];

        if ($method === 'cash') {
            $payload['received_amount'] = 5000;
        }

        $this->post('/pos/checkout', $payload)->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();

        if ($userId !== null) {
            $sale->update(['user_id' => $userId]);
        }

        return $sale;
    }

    private function countSales(string $html): int
    {
        return substr_count($html, 'data-sale-id="');
    }
}
