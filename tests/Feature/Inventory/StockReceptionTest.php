<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StockReceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_lots_remain_unavailable_until_approved_then_sell_and_restore_by_expiry(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['stock' => 0, 'sale_category' => 'controlled']);
        $warehouse = User::factory()->create(['role' => 'bodeguero']);
        $chemist = User::factory()->create(['role' => 'quimico']);

        $this->actingAs($warehouse);
        foreach (['LATE' => 30, 'EARLY' => 10] as $lot => $days) {
            $this->post('/inventory/receipts', array_replace($this->delivery($product), [
                'lot' => $lot,
                'received_quantity' => 2,
                'expires_on' => now(config('pos.timezone'))->addDays($days)->toDateString(),
            ]))->assertSessionHasNoErrors();
        }
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(2, StockReceipt::query()->where('status', 'pending')->count());

        $this->actingAs($chemist);
        foreach (StockReceipt::query()->orderBy('id')->get() as $receipt) {
            $this->patch('/inventory/receipts/'.$receipt->id, [
                'decision' => 'approved', 'reason' => 'Lote y evidencia revisados',
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(4, $product->fresh()->stock);

        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
        $this->post('/cash-registers', ['opening_amount' => 0])->assertSessionHasNoErrors();
        $this->post('/pos/cart', ['product_id' => $product->id])->assertSessionHasNoErrors();
        $this->patch(route('pos.cart.update', $product), ['quantity' => 3])->assertSessionHasNoErrors();
        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'prescription_number' => 'REC-100',
            ...$this->pharmacistCredentials(),
            'prescription_prescriber_name' => 'Dra. Ana Pérez',
            'prescription_prescriber_rut' => '9.876.543-2',
            'prescription_issued_at' => now(config('pos.timezone'))->toDateString(),
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'EARLY', 'remaining_quantity' => 0]);
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'LATE', 'remaining_quantity' => 1]);
        $this->assertSame(1, $product->fresh()->stock);
        $this->actingAs($chemist)->post(route('pos.sales.void', $sale), [
            'reason' => 'Anulación de prueba',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'EARLY', 'remaining_quantity' => 2]);
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'LATE', 'remaining_quantity' => 2]);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_sale_uses_the_earliest_expiry_and_records_the_split(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['stock' => 1, 'sale_category' => 'free']);
        $warehouse = User::factory()->create(['role' => 'bodeguero']);
        $this->actingAs($warehouse);
        foreach (['LATE' => 30, 'EARLY' => 10] as $lot => $days) {
            $this->post('/inventory/receipts', array_replace($this->delivery($product), [
                'lot' => $lot, 'received_quantity' => 2,
                'expires_on' => now(config('pos.timezone'))->addDays($days)->toDateString(),
            ]))->assertSessionHasNoErrors();
        }
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
        $this->post('/cash-registers', ['opening_amount' => 0])->assertSessionHasNoErrors();
        $this->post('/pos/cart', ['product_id' => $product->id])->assertSessionHasNoErrors();
        $this->patch(route('pos.cart.update', $product), ['quantity' => 3])->assertSessionHasNoErrors();
        $this->post('/pos/checkout', ['payment_method' => 'debit'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'EARLY', 'remaining_quantity' => 0]);
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'LATE', 'remaining_quantity' => 1]);
        $this->assertSame(2, $product->fresh()->stock);
        $sale = Sale::query()->sole();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.sales.show', $sale))
            ->assertOk()->assertSee('Lote EARLY · 2 u.')->assertSee('Lote LATE · 1 u.');

        $this->travel(11)->days();
        $this->post(route('pos.sales.void', $sale), ['reason' => 'Anulación de prueba'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'EARLY', 'remaining_quantity' => 2]);
        $this->assertDatabaseHas('stock_receipts', ['lot' => 'LATE', 'remaining_quantity' => 2]);
        $this->assertSame(5, $product->fresh()->stock);
        $this->actingAs(User::factory()->create(['role' => 'tecnico']))->get('/pos')
            ->assertSee(__('pos.cash.stock', ['stock' => 3]));
    }

    public function test_warehouse_records_evidence_and_only_adds_the_units_received(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['stock' => 10, 'sale_category' => 'free']);
        $warehouse = User::factory()->create(['role' => 'bodeguero']);

        $this->actingAs($warehouse)->post('/inventory/receipts', array_replace($this->delivery($product), [
            'expected_quantity' => '20',
            'received_quantity' => '18',
        ]))
            ->assertRedirect('/inventory/receipts')->assertSessionHasNoErrors();
        $this->get('/inventory/receipts')->assertOk()->assertSee('DEMO-01')->assertSee('−2');
        $this->assertSame(28, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'user_id' => $warehouse->id, 'quantity' => 18]);
    }

    private function delivery(Product $product): array
    {
        return [
            'product_id' => $product->id,
            'lot' => 'DEMO-01',
            'expires_on' => now(config('pos.timezone'))->addYear()->toDateString(),
            'expected_quantity' => 20,
            'received_quantity' => 18,
            'photo' => UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aJosAAAAASUVORK5CYII=')),
        ];
    }

    public function test_only_a_chemist_can_decide_and_approval_is_applied_once(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['stock' => 10, 'sale_category' => 'controlled']);
        $warehouse = User::factory()->create(['role' => 'bodeguero']);
        $chemist = User::factory()->create(['role' => 'quimico']);
        $this->actingAs($warehouse)->post('/inventory/receipts', $this->delivery($product))->assertSessionHasNoErrors();
        $this->assertSame(10, $product->fresh()->stock);
        $receipt = StockReceipt::query()->sole();
        Storage::disk('local')->assertExists($receipt->photo_path);
        $this->assertStringStartsWith('private/stock-receipts/', $receipt->photo_path);
        $url = '/inventory/receipts/'.$receipt->id;
        $decision = ['decision' => 'approved', 'reason' => 'Lote y fotografía revisados'];
        $this->patch($url, $decision)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($url.'/photo')->assertForbidden();
        $this->actingAs($chemist)->get($url.'/photo')->assertOk();
        $this->patch($url, $decision)->assertSessionHasNoErrors();
        $this->patch($url, $decision)->assertConflict();
        $this->assertSame(28, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_receipts', ['id' => $receipt->id, 'reviewer_id' => $chemist->id, 'status' => 'approved']);
    }

    public function test_a_receipt_that_expires_in_review_can_be_rejected_without_adding_stock(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['stock' => 10, 'sale_category' => 'controlled']);
        $warehouse = User::factory()->create(['role' => 'bodeguero']);
        $delivery = array_replace($this->delivery($product), ['expires_on' => now(config('pos.timezone'))->toDateString()]);
        $this->actingAs($warehouse)->post('/inventory/receipts', $delivery)->assertSessionHasNoErrors();
        $receipt = StockReceipt::query()->sole();
        $url = '/inventory/receipts/'.$receipt->id;

        $this->travel(1)->day();
        $chemist = User::factory()->create(['role' => 'quimico']);
        $this->actingAs($chemist)->from('/inventory/receipts')->patch($url, ['decision' => 'approved', 'reason' => 'Revisado'])
            ->assertSessionHasErrors('review');
        $this->patch($url, ['decision' => 'rejected', 'reason' => 'Lote vencido'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $receipt->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
    }
}
