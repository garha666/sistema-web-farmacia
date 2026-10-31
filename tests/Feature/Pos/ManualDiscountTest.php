<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\DiscountOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S4 - RF-13 (descuento manual), RF-16 (tope y supervisor)
 * y RF-17 (motivo obligatorio).
 */
final class ManualDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'tecnico']);
        $this->actingAs($this->cashier);
    }

    public function test_a_manual_percent_discount_within_the_cap_is_applied_without_supervisor(): void
    {
        $this->addProduct(price: 10_000);

        $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '10',
            'discount_reason' => 'Promoción de mostrador',
        ])->assertSessionHasNoErrors();

        $this->get('/pos')
            ->assertOk()
            ->assertSee('Promoción de mostrador')
            ->assertSee('$9.000');

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame(1_000, $sale->global_discount);
        $this->assertSame(9_000, $sale->total);
        $this->assertSame(DiscountOrigin::manual, $sale->discount_origin);
        $this->assertSame('Promoción de mostrador', $sale->discount_reason);
        $this->assertNull($sale->discount_authorized_by);

        $this->get(route('pos.sales.receipt', $sale))
            ->assertOk()
            ->assertSee('Promoción de mostrador');
    }

    public function test_an_amount_discount_within_the_cap_is_applied(): void
    {
        $this->addProduct(price: 10_000);

        $this->post('/pos/discount', [
            'discount_type' => 'amount',
            'discount_value' => '500',
            'discount_reason' => 'Ajuste de precio',
        ])->assertSessionHasNoErrors();

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame(500, $sale->global_discount);
        $this->assertSame(9_500, $sale->total);
        $this->assertSame(DiscountOrigin::manual, $sale->discount_origin);
    }

    public function test_a_discount_without_reason_is_rejected(): void
    {
        $this->addProduct(price: 10_000);

        $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '5',
            'discount_reason' => '',
        ])->assertSessionHasErrors('discount_reason');

        $this->assertNull(session('pos.manual_discount'));
    }

    public function test_a_discount_that_leaves_the_total_at_zero_is_rejected(): void
    {
        $this->addProduct(price: 10_000);

        $this->post('/pos/discount', [
            'discount_type' => 'amount',
            'discount_value' => '10000',
            'discount_reason' => 'Todo gratis',
        ])->assertSessionHasErrors('discount_value');

        $this->assertNull(session('pos.manual_discount'));
    }

    public function test_a_discount_above_the_cap_opens_the_supervisor_modal_and_requires_credentials(): void
    {
        $this->addProduct(price: 10_000);

        $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '15',
            'discount_reason' => 'Cliente frecuente',
        ])->assertSessionHasErrors('discount_supervisor');

        $this->assertNull(session('pos.manual_discount'));

        $this->get('/pos')
            ->assertOk()
            ->assertSee('Autorización de supervisor')
            ->assertSee('supervisor_email', false)
            ->assertSee('supervisor_password', false);

        // Un cajero no puede autorizar: se exigen credenciales de Químico/Admin.
        $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '15',
            'discount_reason' => 'Cliente frecuente',
            'supervisor_email' => $this->cashier->email,
            'supervisor_password' => 'password',
        ])->assertSessionHasErrors('supervisor_email');

        $this->assertNull(session('pos.manual_discount'));

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '15',
            'discount_reason' => 'Cliente frecuente',
            'supervisor_email' => $supervisor->email,
            'supervisor_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame(1_500, $sale->global_discount);
        $this->assertSame(8_500, $sale->total);
        $this->assertSame(DiscountOrigin::manual, $sale->discount_origin);
        $this->assertSame('Cliente frecuente', $sale->discount_reason);
        $this->assertSame($supervisor->id, $sale->discount_authorized_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.discount_authorized',
            'auditable_id' => $sale->id,
            'user_id' => $supervisor->id,
        ]);

        $this->get(route('pos.sales.receipt', $sale))
            ->assertOk()
            ->assertSee('Cliente frecuente')
            ->assertSee($supervisor->name);
    }

    public function test_removing_the_manual_discount_restores_the_total(): void
    {
        $this->addProduct(price: 10_000);

        $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '5',
            'discount_reason' => 'Descuento temporal',
        ])->assertSessionHasNoErrors();

        $this->delete('/pos/discount')->assertSessionHasNoErrors();

        $this->assertNull(session('pos.manual_discount'));

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame(0, $sale->global_discount);
        $this->assertSame(10_000, $sale->total);
        $this->assertNull($sale->discount_origin);
    }

    private function addProduct(int $price): Product
    {
        $product = Product::factory()->create(['price' => $price, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id]);

        return $product;
    }
}
