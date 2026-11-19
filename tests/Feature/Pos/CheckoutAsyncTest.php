<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Calculator\PriceCalculator;
use App\Services\Pos\CartService;
use App\Services\Pos\ManualDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T34 - RF-89: cobro a salvo (verificación de stock y catálogo al confirmar).
 *
 * Justo antes de confirmar la venta, el sistema verifica que cada línea del
 * carrito sigue teniendo stock y que el producto sigue en el catálogo. Si algo
 * cambió, la venta no se confirma y el carrito se conserva.
 */
final class CheckoutAsyncTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // S1 - RF-22: el POS exige sesión y la venta guarda al cajero.
        $this->cashier = User::factory()->create(['role' => 'tecnico']);
        $this->actingAs($this->cashier);
        $this->openCashRegister($this->cashier);
    }

    /** RF-89: una venta no se confirma si el producto salió del catálogo. */
    public function test_checkout_is_rejected_when_product_is_inactive(): void
    {
        $product = Product::factory()->create(['price' => 1990]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        // El producto sale del catálogo justo antes de cobrar.
        $product->forceFill(['active' => false])->save();

        $ventasAntes = Sale::query()->count();
        $this->assertSame(0, $ventasAntes, 'Ventas antes del checkout: '.$ventasAntes);

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $response->assertRedirect();
        // El checkout no debe crear una venta cuando el producto salió del catálogo.
        $sales = Sale::query()->get();
        $this->assertSame(0, $sales->count(), 'Ventas después del checkout: '.json_encode($sales->map(fn ($s) => ['id' => $s->id, 'total' => $s->total])->toArray()));
    }

    /** RF-89: una venta no se confirma si una línea supera el stock disponible. */
    public function test_checkout_is_rejected_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->patch("/pos/cart/{$product->id}", ['quantity' => 5]);

        // Se agota el producto justo antes de cobrar.
        $product->forceFill(['stock' => 3])->save();

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertSame(0, Sale::query()->count());
        // El carrito se conserva.
        $this->assertSame(5, $this->cartTotalQuantity());
    }

    /** RF-89: el carrito se conserva intacto tras un cobro rechazado. */
    public function test_cart_is_preserved_after_rejected_checkout(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->patch("/pos/cart/{$product->id}", ['quantity' => 5]);

        $product->forceFill(['stock' => 3])->save();

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->assertSame(5, $this->cartTotalQuantity());
        $this->assertSame(9950, $this->cartSubtotal());
    }

    /** RF-89: una venta válida se confirma normalmente. */
    public function test_valid_checkout_is_confirmed(): void
    {
        $product = Product::factory()->create(['price' => 1990, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1990, Sale::query()->firstOrFail()->total);
        $this->assertSame(9, $product->fresh()->stock);
    }

    private function cartTotalQuantity(): int
    {
        $cart = app(CartService::class)->load();

        return $cart->totalQuantity();
    }

    private function cartSubtotal(): int
    {
        $cart = app(CartService::class)->load();
        $totals = app(PriceCalculator::class)->totals(
            $cart,
            (int) config('pos.iva_rate'),
            app(ManualDiscountService::class)->load(),
        );

        return $totals->subtotal()->pesos();
    }
}
