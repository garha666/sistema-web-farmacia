<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T11 - RF-41, RF-42, RF-43 y RF-45: aterrizaje y acceso por rol.
 *
 * Casos límite 1 (usuario sin rol), 2 (Bodeguero en el POS) y 3 (Químico en el
 * panel del Administrador).
 */
final class RoleLandingTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private const PANELS = [
        'admin' => 'dashboard.admin',
        'quimico' => 'dashboard.quimico',
        'tecnico' => 'dashboard.tecnico',
        'bodeguero' => 'dashboard.bodeguero',
    ];

    public function test_each_role_lands_on_its_own_panel(): void
    {
        foreach (self::PANELS as $role => $panel) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get('/')
                ->assertRedirect(route($panel));

            $this->actingAs($user)
                ->get(route($panel))
                ->assertOk()
                ->assertSee(__("pos.panel.{$role}.title"));
        }
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_a_user_without_a_known_role_is_told_whom_to_ask(): void
    {
        $user = User::factory()->create(['role' => 'operador']);

        $this->actingAs($user)
            ->get('/')
            ->assertStatus(403)
            ->assertSee(__('pos.panel.unsupported.message'));
    }

    public function test_a_bodeguero_cannot_open_the_cash_screen_nor_the_sales_history(): void
    {
        $bodeguero = User::factory()->create(['role' => 'bodeguero']);

        $this->actingAs($bodeguero)->get('/pos')->assertForbidden();
        $this->actingAs($bodeguero)->get('/admin/sales')->assertForbidden();
        $this->actingAs($bodeguero)->get('/panel/admin')->assertForbidden();
    }

    public function test_a_chemist_cannot_open_the_admin_panel(): void
    {
        $chemist = User::factory()->create(['role' => 'quimico']);

        $this->actingAs($chemist)
            ->get(route('dashboard.admin'))
            ->assertForbidden()
            ->assertDontSee(__('pos.panel.admin.title'));
    }

    public function test_the_pos_is_open_to_the_cashier_supervisor_and_administrator(): void
    {
        foreach (['tecnico', 'quimico', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/pos')->assertOk();
        }
    }

    public function test_the_user_seeder_creates_one_user_per_role(): void
    {
        $this->seed(UserSeeder::class);

        $roles = User::query()->orderBy('role')->pluck('role')->all();

        $this->assertSame(['admin', 'bodeguero', 'quimico', 'tecnico'], $roles);
    }

    public function test_the_demo_user_seeder_refuses_production_without_writing_users(): void
    {
        $this->app->instance('env', 'production');

        try {
            $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])->run();
            $this->fail('Demo user seeding should refuse production.');
        } catch (\LogicException $exception) {
            $this->assertSame('Demo users can only be seeded in local and testing environments.', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
