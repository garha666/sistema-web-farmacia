<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T28 — S7 del informe de seguridad: bitácora de eventos de seguridad.
 *
 * RF-84 (spec 003): los intentos fallidos de inicio de sesión y de
 * autorización de supervisor quedan registrados sin credenciales, para que el
 * Administrador pueda verlos en su panel. Nunca se guarda la contraseña.
 */
final class SecurityAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_login_is_recorded_without_credentials(): void
    {
        $user = User::factory()->tecnico()->create(['email' => 'cajero@farmacia.test']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'clave-incorrecta-123',
        ]);

        $log = AuditLog::query()->where('action', 'auth.login_failed')->sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertNull($log->auditable_type);
        $this->assertStringNotContainsString('clave-incorrecta-123', json_encode($log->getAttributes()));
    }

    public function test_a_failed_login_with_an_unknown_email_is_recorded_without_a_user(): void
    {
        $this->post('/login', [
            'email' => 'nadie@farmacia.test',
            'password' => 'clave-incorrecta-123',
        ]);

        $log = AuditLog::query()->where('action', 'auth.login_failed')->sole();

        $this->assertNull($log->user_id);
    }

    public function test_a_successful_login_is_not_recorded_as_a_failure(): void
    {
        $user = User::factory()->tecnico()->create(['email' => 'cajero@farmacia.test']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertSame(0, AuditLog::query()->where('action', 'auth.login_failed')->count());
    }

    public function test_a_failed_supervisor_authorization_is_recorded(): void
    {
        $cashier = User::factory()->tecnico()->create();
        $supervisor = User::factory()->quimico()->create(['email' => 'supervisor@farmacia.test']);

        $this->actingAs($cashier);
        $product = Product::factory()->create(['stock' => 10, 'price' => 10000]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $this->post('/pos/discount', [
            'discount_type' => 'percent',
            'discount_value' => '25',
            'discount_reason' => 'Convenio especial',
            'supervisor_email' => $supervisor->email,
            'supervisor_password' => 'clave-incorrecta-123',
        ]);

        $log = AuditLog::query()->where('action', 'auth.supervisor_failed')->sole();

        $this->assertSame($cashier->id, $log->user_id);
        $this->assertStringNotContainsString('clave-incorrecta-123', json_encode($log->getAttributes()));
    }

    public function test_the_admin_panel_shows_the_translated_security_events(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->tecnico()->create(['email' => 'cajero@farmacia.test']);

        $this->post('/login', [
            'email' => 'cajero@farmacia.test',
            'password' => 'clave-incorrecta-123',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.admin'))
            ->assertOk()
            ->assertSeeText(__('pos.panel.audit_action.auth.login_failed'));
    }
}
