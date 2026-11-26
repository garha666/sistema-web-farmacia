<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S11 - RF-4/RF-13: columna de descuento por línea en el carrito.
 *
 * El descuento se expresa en porcentaje o monto, exige motivo, no deja la
 * línea en cero y el acumulado de la transacción no supera el tope general
 * (10%); sobre el tope pide supervisor. El cálculo ya existía en el dominio;
 * aquí se cubre la UI, la persistencia en sesión y los totales en vivo.
 */
final class LineDiscountTest extends TestCase
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

    public function test_the_cart_shows_a_per_line_discount_control(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id])->assertRedirect('/pos');

        $this->get('/pos')
            ->assertOk()
            ->assertSee('Descuento de línea', false);
    }

    public function test_applying_a_percent_line_discount_recalculates_the_totals(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ])->assertRedirect(route('pos.index'));

        $this->get('/pos')
            ->assertOk()
            ->assertSee('$1.000')       // subtotal
            ->assertSee('-$100')        // descuentos
            ->assertSee('10%', false);  // badge de la línea
    }

    public function test_applying_an_amount_line_discount_recalculates_the_totals(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'amount',
            'line_value' => '100',
            'line_reason' => 'Ajuste',
            ...$this->discountCredentials(true),
        ])->assertRedirect(route('pos.index'));

        $this->get('/pos')
            ->assertOk()
            ->assertSee('-$100');
    }

    public function test_removing_a_line_discount_restores_the_totals(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ]);

        $this->delete(route('pos.cart.discount.remove', $product))
            ->assertRedirect(route('pos.index'));

        $this->get('/pos')
            ->assertOk()
            ->assertSee('$1.000')
            ->assertDontSee('10%');
    }

    public function test_a_line_discount_requires_a_reason(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => '',
        ])->assertSessionHasErrors('line_reason');

        $this->get('/pos')->assertSee('$1.000');
    }

    public function test_a_line_discount_cannot_take_the_line_to_zero(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '100',
            'line_reason' => 'Regalo',
        ])->assertSessionHasErrors('line_value');

        $this->get('/pos')->assertSee('$1.000');
    }

    public function test_a_line_discount_over_the_cap_requires_a_supervisor(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        // 20% supera el tope general del 10%: sin supervisor no se aplica.
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
        ])->assertSessionHasErrors('line_discount_supervisor');

        $this->get('/pos')->assertSee('$1.000');

        // Con credenciales válidas de supervisor se aplica.
        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
            ...$this->discountCredentials(true, $supervisor),
        ])->assertRedirect(route('pos.index'));

        $this->get('/pos')
            ->assertOk()
            ->assertSee('$800')
            ->assertSee('20%', false);
    }

    public function test_invalid_supervisor_credentials_keep_the_modal_open(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post(route('pos.cart.discount', $product), [
            'line_product_id' => $product->id,
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
            'line_supervisor_email' => 'nadie@example.com',
            'line_supervisor_password' => 'secreto',
        ])->assertSessionHasErrors('line_discount_supervisor');

        // El modal se reabre con el motivo del rechazo.
        $this->get('/pos')
            ->assertOk()
            ->assertSeeText(__('pos.cash.discount.supervisor_title'))
            ->assertSeeText(__('pos.errors.supervisor_invalid'));
    }

    public function test_a_line_discount_survives_a_reload(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ]);

        // Nueva petición: la sesión debe rehidratar el descuento de la línea.
        $this->get('/pos')
            ->assertOk()
            ->assertSee('10%', false)
            ->assertSee('-$100');
    }

    public function test_a_line_discount_is_recalculated_when_the_quantity_changes(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ]);

        $this->patch(route('pos.cart.update', $product), ['quantity' => 3])
            ->assertRedirect(route('pos.index'));

        // Base 3 × $1.000 = $3.000; 10% = $300; total $2.700.
        $this->get('/pos')
            ->assertOk()
            ->assertSee('10%', false)
            ->assertSee('-$300')
            ->assertSee('data-testid="cart-total">$2.700', false);
    }

    public function test_the_global_discount_also_counts_the_line_discounts(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        // 10% de línea ya consume el tope: otro 10% global pide supervisor.
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ])->assertRedirect(route('pos.index'));

        $this->post(route('pos.discount.apply'), [
            'discount_type' => 'percent',
            'discount_value' => '10',
            'discount_reason' => 'Convenio interno',
        ])->assertSessionHasErrors('discount_supervisor');

        // Con autorización se aplica: 10% sobre la base de $900 y total $810.
        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.discount.apply'), [
            'discount_type' => 'percent',
            'discount_value' => '10',
            'discount_reason' => 'Convenio interno',
            ...$this->discountCredentials(supervisor: $supervisor),
        ])->assertRedirect(route('pos.index'));

        $this->get('/pos')
            ->assertOk()
            ->assertSee('-$190')
            ->assertSee('data-testid="cart-total">$810', false);
    }

    public function test_an_authorized_global_discount_combines_with_an_authorized_line_discount(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '5',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ]);

        // 5% de línea + 5% global = 9,8% acumulado: dentro del tope del 10%.
        $this->post(route('pos.discount.apply'), [
            'discount_type' => 'percent',
            'discount_value' => '5',
            'discount_reason' => 'Ajuste',
            ...$this->discountCredentials(),
        ])->assertRedirect(route('pos.index'));

        $this->get('/pos')
            ->assertOk()
            ->assertSee('-$98')
            ->assertSee('data-testid="cart-total">$902', false);
    }

    public function test_the_general_cap_counts_the_discounts_of_every_line(): void
    {
        $first = Product::factory()->create(['price' => 1000]);
        $second = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $first->id]);
        $this->post('/pos/cart', ['product_id' => $second->id]);

        // 10% en la primera línea: $100 sobre $2.000 = 5%, dentro del tope.
        $this->post(route('pos.cart.discount', $first), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ])->assertRedirect(route('pos.index'));

        // 20% en la segunda: acumulado $300 sobre $2.000 = 15%, sobre el tope.
        $this->post(route('pos.cart.discount', $second), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
        ])->assertSessionHasErrors('line_discount_supervisor');

        $this->get('/pos')
            ->assertOk()
            ->assertSee('-$100')
            ->assertDontSee('-$200');
    }

    public function test_the_line_discount_is_shown_on_the_checkout_screen(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ]);

        $this->get('/pos/checkout')
            ->assertOk()
            ->assertSeeText('Descuento de línea: -$100 (Promoción)')
            ->assertSeeText('$900');
    }

    public function test_the_line_discount_is_persisted_to_the_sale_line(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ]);

        $this->post('/pos/checkout', ['payment_method' => 'debit'])->assertRedirect();

        $sale = Sale::query()->firstOrFail();
        $line = $sale->lines()->firstOrFail();

        $this->assertSame(900, $sale->total);
        $this->assertSame(1000, $line->unit_price);
        $this->assertSame(100, $line->discount_amount);
        $this->assertSame(1, $line->quantity);
    }

    public function test_checkout_rejects_an_old_line_discount_without_pos_authorization(): void
    {
        $discounted = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $other = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $discounted->id]);
        $this->post('/pos/cart', ['product_id' => $other->id]);
        $this->patch(route('pos.cart.update', $discounted), ['quantity' => 3]);

        // $300 sobre una base de $3.000: 7,5% del carrito de $4.000, dentro del tope.
        $this->post(route('pos.cart.discount', $discounted), [
            'line_type' => 'amount',
            'line_value' => '300',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ])->assertRedirect(route('pos.index'));

        // Al bajar la cantidad, $300 sobre un carrito de $2.000 ya es 15%.
        $this->patch(route('pos.cart.update', $discounted), ['quantity' => 1])
            ->assertRedirect(route('pos.index'));

        // Simula un carrito abierto antes del cambio de política.
        $discounts = session('pos.line_discounts');
        unset($discounts[$discounted->id]['authorized_by']);
        $this->withSession(['pos.line_discounts' => $discounts])
            ->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasErrors('discount');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, $discounted->fresh()->stock);

    }

    public function test_checkout_accepts_a_line_discount_authorized_by_a_supervisor_after_the_cart_changes(): void
    {
        $discounted = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $other = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post('/pos/cart', ['product_id' => $discounted->id]);
        $this->post('/pos/cart', ['product_id' => $other->id]);
        $this->patch(route('pos.cart.update', $discounted), ['quantity' => 2]);

        // 20% sobre $2.000 = $400: 13,3% del carrito, sobre el tope y autorizado.
        $this->post(route('pos.cart.discount', $discounted), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
            ...$this->discountCredentials(true, $supervisor),
        ])->assertRedirect(route('pos.index'));

        $this->patch(route('pos.cart.update', $discounted), ['quantity' => 1])
            ->assertRedirect(route('pos.index'));

        // La autorización vigente cubre el descuento aunque el carrito cambie:
        // 20% de la nueva base de $1.000 son $200, sobre un subtotal de $2.000.
        $this->post('/pos/checkout', ['payment_method' => 'debit'])->assertRedirect();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame(1800, $sale->total);
    }

    public function test_checkout_rejects_a_line_discount_that_now_zeroes_the_line(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->patch(route('pos.cart.update', $product), ['quantity' => 3]);

        // $2.999 sobre una base de $3.000: sobre el tope, autorizado.
        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'amount',
            'line_value' => '2999',
            'line_reason' => 'Ajuste de precio',
            ...$this->discountCredentials(true, $supervisor),
        ])->assertRedirect(route('pos.index'));

        // Al bajar la cantidad el monto iguala la base: la línea queda en cero.
        $this->patch(route('pos.cart.update', $product), ['quantity' => 1])
            ->assertRedirect(route('pos.index'));

        // RF-18: ningún descuento puede dejar el total en cero o negativo.
        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasErrors('line_value');

        $this->assertSame(0, Sale::query()->count());
    }
}
