<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Raíz del sitio (F0.2, ajustada en T11): `GET /` ya no lleva siempre a la caja.
 * Un invitado va al inicio de sesión y el cajero a su panel (RF-41).
 */
final class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_sends_a_guest_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_root_takes_the_cashier_to_its_panel(): void
    {
        $cashier = User::factory()->create(['role' => 'tecnico']);

        $this->actingAs($cashier)
            ->get('/')
            ->assertRedirect(route('dashboard.tecnico'));

        $this->assertSame('/panel/tecnico', route('dashboard.tecnico', absolute: false));
    }
}
