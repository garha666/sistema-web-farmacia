<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Document;
use App\Models\InvoiceDetail;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Pos\Contract\DocumentEmitter;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\SaleStatus;
use App\Pos\Exception\EmissionFailed;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * S9 - RF-24: emisión simulada fallida, comprobante provisorio y reintento.
 *
 * La venta se confirma una sola vez (stock, movimientos y un documento
 * original pendiente); la emisión simulada ocurre después y su fallo no
 * deshace la venta. Sólo un fallo del simulador deja el documento pendiente:
 * un error de base de datos se propaga.
 */
final class DocumentEmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'tecnico']);
        $this->actingAs($this->cashier);
        $this->openCashRegister($this->cashier);
    }

    public function test_a_successful_checkout_issues_one_document_with_one_folio(): void
    {
        $product = $this->addToCart(stock: 10);

        $response = $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();
        $document = Document::query()->firstOrFail();

        $response->assertRedirect(URL::signedRoute('pos.sales.receipt', $sale));
        $this->assertSame(SaleStatus::confirmed, $sale->status);
        $this->assertSame(DocumentStatus::issued, $document->status);
        $this->assertSame(1, $document->folio);
        $this->assertNotNull($document->issued_at);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(9, $product->fresh()->stock);

        $this->get(URL::signedRoute('pos.sales.receipt', $sale))
            ->assertOk()
            ->assertSee('Folio 1')
            ->assertDontSee('Provisorio');
    }

    public function test_a_simulated_emission_failure_keeps_the_sale_pending_and_shows_a_provisional_receipt(): void
    {
        $this->failEmission();
        $product = $this->addToCart(stock: 10);

        $response = $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();
        $document = Document::query()->firstOrFail();

        $response->assertRedirect(URL::signedRoute('pos.sales.receipt', $sale));

        // La venta quedó confirmada una sola vez.
        $this->assertSame(SaleStatus::confirmed, $sale->status);
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(-1, StockMovement::query()->firstOrFail()->quantity);
        $this->assertSame(1, Sale::query()->count());

        // Un único documento original, pendiente y sin folio inventado.
        $this->assertSame(1, Document::query()->count());
        $this->assertSame($sale->id, $document->sale_id);
        $this->assertSame(DocumentType::receipt, $document->type);
        $this->assertSame(DocumentStatus::pending, $document->status);
        $this->assertNull($document->folio);
        $this->assertNull($document->issued_at);

        // El carrito se consumió: el comprobante se muestra sin volver al cobro.
        $this->get('/pos')->assertSee('Carrito vacío');

        $receipt = $this->get(URL::signedRoute('pos.sales.receipt', $sale));

        $receipt->assertOk();
        $receipt->assertSee('Provisorio');
        $receipt->assertSee('Documento simulado');
        $receipt->assertSee('sin validez tributaria');
        $receipt->assertDontSee('Folio 1');
        $receipt->assertDontSee('Folio pendiente', false);
    }

    public function test_retrying_a_failed_emission_issues_the_same_document_without_duplicates(): void
    {
        $this->failEmission();
        $product = $this->addToCart(stock: 10);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();
        $document = Document::query()->firstOrFail();

        $this->restoreSimulatedEmission();
        $this->actingAs($this->admin());

        $response = $this->post(route('admin.documents.retry', $document));

        $response->assertSessionHasNoErrors();

        $document = $document->fresh();

        $this->assertSame(DocumentStatus::issued, $document->status);
        $this->assertSame(1, $document->folio);
        $this->assertNotNull($document->issued_at);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame($sale->id, $document->sale_id);
    }

    public function test_a_second_retry_of_an_issued_document_is_rejected_without_changes(): void
    {
        $this->failEmission();
        $this->addToCart();
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $document = Document::query()->firstOrFail();
        $this->restoreSimulatedEmission();
        $this->actingAs($this->admin());

        $this->post(route('admin.documents.retry', $document))->assertSessionHasNoErrors();
        $issued = $document->fresh();

        $this->post(route('admin.documents.retry', $document))
            ->assertSessionHasErrors('document');

        $after = $document->fresh();

        $this->assertSame(DocumentStatus::issued, $after->status);
        $this->assertSame($issued->folio, $after->folio);
        $this->assertSame($issued->issued_at?->toIso8601String(), $after->issued_at?->toIso8601String());
        $this->assertSame(1, Document::query()->count());
    }

    public function test_retrying_a_rejected_document_keeps_its_existing_folio(): void
    {
        $this->addToCart();
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $document = Document::query()->firstOrFail();
        $document->update(['status' => DocumentStatus::rejected, 'issued_at' => null]);

        $this->actingAs($this->admin());
        $this->post(route('admin.documents.retry', $document))
            ->assertSessionHasNoErrors();

        $retried = $document->fresh();

        $this->assertSame(DocumentStatus::issued, $retried->status);
        $this->assertSame(1, $retried->folio);
        $this->assertSame(1, Document::query()->count());
    }

    public function test_an_exhausted_folio_range_leaves_the_sale_pending_without_a_folio(): void
    {
        config(['pos.folio.receipt_to' => 1]);

        $product = $this->addToCart();
        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertRedirect();

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $response = $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sales = Sale::query()->orderBy('id')->get();
        $documents = Document::query()->orderBy('id')->get();

        $this->assertSame(2, $sales->count());
        $this->assertSame(DocumentStatus::issued, $documents[0]->status);
        $this->assertSame(1, $documents[0]->folio);
        $this->assertSame(DocumentStatus::pending, $documents[1]->status);
        $this->assertNull($documents[1]->folio);
        $this->assertSame(1, (int) Document::query()->max('folio'));

        $response->assertRedirect(URL::signedRoute('pos.sales.receipt', $sales[1]));
        $this->get(URL::signedRoute('pos.sales.receipt', $sales[1]))->assertSee('Provisorio');
    }

    public function test_invoice_details_survive_an_emission_failure_and_retry(): void
    {
        $this->addToCart();
        $this->post('/pos/checkout', ['payment_method' => 'debit'])->assertRedirect();

        $this->failEmission();
        $product = $this->addToCart();
        $this->post('/pos/checkout', $this->invoicePayload())->assertRedirect();

        $invoiceSale = Sale::query()->orderByDesc('id')->firstOrFail();
        $document = Document::query()->where('sale_id', $invoiceSale->id)->firstOrFail();

        $this->assertSame(DocumentType::invoice, $document->type);
        $this->assertSame(DocumentStatus::pending, $document->status);
        $this->assertNull($document->folio);

        $detail = InvoiceDetail::query()->where('sale_id', $invoiceSale->id)->firstOrFail();
        $snapshot = $detail->only([
            'rut',
            'business_name',
            'business_activity',
            'address',
            'commune',
            'folio_from',
            'folio_to',
        ]);

        $this->restoreSimulatedEmission();
        $this->actingAs($this->admin());
        $this->post(route('admin.documents.retry', $document))->assertSessionHasNoErrors();

        $retried = $document->fresh();
        $after = InvoiceDetail::query()->where('sale_id', $invoiceSale->id)->firstOrFail();

        $this->assertSame(DocumentStatus::issued, $retried->status);
        $this->assertSame((int) config('pos.folio.invoice_from'), $retried->folio);
        $this->assertSame($snapshot, $after->only(array_keys($snapshot)));

        // Los rangos siguen separados: la boleta no cambió.
        $this->assertSame(1, Document::query()->where('type', DocumentType::receipt)->value('folio'));

        $this->get(URL::signedRoute('pos.sales.receipt', $invoiceSale))
            ->assertOk()
            ->assertSee('Factura electrónica')
            ->assertSee('Razón social: Comercial Andes SpA')
            ->assertDontSee('Provisorio');
    }

    public function test_a_repeated_checkout_token_does_not_create_a_second_sale_or_document(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $session = [
            'pos.cart' => [$product->id => 1],
            'pos.checkout_token' => '3f1c2b4a-0000-4000-8000-000000000009',
        ];

        $first = $this->withSession($session)->post('/pos/checkout', ['payment_method' => 'debit']);
        $first->assertSessionHasNoErrors();
        $second = $this->withSession($session)->post('/pos/checkout', ['payment_method' => 'debit']);
        $second->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $first->assertRedirect(URL::signedRoute('pos.sales.receipt', $sale));
        $second->assertRedirect(URL::signedRoute('pos.sales.receipt', $sale));

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(DocumentStatus::issued, Document::query()->firstOrFail()->status);
    }

    public function test_only_an_admin_can_retry_the_emission(): void
    {
        $this->failEmission();
        $this->addToCart();
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $document = Document::query()->firstOrFail();
        $this->restoreSimulatedEmission();

        foreach (['tecnico', 'quimico'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('admin.documents.retry', $document))
                ->assertForbidden();
        }

        $this->assertSame(DocumentStatus::pending, $document->fresh()->status);

        $this->actingAs($this->admin())
            ->post(route('admin.documents.retry', $document))
            ->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::issued, $document->fresh()->status);
    }

    public function test_a_voided_sale_with_a_pending_document_cannot_be_reissued(): void
    {
        $this->failEmission();
        $product = $this->addToCart(stock: 10);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();
        $document = Document::query()->firstOrFail();

        $admin = $this->admin();
        $this->actingAs($admin);
        $this->post(route('pos.sales.void', $sale), ['reason' => 'Cliente desistió de la compra'])
            ->assertSessionHasNoErrors();

        $sale = $sale->fresh();

        $this->assertSame(SaleStatus::voided, $sale->status);
        $this->assertSame(DocumentStatus::pending, $document->fresh()->status);
        $this->assertSame(0, Document::query()->whereNotNull('reversal_of_id')->count());

        $this->restoreSimulatedEmission();

        $this->post(route('admin.documents.retry', $document))
            ->assertSessionHasErrors('document');

        $this->assertSame(DocumentStatus::pending, $document->fresh()->status);
        $this->assertNull($document->fresh()->folio);

        $this->get(route('admin.sales.index'))
            ->assertOk()
            ->assertDontSee(route('admin.documents.retry', $document), false);

        $this->get(route('admin.documents.index'))
            ->assertOk()
            ->assertDontSee(route('admin.documents.retry', $document), false);
    }

    public function test_a_database_error_during_emission_is_not_masked_as_pending(): void
    {
        $this->app->instance(DocumentEmitter::class, new class implements DocumentEmitter
        {
            public function emit(DocumentType $type): void
            {
                throw new QueryException('pgsql', 'SELECT 1', [], new \RuntimeException('caída simulada'));
            }
        });

        $this->addToCart(stock: 10);

        try {
            $this->withoutExceptionHandling()->post('/pos/checkout', ['payment_method' => 'debit']);
            $this->fail('Un error de base de datos en la emisión no debe silenciarse.');
        } catch (QueryException) {
            // Esperado: el error de BD permanece visible.
        }

        // La venta ya estaba confirmada: error visible, sin estado incoherente.
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(DocumentStatus::pending, Document::query()->firstOrFail()->status);
        $this->assertSame(9, Product::query()->firstOrFail()->stock);
    }

    // ------------------------------------------------------------------
    // Apoyo
    // ------------------------------------------------------------------

    private function addToCart(int $stock = 10): Product
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => $stock]);

        $this->post('/pos/cart', ['product_id' => $product->id])->assertRedirect('/pos');

        return $product;
    }

    /**
     * @return array<string, string>
     */
    private function invoicePayload(): array
    {
        return [
            'payment_method' => 'debit',
            'document_type' => 'invoice',
            'customer_rut' => '12.345.678-5',
            'invoice_business_name' => 'Comercial Andes SpA',
            'invoice_business_activity' => 'Comercio al por menor',
            'invoice_address' => 'Av. Providencia 1234',
            'invoice_commune' => 'Providencia',
        ];
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

    private function restoreSimulatedEmission(): void
    {
        $this->app->forgetInstance(DocumentEmitter::class);

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

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
