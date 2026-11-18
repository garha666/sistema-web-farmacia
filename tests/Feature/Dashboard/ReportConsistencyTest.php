<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T18 - RF-75, RF-76 y RF-77: los totales del panel coinciden con el historial
 * del mismo día, el día se calcula en la zona local sin caché desfasada y cada
 * panel sin actividad muestra ceros con un mensaje.
 */
final class ReportConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_panel_totals_match_the_sales_history_for_the_same_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 15, 30, 0, config('pos.timezone')));

        $admin = User::factory()->admin()->create();
        $this->print(10000, 'cash');
        $this->print(6000, 'debit');
        $this->print(4000, 'cash', SaleStatus::voided);

        $panel = $this->actingAs($admin)->get(route('dashboard.admin'));
        $panel->assertOk();
        $panel->assertSee('$16.000');
        $panel->assertSee('$4.000');

        $history = $this->actingAs($admin)->get(route('admin.sales.index', [
            'from' => '2026-11-18',
            'to' => '2026-11-18',
        ]));
        $history->assertOk();
        $history->assertSee('$10.000');
        $history->assertSee('$6.000');
        $history->assertSee('$4.000');
        $history->assertDontSee('$16.000');
    }

    public function test_the_chemist_panel_reports_the_same_daily_total_as_the_admin_panel(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 15, 30, 0, config('pos.timezone')));

        $admin = User::factory()->admin()->create();
        $chemist = User::factory()->quimico()->create();
        $this->print(12000, 'cash');
        $this->print(8000, 'debit');

        $this->actingAs($admin)->get(route('dashboard.admin'))
            ->assertOk()
            ->assertSee('$20.000');

        $this->actingAs($chemist)->get(route('dashboard.quimico'))
            ->assertOk()
            ->assertSee('$20.000')
            ->assertSee('$12.000')
            ->assertSee('$8.000');
    }

    public function test_the_panel_does_not_mix_days_around_midnight(): void
    {
        $admin = User::factory()->admin()->create();

        Carbon::setTestNow(Carbon::create(2026, 11, 18, 23, 59, 0, config('pos.timezone')));
        $this->print(5000, 'cash');

        $beforeMidnight = $this->actingAs($admin)->get(route('dashboard.admin'));
        $beforeMidnight->assertOk();
        $beforeMidnight->assertSee('$5.000');

        Carbon::setTestNow(Carbon::create(2026, 11, 19, 0, 1, 0, config('pos.timezone')));

        // La venta de ayer ya no cuenta hoy.
        $afterMidnight = $this->actingAs($admin)->get(route('dashboard.admin'));
        $afterMidnight->assertOk();
        $afterMidnight->assertSeeText(__('pos.panel.admin.no_sales'));
        $afterMidnight->assertDontSee('$5.000');

        $this->print(7000, 'cash');

        // Y la de hoy no arrastra la de ayer.
        $withToday = $this->actingAs($admin)->get(route('dashboard.admin'));
        $withToday->assertOk();
        $withToday->assertSee('$7.000');
        $withToday->assertDontSee('$12.000');
        $withToday->assertDontSee('$5.000');
    }

    public function test_the_panel_recomputes_on_every_load_without_stale_cache(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard.admin'))
            ->assertOk()
            ->assertSeeText(__('pos.panel.admin.no_sales'));

        $this->print(9000, 'cash');

        $this->actingAs($admin)->get(route('dashboard.admin'))
            ->assertOk()
            ->assertSee('$9.000');
    }

    public function test_every_panel_without_activity_shows_zeros_and_a_message(): void
    {
        $admin = User::factory()->admin()->create();
        $chemist = User::factory()->quimico()->create();
        $bodeguero = User::factory()->bodeguero()->create();
        $cashier = User::factory()->tecnico()->create();
        $this->openCashRegister($cashier);

        $adminPanel = $this->actingAs($admin)->get(route('dashboard.admin'));
        $adminPanel->assertOk();
        $adminPanel->assertSeeText(__('pos.panel.admin.no_sales'));
        $adminPanel->assertSeeText(__('pos.panel.admin.documents_empty'));

        $chemistPanel = $this->actingAs($chemist)->get(route('dashboard.quimico'));
        $chemistPanel->assertOk();
        $chemistPanel->assertSeeText(__('pos.panel.quimico.no_sales'));
        $chemistPanel->assertSeeText(__('pos.panel.quimico.authorizations_empty'));

        $cashierPanel = $this->actingAs($cashier)->get(route('dashboard.tecnico'));
        $cashierPanel->assertOk();
        $cashierPanel->assertSeeText(__('pos.cash_register.empty'));

        $warehousePanel = $this->actingAs($bodeguero)->get(route('dashboard.bodeguero'));
        $warehousePanel->assertOk();
        $warehousePanel->assertSeeText(__('pos.panel.bodeguero.replenishment_empty'));
        $warehousePanel->assertSeeText(__('pos.panel.bodeguero.movements_empty'));
        $this->assertMatchesRegularExpression('/id="inventory-active"[^>]*>\s*0\s*</', $warehousePanel->getContent());
        $this->assertMatchesRegularExpression('/id="inventory-movements"[^>]*>\s*0\s*</', $warehousePanel->getContent());
    }

    private function print(int $total, string $method, SaleStatus $status = SaleStatus::confirmed): Sale
    {
        $net = (int) round($total * 100 / 119);

        return Sale::query()->create([
            'token' => (string) Str::uuid(),
            'subtotal' => $total,
            'net' => $net,
            'iva' => $total - $net,
            'total' => $total,
            'payment_method' => $method,
            'status' => $status,
            'sold_at' => now(),
            'user_id' => User::factory()->create(['role' => 'tecnico'])->id,
        ]);
    }
}
