<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S8 - RNF-4 (interfaz en español), RNF-9 (mensajes claros y accionables)
 * y P6 (textos centralizados en lang/es/pos.php).
 */
final class I18nTest extends TestCase
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

    public function test_the_application_locale_is_spanish(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', app()->getLocale());
        $this->assertFileExists(lang_path('es/pos.php'));
    }

    public function test_checkout_shows_the_discount_reason_message_in_spanish(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id])->assertRedirect('/pos');

        $response = $this->from('/pos/checkout')->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '5',
            'discount_reason' => '',
        ]);

        $response->assertRedirect('/pos/checkout');
        $response->assertSessionHasErrors([
            'discount_reason' => 'El motivo es obligatorio.',
        ]);

        $this->get('/pos/checkout')->assertSee('El motivo es obligatorio.');
    }

    public function test_prescription_validation_messages_are_in_spanish(): void
    {
        $product = Product::factory()->prescription()->create(['price' => 1000, 'stock' => 10]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'prescription_number' => 'REC-100',
            'prescription_prescriber_name' => 'Dra. Soto',
            'prescription_prescriber_rut' => '',
            'prescription_issued_at' => '2026-11-01',
        ]);

        $response->assertSessionHasErrors(['prescription_prescriber_rut']);

        $message = (string) session('errors')->first('prescription_prescriber_rut');

        $this->assertStringContainsString('obligatorio', $message);
        $this->assertStringNotContainsString('field is required', $message);
        $this->assertStringNotContainsString('prescription', $message);
    }

    public function test_document_and_sale_statuses_are_translated_to_spanish(): void
    {
        $this->assertSame('Pendiente', DocumentStatus::pending->label());
        $this->assertSame('Emitida', DocumentStatus::issued->label());
        $this->assertSame('Rechazada', DocumentStatus::rejected->label());

        $this->assertSame('Confirmada', SaleStatus::confirmed->label());
        $this->assertSame('Anulada', SaleStatus::voided->label());
    }

    public function test_admin_views_show_translated_document_statuses(): void
    {
        $sale = $this->sell();
        $sale->document()->update([
            'folio' => null,
            'status' => DocumentStatus::pending,
            'issued_at' => null,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $history = $this->get('/admin/sales');

        $history->assertOk();
        $history->assertSee('Pendiente');
        $history->assertDontSee('Pending');

        $detail = $this->get("/admin/sales/{$sale->id}");

        $detail->assertOk();
        $detail->assertSee('Estado: Pendiente');
        $detail->assertDontSee('Estado: Pending');
    }

    public function test_views_have_no_hardcoded_ui_strings(): void
    {
        $views = [
            'pos/index.blade.php',
            'pos/checkout.blade.php',
            'pos/receipt.blade.php',
            'admin/sales/index.blade.php',
            'admin/sales/show.blade.php',
            'admin/documents/index.blade.php',
        ];

        $hardcoded = [
            'Buscar producto',
            'Carrito vacío',
            'Confirmar venta',
            'Documento simulado',
            'Historial de ventas',
            'Receta médica',
        ];

        foreach ($views as $view) {
            $source = (string) file_get_contents(resource_path('views/'.$view));

            foreach ($hardcoded as $text) {
                $this->assertStringNotContainsString(
                    '>'.$text,
                    $source,
                    "{$view} todavía contiene el texto hardcodeado «{$text}».",
                );
            }
        }
    }

    private function sell(): Sale
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

        $this->post('/pos/cart', ['product_id' => $product->id]);
        $this->post('/pos/checkout', ['payment_method' => 'debit'])
            ->assertSessionHasNoErrors();

        return Sale::query()->latest('id')->firstOrFail();
    }
}
