<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\SaleStatus;
use App\Services\Pos\VoidSaleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T9 - RF-32 (anular con motivo), RF-33 (inmutabilidad + hecho separado),
 * RF-34 (nota de crédito simulada), RF-35 (anulación única),
 * RF-36 (registro de autorizaciones).
 */
final class VoidSaleTest extends TestCase
{
    use RefreshDatabase;

    private function sell(int $quantity = 2, int $stock = 10): array
    {
        $product = Product::factory()->create(['stock' => $stock, 'price' => 1000]);

        for ($i = 0; $i < $quantity; $i++) {
            $this->post('/pos/cart', ['product_id' => $product->id]);
        }

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        return [$product, Sale::query()->firstOrFail()];
    }

    public function test_supervisor_voids_a_sale_and_the_stock_returns(): void
    {
        [$product, $sale] = $this->sell();
        $supervisor = User::factory()->create(['role' => 'quimico']);

        $response = $this->actingAs($supervisor)->post(route('pos.sales.void', $sale), [
            'reason' => 'Cliente desiste de la compra',
        ]);

        $response->assertRedirect(route('pos.sales.receipt', $sale));
        $response->assertSessionHasNoErrors();

        $this->assertSame(SaleStatus::voided, $sale->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, (int) StockMovement::query()->sum('quantity'));

        $voidMovements = StockMovement::query()->where('reason', 'void')->get();
        $this->assertCount(1, $voidMovements);
        $this->assertSame(2, $voidMovements[0]->quantity);
    }

    public function test_voiding_without_a_reason_changes_nothing(): void
    {
        [$product, $sale] = $this->sell();

        $response = $this->actingAs(User::factory()->create(['role' => 'quimico']))
            ->post(route('pos.sales.void', $sale), ['reason' => '']);

        $response->assertSessionHasErrors('reason');

        $this->assertSame(SaleStatus::confirmed, $sale->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_void_registers_author_reason_and_santiago_timestamp(): void
    {
        [, $sale] = $this->sell();
        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->actingAs($supervisor)->post(route('pos.sales.void', $sale), [
            'reason' => 'Producto equivocado',
        ]);

        $log = AuditLog::query()->where('action', 'sale.void')->firstOrFail();

        $this->assertSame($supervisor->id, $log->user_id);
        $this->assertSame('Producto equivocado', $log->reason);
        $this->assertSame($sale->id, $log->auditable_id);
        $this->assertSame(Sale::class, $log->auditable_type);
        $this->assertSame('America/Santiago', $log->created_at->timezoneName);
    }

    public function test_the_original_sale_is_preserved_intact(): void
    {
        [, $sale] = $this->sell();
        $sale->load('lines', 'document');

        $snapshot = $sale->only([
            'token', 'subtotal', 'line_discounts', 'global_discount', 'net',
            'iva', 'total', 'payment_method', 'sold_at', 'customer_rut',
        ]);
        $lineSnapshot = $sale->lines[0]->only(['product_id', 'name', 'unit_price', 'quantity', 'subtotal']);
        $document = $sale->document;

        app(VoidSaleService::class)->void($sale, 'Error de cobro');

        $fresh = $sale->fresh('lines', 'document');

        $this->assertNotNull(Sale::query()->find($sale->id), 'La venta no debe borrarse.');
        $this->assertEquals($snapshot, $fresh->only(array_keys($snapshot)));
        $this->assertEquals($lineSnapshot, $fresh->lines[0]->only(array_keys($lineSnapshot)));

        $this->assertSame($document->folio, $fresh->document->folio);
        $this->assertSame(DocumentStatus::issued, $fresh->document->status);
    }

    public function test_voiding_an_issued_sale_generates_a_simulated_credit_note(): void
    {
        [, $sale] = $this->sell();
        $original = $sale->document;

        app(VoidSaleService::class)->void($sale, 'Error de cobro');

        $creditNote = Document::query()->where('type', DocumentType::creditNote)->firstOrFail();

        $this->assertSame((int) config('pos.folio.credit_note_from'), $creditNote->folio);
        $this->assertSame(DocumentStatus::issued, $creditNote->status);
        $this->assertSame($sale->id, $creditNote->sale_id);
        $this->assertSame($original->id, $creditNote->reversal_of_id);
        $this->assertNotNull($creditNote->issued_at);
    }

    public function test_voiding_a_pending_document_does_not_create_a_credit_note(): void
    {
        config(['pos.folio.receipt_to' => 0]);
        [, $sale] = $this->sell();

        $this->assertSame(DocumentStatus::pending, $sale->document->status);

        app(VoidSaleService::class)->void($sale, 'Error de cobro');

        $this->assertSame(0, Document::query()->where('type', DocumentType::creditNote)->count());
    }

    public function test_a_sale_cannot_be_voided_twice_over_http(): void
    {
        [$product, $sale] = $this->sell();
        $payload = ['reason' => 'Cliente desiste de la compra'];

        $this->actingAs(User::factory()->create(['role' => 'quimico']));

        $this->post(route('pos.sales.void', $sale), $payload);
        $response = $this->post(route('pos.sales.void', $sale), $payload);

        $response->assertSessionHasErrors('reason');

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(1, AuditLog::query()->where('action', 'sale.void')->count());
        $this->assertSame(1, Document::query()->where('type', DocumentType::creditNote)->count());
    }

    public function test_the_service_rejects_a_second_void(): void
    {
        [, $sale] = $this->sell();
        $service = app(VoidSaleService::class);

        $service->void($sale, 'Cliente desiste de la compra');

        $this->expectException(DomainException::class);

        $service->void($sale->fresh(), 'Cliente desiste de la compra');
    }

    public function test_the_receipt_marks_the_sale_as_voided(): void
    {
        [, $sale] = $this->sell();

        app(VoidSaleService::class)->void($sale, 'Producto equivocado');

        $response = $this->get(route('pos.sales.receipt', $sale));

        $response->assertOk();
        $response->assertSee('ANULADA');
        $response->assertSee('Producto equivocado');
    }
}
