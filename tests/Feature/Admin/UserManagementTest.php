<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivating_an_operational_account_preserves_it_but_revokes_access(): void
    {
        $password = 'Local-Test-Password-29!';
        $chemist = User::factory()->quimico()->create(['password' => $password]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/users/'.$admin->id.'/deactivate')->assertForbidden();
        $this->patch('/admin/users/'.$chemist->id.'/deactivate')->assertRedirect('/admin/users')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $chemist->id, 'role' => 'quimico']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'auditable_id' => $chemist->id, 'action' => 'auth.user_deactivated']);
        $this->actingAs($chemist->fresh())->get(route('dashboard.quimico'))->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $chemist->email, 'password' => $password])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_create_an_operational_account_but_cannot_provision_an_admin(): void
    {
        $password = 'Local-Test-Password-29!';
        $payload = [
            'name' => 'Bodega de prueba',
            'email' => 'BODEGA-QA@farmacia.test',
            'password' => $password,
            'password_confirmation' => $password,
            'role' => 'admin',
        ];

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/users')->assertOk();
        $this->post('/admin/users', $payload)->assertSessionHasErrors('role');
        $this->post('/admin/users', array_replace($payload, ['role' => 'bodeguero']))
            ->assertRedirect('/admin/users')->assertSessionHasNoErrors();
        $this->get('/admin/users')->assertSee('bodega-qa@farmacia.test')->assertDontSee($password);

        auth()->logout();
        $this->post('/login', ['email' => 'bodega-qa@farmacia.test', 'password' => $password])
            ->assertRedirect(route('dashboard.bodeguero'));
        $this->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', $payload)->assertForbidden();
    }
}
