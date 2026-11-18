<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T19 - spec 003: pantalla de inicio de sesión, autenticación y cierre de sesión.
 *
 * RF-78 (entrar y aterrizar en el panel del rol), RF-79 (un solo mensaje para
 * credenciales inválidas), RF-80 (el invitado siempre cae en la pantalla de
 * acceso), RF-81 (cerrar sesión invalida la sesión) y RF-82 (freno por fuerza
 * bruta). RNF-23 (la contraseña no se filtra), RNF-24 (la sesión se regenera).
 *
 * Casos límite 1 a 10 de la spec 003.
 */
final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_screen_is_reachable_and_asks_for_credentials(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSeeText(__('pos.login.submit'));
    }

    public function test_a_guest_is_sent_to_the_login_screen_instead_of_an_error(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/pos')->assertRedirect(route('login'));
        $this->get('/panel/admin')->assertRedirect(route('login'));
        $this->get('/cash-registers/close')->assertRedirect(route('login'));
    }

    public function test_each_role_lands_on_its_own_panel(): void
    {
        $roles = [
            'admin' => 'dashboard.admin',
            'quimico' => 'dashboard.quimico',
            'tecnico' => 'dashboard.tecnico',
            'bodeguero' => 'dashboard.bodeguero',
        ];

        foreach ($roles as $role => $panel) {
            $user = User::factory()->create(['role' => $role]);

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect(route($panel));

            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'));
        }
    }

    public function test_a_user_without_a_known_role_does_not_land_on_a_panel(): void
    {
        $user = User::factory()->create(['role' => 'pasante']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard.home'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard.home'))->assertForbidden();
    }

    public function test_invalid_credentials_do_not_reveal_whether_the_email_exists(): void
    {
        $user = User::factory()->create(['email' => 'tecnico@farmacia.test']);

        $unknownEmail = $this->post(route('login.store'), [
            'email' => 'nadie@farmacia.test',
            'password' => 'password',
        ]);

        $wrongPassword = $this->post(route('login.store'), [
            'email' => 'tecnico@farmacia.test',
            'password' => 'clave-equivocada',
        ]);

        $unknownEmail->assertSessionHasErrors(['email' => __('pos.login.error.invalid')]);
        $wrongPassword->assertSessionHasErrors(['email' => __('pos.login.error.invalid')]);
        $this->assertGuest();
    }

    public function test_the_email_and_the_password_are_required(): void
    {
        $this->post(route('login.store'), [])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_too_many_failed_attempts_are_throttled_even_with_the_right_password(): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'clave-equivocada',
            ])->assertSessionHasErrors('email');
        }

        $blocked = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $blocked->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_successful_login_clears_the_failed_attempts(): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'clave-equivocada',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard.tecnico'));

        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        // El contador volvió a cero: el siguiente error es el normal, no el freno.
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'clave-equivocada',
        ])->assertSessionHasErrors(['email' => __('pos.login.error.invalid')]);
    }

    public function test_an_authenticated_user_reaches_its_panel_from_the_login_screen(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard.admin'));
    }

    public function test_closing_the_session_requires_credentials_again(): void
    {
        $user = User::factory()->tecnico()->create();

        $this->actingAs($user);
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get('/panel/tecnico')->assertRedirect(route('login'));
    }

    public function test_a_panel_offers_the_logout_action(): void
    {
        $user = User::factory()->tecnico()->create();

        $this->actingAs($user)
            ->get(route('dashboard.tecnico'))
            ->assertOk()
            ->assertSee(route('logout'), false)
            ->assertSeeText(__('pos.login.logout'));
    }

    public function test_the_session_identifier_changes_after_login(): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);

        $this->get(route('login'));
        $guestSession = session()->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($guestSession, session()->getId());
    }

    public function test_the_typed_password_is_never_rendered_back(): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'clave-secreta-123',
        ])->assertSessionHasErrors('email');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('clave-secreta-123');
    }
}
