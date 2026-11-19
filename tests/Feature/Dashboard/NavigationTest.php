<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * T18 - RF-44 y RNF-19: cada rol ve sólo sus secciones, toda opción visible
 * responde 200 y ninguna termina en un rechazo por permisos.
 */
final class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private const PANELS = [
        'admin' => 'dashboard.admin',
        'quimico' => 'dashboard.quimico',
        'tecnico' => 'dashboard.tecnico',
        'bodeguero' => 'dashboard.bodeguero',
    ];

    /**
     * Rutas que no deben aparecer en el HTML del panel de cada rol.
     *
     * @var array<string, array<int, string>>
     */
    private const FORBIDDEN = [
        'admin' => ['dashboard.quimico', 'dashboard.tecnico', 'dashboard.bodeguero'],
        'quimico' => ['dashboard.admin', 'dashboard.tecnico', 'admin.sales.index', 'admin.documents.index', 'inventory.movements.index'],
        'tecnico' => ['dashboard.admin', 'dashboard.quimico', 'admin.sales.index', 'inventory.products.index', 'inventory.movements.index'],
        'bodeguero' => ['dashboard.admin', 'dashboard.quimico', 'admin.sales.index', 'admin.documents.index', 'pos.index'],
    ];

    public function test_every_role_has_a_non_empty_navigation_with_valid_items(): void
    {
        foreach (array_keys(self::PANELS) as $role) {
            $items = config('navigation.'.$role);

            $this->assertIsArray($items, "El rol {$role} no tiene navegación.");
            $this->assertNotEmpty($items, "El rol {$role} tiene la navegación vacía.");

            foreach ($items as $item) {
                $this->assertArrayHasKey('route', $item);
                $this->assertArrayHasKey('active', $item);
                $this->assertArrayHasKey('label', $item);
                $this->assertArrayHasKey('icon', $item);
                $this->assertTrue(Route::has($item['route']), "La ruta {$item['route']} no existe.");
                $this->assertNotSame($item['label'], __($item['label']), "La etiqueta {$item['label']} no está traducida.");
                $this->assertStringStartsWith('pos.nav.', $item['label']);
            }
        }
    }

    public function test_every_visible_link_of_each_role_responds_without_a_permission_error(): void
    {
        foreach (self::PANELS as $role => $panel) {
            $user = User::factory()->create(['role' => $role]);

            foreach (config('navigation.'.$role) as $item) {
                $this->actingAs($user)
                    ->get(route($item['route']))
                    ->assertOk();
            }

            // El acceso al POS del layout sólo aplica a los roles que lo usan.
            if ($role !== 'bodeguero') {
                $this->actingAs($user)->get(route('pos.index'))->assertOk();
            }
        }
    }

    public function test_each_role_sees_its_own_sections_and_none_of_the_others(): void
    {
        foreach (self::PANELS as $role => $panel) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($panel));

            $response->assertOk();

            // Toda ruta de su menú aparece en la página.
            foreach (config('navigation.'.$role) as $item) {
                $response->assertSee(route($item['route']), false);
            }

            // Y ninguna sección ajena.
            foreach (self::FORBIDDEN[$role] as $route) {
                $response->assertDontSee(route($route), false);
            }
        }
    }

    /**
     * RF-44 / RNF-19: las páginas compartidas entre roles no pueden caer en el
     * grupo del Administrador. Si lo hicieran, el Químico vería en su menú
     * secciones que su rol tiene prohibidas y al abrirlas recibiría 403.
     */
    public function test_shared_pages_keep_the_navigation_of_the_signed_in_role(): void
    {
        $chemist = User::factory()->quimico()->create();

        foreach (['admin.audit.index', 'admin.conventions.index'] as $route) {
            $response = $this->actingAs($chemist)->get(route($route));

            $response->assertOk();
            $response->assertSee('aria-label="'.__('pos.panel.group.quimico').'"', false);
            $response->assertDontSee('aria-label="'.__('pos.panel.group.admin').'"', false);

            foreach (['admin.sales.index', 'admin.documents.index', 'inventory.products.index', 'admin.controlled-register.index'] as $forbidden) {
                $response->assertDontSee(route($forbidden), false);
            }
        }

        $warehouse = User::factory()->bodeguero()->create();
        $response = $this->actingAs($warehouse)->get(route('admin.controlled-register.index'));

        $response->assertOk();
        $response->assertSee('aria-label="'.__('pos.panel.group.bodeguero').'"', false);
        $response->assertDontSee('aria-label="'.__('pos.panel.group.admin').'"', false);
        $response->assertDontSee(route('admin.sales.index'), false);
        $response->assertDontSee(route('admin.conventions.index'), false);
    }

    public function test_the_navigation_renders_once_per_variant_and_marks_the_current_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($user)->get(route('admin.audit.index'))->getContent();

        foreach (config('navigation.admin') as $item) {
            // Barra lateral (escritorio) y barra horizontal (móvil).
            $this->assertSame(2, substr_count($html, 'href="'.route($item['route']).'"'), "El enlace {$item['route']} no aparece dos veces.");
        }

        $this->assertSame(2, substr_count($html, 'aria-current="page"'));
    }
}
