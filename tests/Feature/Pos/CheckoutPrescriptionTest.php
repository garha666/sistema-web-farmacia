<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S2 - RF-8 (exigir receta), RF-9 (vigencia de 30 días),
 * RF-11 (receta reutilizada exige supervisor) y RF-10 (libro de controlados).
 */
final class CheckoutPrescriptionTest extends TestCase
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

    public function test_a_controlled_sale_without_prescription_is_rejected(): void
    {
        $product = $this->controlledProduct();
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->get('/pos/checkout')
            ->assertOk()
            ->assertSee('Receta médica');

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasErrors([
                'prescription_number',
                'prescription_prescriber_name',
                'prescription_prescriber_rut',
                'prescription_issued_at',
            ]);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_prescription_older_than_thirty_days_is_rejected(): void
    {
        $product = $this->controlledProduct();
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post('/pos/checkout', $this->payload(now()->subDays(31)))
            ->assertSessionHasErrors('prescription_issued_at');

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_a_prescription_valid_on_its_last_day_is_accepted(): void
    {
        $product = $this->controlledProduct();
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post('/pos/checkout', $this->payload(now()->subDays(30)) + $this->pharmacistCredentials())
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();
        $document = $sale->document()->firstOrFail();

        $this->assertNotNull($sale->prescription_id);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $sale->prescription_id,
            'number' => 'REC-100',
            'prescriber_rut' => '9.876.543-2',
        ]);
        $this->assertDatabaseHas('controlled_register', [
            'sale_id' => $sale->id,
            'prescription_id' => $sale->prescription_id,
            'medication' => 'Tramadol 50 mg',
            'quantity' => 1,
            'folio' => $document->folio,
        ]);
    }

    public function test_a_reused_prescription_requires_supervisor_authorization(): void
    {
        $prescription = Prescription::query()->create([
            'number' => 'REC-100',
            'prescriber_name' => 'Dra. Ana Pérez',
            'prescriber_rut' => '9.876.543-2',
            'issued_on' => now()->subDays(5)->toDateString(),
        ]);

        $product = $this->controlledProduct();
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $payload = $this->payload(now()->subDays(5));

        $this->post('/pos/checkout', $payload)
            ->assertSessionHasErrors('prescription_approval');

        $this->assertSame(0, Sale::query()->count());

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post('/pos/checkout', $payload + $this->pharmacistCredentials($supervisor))->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame($prescription->id, $sale->prescription_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.prescription_reuse',
            'auditable_id' => $sale->id,
            'user_id' => $supervisor->id,
        ]);
    }

    public function test_a_free_sale_needs_no_prescription(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertNull($sale->prescription_id);
    }

    public function test_a_reused_prescription_with_different_details_cannot_be_requested_or_charged(): void
    {
        $today = now(config('pos.timezone'));
        Prescription::query()->create([
            'number' => 'REC-100', 'prescriber_name' => 'Dra. Ana Pérez', 'prescriber_rut' => '9.876.543-2',
            'issued_on' => $today->copy()->subDay()->toDateString(),
        ]);
        $product = $this->controlledProduct();
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $payload = $this->payload($today);
        $this->post('/pos/checkout', $payload + ['request_review' => '1'])->assertSessionHasErrors('prescription_issued_at');
        $this->assertDatabaseCount('prescription_reviews', 0);
        $credentials = $this->pharmacistCredentials();
        $this->post('/pos/checkout', $payload + $credentials)->assertSessionHasErrors('prescription_issued_at');
        $this->post('/pos/checkout', array_replace($this->payload($today->copy()->subDay()), [
            'prescription_prescriber_name' => 'Otro profesional',
        ]) + $credentials)->assertSessionHasErrors('prescription_prescriber_name');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    private function controlledProduct(): Product
    {
        return Product::factory()->controlled()->create([
            'name' => 'Tramadol 50 mg',
            'price' => 5000,
            'stock' => 5,
        ]);
    }

    public function test_a_valid_controlled_prescription_does_not_allow_checkout_without_a_chemist(): void
    {
        $product = $this->controlledProduct();
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', $this->payload(now(config('pos.timezone'))))
            ->assertSessionHasErrors('prescription_approval');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_the_chemist_reviews_a_prescription_and_only_the_approved_basket_can_be_charged(): void
    {
        $product = Product::factory()->create(['sale_category' => 'prescription', 'stock' => 5]);
        $chemist = User::factory()->quimico()->create();
        $payload = $this->payload(now(config('pos.timezone')));
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', $payload + ['request_review' => '1'])->assertRedirect('/pos/checkout');
        $this->get('/pos/checkout')->assertSee(__('pos.rx_review.pending'));
        $this->post('/pos/checkout', $payload)->assertSessionHasErrors('prescription_approval');

        $panel = $this->actingAs($chemist)->get('/panel/quimico')->assertOk()
            ->assertSee('REC-100')->assertSee('Dra. Ana Pérez')->assertSee($product->name)->getContent();
        preg_match('~action="([^" ]+/recetas/\d+/approve)"~', $panel, $action);
        preg_match('~name="fingerprint" value="([a-f0-9]{64})"~', $panel, $version);
        $this->assertNotEmpty($action);
        $this->assertNotEmpty($version);
        $this->actingAs($this->cashier)->post($action[1], ['fingerprint' => $version[1]])->assertForbidden();
        $this->actingAs($chemist)->post($action[1], ['fingerprint' => $version[1]])->assertSessionHas('prescription_approved');
        $this->assertDatabaseCount('sales', 0);

        $this->actingAs($this->cashier)->get('/pos/checkout')->assertSee(__('pos.rx_review.approved'));
        $this->post('/pos/checkout', array_replace($payload, ['prescription_number' => 'REC-DIFERENTE']))
            ->assertSessionHasErrors('prescription_approval');
        $this->patch(route('pos.cart.update', $product), ['quantity' => 2]);
        $this->get('/pos/checkout')->assertDontSee(__('pos.rx_review.approved'))->assertSee(__('pos.rx_review.checkout_waiting'));
        $this->post('/pos/checkout', $payload)->assertSessionHasErrors('prescription_approval');
        $this->patch(route('pos.cart.update', $product), ['quantity' => 1]);
        $this->post('/pos/checkout', $payload)->assertSessionHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.prescription_approved', 'auditable_id' => $sale->id, 'user_id' => $chemist->id]);
        $this->assertDatabaseCount('prescription_reviews', 0);
    }

    public function test_a_chemist_can_configure_a_numeric_pin_that_only_their_role_can_use(): void
    {
        $chemist = User::factory()->quimico()->create();
        $route = route('account.pharmacist-pin.update');
        $data = ['current_password' => 'password', 'pharmacist_pin' => '0843719205', 'pharmacist_pin_confirmation' => '0843719205'];
        $this->put($route, $data)->assertForbidden();
        $this->actingAs($chemist)->put($route, $data)->assertSessionHas('pharmacist_pin_updated');

        $product = $this->controlledProduct();
        $this->actingAs($this->cashier)->post('/pos/cart', ['product_id' => $product->id]);
        $payload = $this->payload(now(config('pos.timezone')));
        $this->post('/pos/checkout', $payload + ['chemist_email' => $chemist->email, 'pharmacist_pin' => '12345678'])
            ->assertSessionHasErrors('prescription_approval')->assertSessionMissing('_old_input.pharmacist_pin');
        $this->post('/pos/checkout', $payload + ['chemist_email' => $chemist->email, 'pharmacist_pin' => '0843719205'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.prescription_approved', 'user_id' => $chemist->id]);
    }

    /**
     * @return array<string, string>
     */
    private function payload(\DateTimeInterface $issuedAt): array
    {
        return [
            'payment_method' => 'debit',
            'prescription_number' => 'REC-100',
            'prescription_prescriber_name' => 'Dra. Ana Pérez',
            'prescription_prescriber_rut' => '9.876.543-2',
            'prescription_issued_at' => $issuedAt->format('Y-m-d'),
        ];
    }
}
