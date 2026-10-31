<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ControlledRegisterEntry;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S3 - RF-10: libro de controlados consultable y exportable en CSV.
 */
final class ControlledRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_exports_the_controlled_register_as_csv(): void
    {
        $entry = $this->seedControlledEntry();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.controlled-register.export', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertDownload('libro-controlados-'.now()->format('Y-m').'.csv');

        $csv = $response->streamedContent();

        $rows = array_map(
            static fn (string $line): array => str_getcsv($line),
            preg_split('/\r\n|\n|\r/', trim($csv)),
        );

        $this->assertSame(['RUT', 'Folio', 'Fecha', 'Medicamento', 'Cantidad'], $rows[0]);
        $this->assertSame([
            '12.345.678-9',
            (string) $entry->folio,
            $entry->sold_at->format('d-m-Y'),
            'Tramadol 50 mg',
            '2',
        ], $rows[1]);
    }

    public function test_admin_sees_the_controlled_register(): void
    {
        $this->seedControlledEntry();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.controlled-register.index'));

        $response->assertOk();
        $response->assertSee('Libro de controlados');
        $response->assertSee('Tramadol 50 mg');
        $response->assertSee('REC-001');
        $response->assertSee('12.345.678-9');
    }

    public function test_only_the_admin_accesses_the_controlled_register(): void
    {
        $tecnico = User::factory()->create(['role' => 'tecnico']);

        $this->actingAs($tecnico)
            ->get(route('admin.controlled-register.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'quimico']))
            ->get(route('admin.controlled-register.export'))
            ->assertForbidden();
    }

    private function seedControlledEntry(): ControlledRegisterEntry
    {
        $cashier = User::factory()->create(['role' => 'tecnico']);
        $product = Product::factory()->controlled()->create([
            'name' => 'Tramadol 50 mg',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->actingAs($cashier)->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'customer_rut' => '12.345.678-9',
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();
        $line = $sale->lines()->firstOrFail();

        $prescription = Prescription::query()->create([
            'number' => 'REC-001',
            'prescriber_name' => 'Dra. Ana Pérez',
            'prescriber_rut' => '9.876.543-2',
            'issued_on' => now()->subDays(5)->toDateString(),
        ]);

        return ControlledRegisterEntry::query()->create([
            'prescription_id' => $prescription->id,
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'medication' => $line->name,
            'quantity' => $line->quantity,
            'customer_rut' => $sale->customer_rut,
            'folio' => $sale->document->folio,
            'sold_at' => $sale->sold_at,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
