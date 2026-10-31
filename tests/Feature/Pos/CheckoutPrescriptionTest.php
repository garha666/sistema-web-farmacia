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

        $this->post('/pos/checkout', $this->payload(now()->subDays(30)))
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
            ->assertSessionHasErrors('prescription_reuse');

        $this->assertSame(0, Sale::query()->count());

        $supervisor = User::factory()->create(['role' => 'quimico']);

        $this->post('/pos/checkout', $payload + [
            'supervisor_email' => $supervisor->email,
            'supervisor_password' => 'password',
        ])->assertSessionHasNoErrors();

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

    private function controlledProduct(): Product
    {
        return Product::factory()->controlled()->create([
            'name' => 'Tramadol 50 mg',
            'price' => 5000,
            'stock' => 5,
        ]);
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
