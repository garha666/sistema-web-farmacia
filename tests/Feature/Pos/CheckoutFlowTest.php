<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7 - RF-20 (medio de pago), RF-21 (vuelto), RF-22 (registrar venta),
 * RF-23 (documento simulado con folio), RF-24 (emisión no bloqueante),
 * RF-25 (comprobante reimprimible), RF-26 (estado del documento).
 */
final class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_with_empty_cart_is_rejected(): void
    {
        $response = $this->post('/pos/checkout', [
            'payment_method' => 'cash',
            'received_amount' => 5000,
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_checkout_screen_shows_the_total_to_pay(): void
    {
        $product = Product::factory()->create(['price' => 1990]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->get('/pos/checkout');

        $response->assertOk();
        $response->assertSee('$1.990');
        $response->assertSee('Efectivo');
    }

    public function test_cash_payment_below_total_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 1990]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'cash',
            'received_amount' => 1000,
        ]);

        $response->assertSessionHasErrors('received_amount');
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(50, $product->fresh()->stock);
    }

    public function test_cash_payment_creates_the_sale_and_calculates_the_change(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'cash',
            'received_amount' => 5000,
        ]);

        $sale = Sale::query()->firstOrFail();

        $response->assertRedirect(route('pos.sales.receipt', $sale));
        $this->assertSame(1990, $sale->total);
        $this->assertSame(5000, $sale->received_amount);
        $this->assertSame(3010, $sale->change_amount);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_card_payment_does_not_require_received_amount(): void
    {
        $product = Product::factory()->create(['price' => 1990]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Sale::query()->count());
        $this->assertNull(Sale::query()->firstOrFail()->change_amount);
    }

    public function test_receipt_shows_folio_issuer_and_simulation_notice(): void
    {
        $product = Product::factory()->create(['price' => 1990]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'cash',
            'received_amount' => 2000,
        ]);

        $response->assertRedirect();
        $receipt = $this->get($response->headers->get('Location'));

        $receipt->assertOk();
        $receipt->assertSee('Farmacia Grupo A SpA');
        $receipt->assertSee('76.543.210-K');
        $receipt->assertSee('Documento simulado');
        $receipt->assertSee('sin validez tributaria');
        $receipt->assertSee('Folio 1');
        $receipt->assertSee('$1.990');
    }

    public function test_sale_lines_freeze_the_price_at_confirmation_time(): void
    {
        $product = Product::factory()->create(['price' => 1990]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $product->update(['price' => 2990]);

        $sale = Sale::query()->with('lines')->firstOrFail();
        $this->assertSame(1990, $sale->lines[0]->unit_price);
        $this->assertSame(1990, $sale->total);
    }

    public function test_cart_is_cleared_after_a_successful_sale(): void
    {
        $product = Product::factory()->create();
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->get('/pos')->assertSee('Carrito vacío');
    }

    public function test_folio_increments_between_sales(): void
    {
        $product = Product::factory()->create();
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $folios = Document::query()->orderBy('folio')->pluck('folio')->all();

        $this->assertSame([1, 2], $folios);
    }

    public function test_stock_movement_is_registered_once_per_sale_line(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $movements = StockMovement::query()->get();

        $this->assertCount(1, $movements);
        $this->assertSame(-2, $movements[0]->quantity);
        $this->assertSame('sale', $movements[0]->reason);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_document_is_created_as_issued_with_a_single_folio(): void
    {
        $product = Product::factory()->create();
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $document = Document::query()->firstOrFail();

        $this->assertSame('receipt', $document->type->value);
        $this->assertSame('issued', $document->status->value);
        $this->assertSame(1, $document->folio);
    }

    public function test_reprinting_the_receipt_does_not_create_a_new_sale(): void
    {
        $product = Product::factory()->create();
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);
        $sale = Sale::query()->firstOrFail();

        $this->get(route('pos.sales.receipt', $sale))->assertOk();
        $this->get(route('pos.sales.receipt', $sale))->assertOk();

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, Document::query()->count());
    }

    public function test_double_submit_of_the_same_cart_creates_only_one_sale(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $payload = ['payment_method' => 'cash', 'received_amount' => 5000];
        $this->post('/pos/checkout', $payload);
        $this->post('/pos/checkout', $payload);

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_sale_keeps_the_seller_and_the_local_timestamp(): void
    {
        $product = Product::factory()->create();
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();

        $this->assertNotNull($sale->sold_at);
        $this->assertSame('America/Santiago', $sale->sold_at->timezoneName);
    }
}
