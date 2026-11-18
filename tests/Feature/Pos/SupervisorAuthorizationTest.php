<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * T24 - RF-83 y RNF-26 (spec 003): freno a la autorización de supervisor.
 *
 * Antes de esta tarea, un cajero autenticado podía probar contraseñas de
 * supervisor sin límite en las tres vías que exigen credenciales (cobro,
 * descuento global y descuento de línea), lo que hacía evadible el tope del
 * 10% de RF-16. Aquí se fija el freno: cinco intentos por minuto por cajero y
 * dirección de origen, y el contador se limpia al acertar.
 *
 * Casos límite 11 y 12 de la spec.
 */
final class SupervisorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->tecnico()->create();
        $this->supervisor = User::factory()->quimico()->create(['email' => 'supervisor@farmacia.test']);

        $this->actingAs($this->cashier);

        $product = Product::factory()->create(['stock' => 10, 'price' => 10000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);
    }

    /**
     * Pide un 25% de descuento: por encima del tope, así que exige supervisor.
     */
    private function askAboveTheCap(string $password): TestResponse
    {
        return $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '25',
            'discount_reason' => 'Convenio especial',
            'supervisor_email' => $this->supervisor->email,
            'supervisor_password' => $password,
        ]);
    }

    public function test_supervisor_credentials_are_throttled_after_repeated_failures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->askAboveTheCap('clave-equivocada')
                ->assertSessionHasErrors(['supervisor_email' => __('pos.errors.supervisor_invalid')]);
        }

        // Con la contraseña correcta, el intento ya está frenado.
        $this->askAboveTheCap('password')->assertSessionHasErrors('supervisor_email');

        $this->assertNotSame(
            __('pos.errors.supervisor_invalid'),
            (string) session('errors')->first('supervisor_email'),
            'El freno debe dar un mensaje distinto al de credenciales inválidas.',
        );

        $this->assertNull(session('pos.manual_discount'));
    }

    public function test_a_correct_authorization_clears_the_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->askAboveTheCap('clave-equivocada');
        }

        $this->askAboveTheCap('password')->assertSessionHasNoErrors();
        $this->assertNotNull(session('pos.manual_discount'));

        // El contador volvió a cero: el siguiente error es el normal, no el freno.
        $this->askAboveTheCap('clave-equivocada')
            ->assertSessionHasErrors(['supervisor_email' => __('pos.errors.supervisor_invalid')]);
    }

    public function test_the_throttle_does_not_change_the_session_or_the_role(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->askAboveTheCap('clave-equivocada');
        }

        $this->askAboveTheCap('password');

        // La autorización de supervisor no es un inicio de sesión (RF-83).
        $this->assertAuthenticatedAs($this->cashier);
    }

    public function test_a_cashier_without_supervisor_credentials_keeps_being_asked_for_them(): void
    {
        // Sin credenciales no hay intento que contar: el aviso del tope se
        // repite sin freno, porque no se está probando ninguna contraseña.
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->post('/pos/discount', [
                'discount_type' => 'percent',
                'discount_value' => '25',
                'discount_reason' => 'Convenio especial',
            ])->assertSessionHasErrors([
                'discount_supervisor' => __('pos.errors.discount_above_cap', [
                    'cap' => (int) config('pos.discount.general_cap_percent'),
                ]),
            ]);
        }

        $this->assertNull(session('pos.manual_discount'));
    }

    public function test_the_checkout_path_is_also_throttled(): void
    {
        // RF-83 cubre las tres vías: aquí la tercera, confirmar el cobro.
        $supervisor = $this->supervisor->email;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/pos/checkout', [
                'payment_method' => 'debit',
                'supervisor_email' => $supervisor,
                'supervisor_password' => 'clave-equivocada',
            ])->assertSessionHasErrors('supervisor_email');
        }

        $this->post('/pos/checkout', [
            'payment_method' => 'debit',
            'supervisor_email' => $supervisor,
            'supervisor_password' => 'password',
        ])->assertSessionHasErrors('supervisor_email');
    }
}
