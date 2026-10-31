<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use App\Pos\Exception\InsufficientStock;
use App\Pos\Money;
use App\Pos\Value\Cart;
use App\Pos\Value\ProductSnapshot;
use App\Services\Pos\CartService;
use App\Services\Pos\CheckoutService;
use App\Services\Pos\VoidSaleService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T8 - RF-27 (sin sobreventa), RF-28 (venta simultánea), RF-29 (cambio de stock),
 * RF-30 (descuento único), RF-31 (reposición por anulación).
 *
 * Usa DatabaseMigrations (no transacciones de test) porque la concurrencia
 * necesita dos conexiones reales a PostgreSQL viendo los mismos datos.
 */
final class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión.
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
    }

    public function test_two_connections_cannot_sell_the_same_last_unit(): void
    {
        $product = Product::factory()->create(['stock' => 1, 'price' => 1000]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $cartService = app(CartService::class);
        $cart = $cartService->load();
        $token = $cartService->token();

        DB::beginTransaction();
        $saleA = app(CheckoutService::class)->checkout($cart, PaymentMethod::debit, null, null, $token);
        $this->assertNotNull($saleA->id);

        config(['database.connections.pgsql_second' => config('database.connections.pgsql')]);
        DB::purge('pgsql_second');
        DB::setDefaultConnection('pgsql_second');
        DB::connection('pgsql_second')->statement("SET lock_timeout = '500ms'");

        $blockedBy = null;

        try {
            app(CheckoutService::class)->checkout(
                $cart,
                PaymentMethod::debit,
                null,
                null,
                (string) Str::uuid(),
            );
        } catch (\Throwable $exception) {
            $blockedBy = $exception;
        } finally {
            DB::setDefaultConnection('pgsql');
            DB::commit();
        }

        $this->assertInstanceOf(
            \Illuminate\Database\QueryException::class,
            $blockedBy,
            'La segunda conexión debió quedar bloqueada por el lock de la fila.',
        );
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, \App\Models\Document::query()->count());
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(-1, (int) StockMovement::query()->sum('quantity'));
    }

    public function test_second_cart_loses_when_the_last_unit_was_already_sold(): void
    {
        $product = Product::factory()->create(['stock' => 1, 'price' => 1000]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $staleCart = Cart::empty()->add(new ProductSnapshot(
            id: $product->id,
            name: $product->name,
            unitPrice: Money::fromPesos(1000),
            category: $product->sale_category,
            availableStock: 1,
        ));

        $this->expectException(InsufficientStock::class);

        app(CheckoutService::class)->checkout(
            $staleCart,
            PaymentMethod::debit,
            null,
            null,
            (string) Str::uuid(),
        );
    }

    public function test_losing_cart_does_not_persist_a_sale(): void
    {
        $product = Product::factory()->create(['stock' => 1, 'price' => 1000]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $staleCart = Cart::empty()->add(new ProductSnapshot(
            id: $product->id,
            name: $product->name,
            unitPrice: Money::fromPesos(1000),
            category: $product->sale_category,
            availableStock: 1,
        ));

        try {
            app(CheckoutService::class)->checkout($staleCart, PaymentMethod::debit, null, null, (string) Str::uuid());
        } catch (InsufficientStock) {
            // esperado
        }

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(-1, (int) StockMovement::query()->sum('quantity'));
    }

    public function test_stock_movements_sum_matches_the_final_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 1000]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $finalStock = $product->fresh()->stock;
        $movements = (int) StockMovement::query()->where('product_id', $product->id)->sum('quantity');

        $this->assertSame(7, $finalStock);
        $this->assertSame(10 + $movements, $finalStock);
    }

    public function test_stock_is_not_deducted_twice_for_the_same_sale(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'price' => 1000]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $payload = ['payment_method' => 'cash', 'received_amount' => 5000];
        $this->post('/pos/checkout', $payload);
        $this->post('/pos/checkout', $payload);

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(-2, (int) StockMovement::query()->sum('quantity'));
    }

    public function test_voiding_a_sale_returns_the_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 1000]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame(8, $product->fresh()->stock);

        app(VoidSaleService::class)->void($sale, 'Error de cobro');

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(SaleStatus::voided, $sale->fresh()->status);
        $this->assertSame(0, (int) StockMovement::query()->sum('quantity'));

        $voidMovements = StockMovement::query()->where('reason', 'void')->get();
        $this->assertCount(1, $voidMovements);
        $this->assertSame(2, $voidMovements[0]->quantity);
    }

    public function test_a_sale_cannot_be_voided_twice(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $sale = Sale::query()->firstOrFail();
        app(VoidSaleService::class)->void($sale, 'Error de cobro');

        $this->expectException(\DomainException::class);

        app(VoidSaleService::class)->void($sale->fresh(), 'Error de cobro');
    }
}
