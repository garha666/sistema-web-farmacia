<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\AuditLog;
use App\Models\Convention;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Pos\Enum\PaymentMethod;
use App\Services\Pos\CartService;
use App\Services\Pos\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S12 - RF-36: auditoría de las autorizaciones de descuento por línea.
 *
 * La autorización ocurre en la caja (cuando la venta aún no existe), así que
 * el autor viaja en la sesión junto al carrito y se registra en `audit_logs`
 * al confirmar el cobro, ligado a la SaleLine real. La autorización del
 * descuento global y la receta reutilizada siguen auditándose igual.
 */
final class AuditTest extends TestCase
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

    public function test_an_authorized_line_discount_is_audited_with_its_author(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
            ...$this->discountCredentials(true, $supervisor),
        ])->assertRedirect(route('pos.index'));

        $this->post('/pos/checkout', ['payment_method' => 'debit'])->assertRedirect();

        $sale = Sale::query()->firstOrFail();
        $line = $sale->lines()->firstOrFail();

        $log = AuditLog::query()
            ->where('action', 'sale.line_discount_authorized')
            ->firstOrFail();

        $this->assertSame($supervisor->id, $log->user_id);
        $this->assertSame(SaleLine::class, $log->auditable_type);
        $this->assertSame($line->id, $log->auditable_id);
        $this->assertSame('percent', $log->discount_type);
        $this->assertSame(200, $log->discount_value);
    }

    public function test_the_line_discount_audit_keeps_the_reason_typed_by_the_cashier(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'name' => 'Ibuprofeno 400 mg']);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Cliente frecuente',
            ...$this->discountCredentials(true, $supervisor),
        ])->assertRedirect(route('pos.index'));

        $this->post('/pos/checkout', ['payment_method' => 'debit'])->assertRedirect();

        $log = AuditLog::query()
            ->where('action', 'sale.line_discount_authorized')
            ->firstOrFail();

        // RF-17/RF-36: se conserva el motivo que escribió el cajero.
        $this->assertSame('Cliente frecuente', $log->reason);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // La bitácora muestra el motivo real, no un texto generado.
        $this->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSeeText('Cliente frecuente');
    }

    public function test_the_audit_entry_resolves_its_sale_line_and_product(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'name' => 'Ibuprofeno 400 mg']);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'amount',
            'line_value' => '200',
            'line_reason' => 'Ajuste',
            ...$this->discountCredentials(true, $supervisor),
        ]);

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $log = AuditLog::query()
            ->where('action', 'sale.line_discount_authorized')
            ->firstOrFail();

        $this->assertNotNull($log->saleLine);
        $this->assertSame('Ibuprofeno 400 mg', $log->saleLine->name);
        $this->assertSame('amount', $log->discount_type);
        $this->assertSame(200, $log->discount_value);
    }

    public function test_a_line_discount_within_the_cap_is_audited(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '10',
            'line_reason' => 'Promoción',
            ...$this->discountCredentials(true),
        ])->assertRedirect(route('pos.index'));

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'sale.line_discount_authorized')->count(),
        );
    }

    public function test_removing_the_line_discount_before_checkout_avoids_the_audit(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
            ...$this->discountCredentials(true, $supervisor),
        ]);

        $this->delete(route('pos.cart.discount.remove', $product));

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $this->assertSame(
            0,
            AuditLog::query()->where('action', 'sale.line_discount_authorized')->count(),
        );
    }

    public function test_a_replayed_checkout_does_not_duplicate_the_line_audit(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
            ...$this->discountCredentials(true, $supervisor),
        ]);

        $cartService = app(CartService::class);
        $cart = $cartService->load();
        $authors = $cartService->lineDiscountAuthors();
        $checkout = app(CheckoutService::class);

        // Mismo token: la segunda llamada devuelve la venta existente.
        $token = '8f14e45f-ceea-4a4a-9f0e-0e6b3a2c1d90';

        $checkout->checkout(
            cart: $cart,
            method: PaymentMethod::debit,
            receivedAmount: null,
            customerRut: null,
            token: $token,
            userId: $this->cashier->id,
            lineDiscountAuthorizedBy: $authors,
        );

        $checkout->checkout(
            cart: $cart,
            method: PaymentMethod::debit,
            receivedAmount: null,
            customerRut: null,
            token: $token,
            userId: $this->cashier->id,
            lineDiscountAuthorizedBy: $authors,
        );

        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'sale.line_discount_authorized')->count(),
        );
    }

    public function test_the_global_discount_authorization_is_still_audited(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.discount.apply'), [
            'discount_type' => 'percent',
            'discount_value' => '20',
            'discount_reason' => 'Convenio interno',
            ...$this->discountCredentials(supervisor: $supervisor),
        ])->assertRedirect(route('pos.index'));

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $log = AuditLog::query()
            ->where('action', 'sale.discount_authorized')
            ->firstOrFail();

        $this->assertSame($supervisor->id, $log->user_id);
        $this->assertNull($log->discount_type);
    }

    public function test_a_convention_does_not_inherit_authorization_for_a_discarded_manual_discount(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post(route('pos.discount.apply'), [
            'discount_type' => 'percent', 'discount_value' => 5, 'discount_reason' => 'Ajuste manual',
            ...$this->discountCredentials(),
        ])->assertSessionHasNoErrors();
        $convention = Convention::factory()->create(['percent' => 20]);

        $this->post('/pos/checkout', ['payment_method' => 'debit', 'convention_id' => $convention->id])->assertSessionHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertSame(200, $sale->global_discount);
        $this->assertSame($convention->id, $sale->convention_id);
        $this->assertNull($sale->discount_authorized_by);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sale.discount_authorized']);
    }

    public function test_the_audit_view_shows_the_line_discount_type_and_product(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'name' => 'Ibuprofeno 400 mg']);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post(route('pos.cart.discount', $product), [
            'line_type' => 'percent',
            'line_value' => '20',
            'line_reason' => 'Convenio interno',
            ...$this->discountCredentials(true, $supervisor),
        ]);

        $this->post('/pos/checkout', ['payment_method' => 'debit']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSeeText('Ibuprofeno 400 mg')
            ->assertSeeText(__('pos.panel.audit_action.sale.line_discount_authorized'));
    }

    public function test_quimico_and_admin_read_the_audit_and_tecnico_is_forbidden(): void
    {
        $this->get(route('admin.audit.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'quimico']));
        $this->get(route('admin.audit.index'))->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.audit.index'))->assertOk();
    }
}
