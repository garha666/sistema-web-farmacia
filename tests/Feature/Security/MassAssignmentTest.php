<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T27 — S3 del informe de seguridad: `role` no debe asignarse en masa.
 *
 * Si `role` está en `$fillable`, cualquier alta o edición futura que pase el
 * request completo a `User::create()` o `update()` permite que un cajero se
 * convierta en administrador. Hoy no existe ese endpoint, pero el hueco queda
 * latente. El rol se provisiona de forma explícita.
 */
final class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_not_mass_assignable(): void
    {
        $this->assertNotContains('role', (new User)->getFillable());
    }

    public function test_creating_a_user_with_a_role_in_the_payload_ignores_it(): void
    {
        $user = User::query()->create([
            'name' => 'Intruso',
            'email' => 'intruso@farmacia.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertSame(User::ROLE_TECNICO, $user->fresh()?->role);
    }

    public function test_the_factory_still_provisions_the_role_explicitly(): void
    {
        $this->assertSame(User::ROLE_ADMIN, User::factory()->admin()->create()->role);
        $this->assertSame(User::ROLE_QUIMICO, User::factory()->quimico()->create()->role);
        $this->assertSame(User::ROLE_BODEGUERO, User::factory()->bodeguero()->create()->role);
        $this->assertSame(User::ROLE_TECNICO, User::factory()->create()->role);
        $this->assertSame(User::ROLE_QUIMICO, User::factory()->create(['role' => User::ROLE_QUIMICO])->role);
    }

    public function test_the_explicit_role_assignment_is_available(): void
    {
        $user = User::factory()->create();

        $user->assignRole(User::ROLE_ADMIN);

        $this->assertSame(User::ROLE_ADMIN, $user->fresh()?->role);
    }
}
