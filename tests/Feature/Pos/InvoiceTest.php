<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S6 - RF-23 y S-2: factura simulada con RUT, razón social, giro,
 * dirección y comuna; folio del rango de facturas y datos visibles en
 * el comprobante.
 */
final class InvoiceTest extends TestCase
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

    public function test_checkout_offers_the_receipt_and_invoice_selector(): void
    {
        $this->addToCart();

        $response = $this->get('/pos/checkout');

        $response->assertOk();
        $response->assertSee('name="document_type"', false);
        $response->assertSee('value="receipt"', false);
        $response->assertSee('value="invoice"', false);
        $response->assertSee('Boleta');
        $response->assertSee('Factura');

        // Campos de factura presentes pero ocultos hasta elegir «Factura».
        $response->assertSee('data-invoice-fields', false);
        $response->assertSee('name="invoice_business_name"', false);
        $response->assertSee('name="invoice_business_activity"', false);
        $response->assertSee('name="invoice_address"', false);
        $response->assertSee('name="invoice_commune"', false);
    }

    public function test_invoice_requires_the_customer_tax_data(): void
    {
        $product = $this->addToCart();

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'document_type' => 'invoice',
        ]);

        $response->assertSessionHasErrors([
            'customer_rut',
            'invoice_business_name',
            'invoice_business_activity',
            'invoice_address',
            'invoice_commune',
        ]);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_invoice_is_issued_with_its_own_folio_range_and_details(): void
    {
        $this->addToCart();

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'document_type' => 'invoice',
            'customer_rut' => '12.345.678-9',
            'invoice_business_name' => 'Comercial Andes SpA',
            'invoice_business_activity' => 'Comercio al por menor',
            'invoice_address' => 'Av. Providencia 1234',
            'invoice_commune' => 'Providencia',
        ]);

        $sale = Sale::query()->firstOrFail();
        $response->assertRedirect(route('pos.sales.receipt', $sale));

        $document = Document::query()->firstOrFail();

        $this->assertSame('invoice', $document->type->value);
        $this->assertSame((int) config('pos.folio.invoice_from'), $document->folio);
        $this->assertGreaterThanOrEqual((int) config('pos.folio.invoice_from'), $document->folio);
        $this->assertLessThanOrEqual((int) config('pos.folio.invoice_to'), $document->folio);

        $detail = DB::table('invoice_details')->where('sale_id', $sale->id)->first();

        $this->assertNotNull($detail, 'La factura debe guardar sus datos en invoice_details.');
        $this->assertSame('12.345.678-9', $detail->rut);
        $this->assertSame('Comercial Andes SpA', $detail->business_name);
        $this->assertSame('Comercio al por menor', $detail->business_activity);
        $this->assertSame('Av. Providencia 1234', $detail->address);
        $this->assertSame('Providencia', $detail->commune);
        $this->assertSame((int) config('pos.folio.invoice_from'), (int) $detail->folio_from);
        $this->assertSame((int) config('pos.folio.invoice_to'), (int) $detail->folio_to);

        $receipt = $this->get(route('pos.sales.receipt', $sale));

        $receipt->assertOk();
        $receipt->assertSee('Factura electrónica');
        $receipt->assertSee('RUT 12.345.678-9');
        $receipt->assertSee('Razón social: Comercial Andes SpA');
        $receipt->assertSee('Giro: Comercio al por menor');
        $receipt->assertSee('Dirección: Av. Providencia 1234');
        $receipt->assertSee('Comuna: Providencia');
    }

    public function test_boleta_is_still_the_default_and_has_no_invoice_details(): void
    {
        $this->addToCart();

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();
        $document = Document::query()->firstOrFail();

        $this->assertSame('receipt', $document->type->value);
        $this->assertSame(1, $document->folio);
        $this->assertSame(0, DB::table('invoice_details')->count());

        $receipt = $this->get(route('pos.sales.receipt', $sale));

        $receipt->assertSee('Boleta electrónica');
        $receipt->assertDontSee('Razón social:');
    }

    public function test_invoice_and_receipt_folios_are_independent(): void
    {
        $this->addToCart();
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->addToCart();
        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'document_type' => 'invoice',
            'customer_rut' => '12.345.678-9',
            'invoice_business_name' => 'Comercial Andes SpA',
            'invoice_business_activity' => 'Comercio al por menor',
            'invoice_address' => 'Av. Providencia 1234',
            'invoice_commune' => 'Providencia',
        ]);

        $receiptFolio = Document::query()->where('type', 'receipt')->value('folio');
        $invoiceFolio = Document::query()->where('type', 'invoice')->value('folio');

        $this->assertSame(1, $receiptFolio);
        $this->assertSame((int) config('pos.folio.invoice_from'), $invoiceFolio);
    }

    private function addToCart(): Product
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id])->assertRedirect('/pos');

        return $product;
    }
}
