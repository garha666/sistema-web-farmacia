<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Models\User;
use App\Pos\Policy\LandingPolicy;
use PHPUnit\Framework\TestCase;

/**
 * T11 - RF-41, RF-43 y RF-45: cada rol tiene un panel y sólo uno.
 *
 * La política es pura: recibe el rol como cadena y devuelve el nombre de la
 * ruta del panel. Un rol ausente o desconocido no tiene panel.
 */
final class LandingPolicyTest extends TestCase
{
    public function test_each_known_role_has_its_own_panel(): void
    {
        $policy = new LandingPolicy;

        $this->assertSame('dashboard.admin', $policy->panelRoute('admin'));
        $this->assertSame('dashboard.quimico', $policy->panelRoute('quimico'));
        $this->assertSame('dashboard.tecnico', $policy->panelRoute('tecnico'));
        $this->assertSame('dashboard.bodeguero', $policy->panelRoute('bodeguero'));
    }

    public function test_a_missing_role_has_no_panel(): void
    {
        $policy = new LandingPolicy;

        $this->assertNull($policy->panelRoute(null));
        $this->assertNull($policy->panelRoute(''));
    }

    public function test_an_unknown_role_has_no_panel(): void
    {
        $this->assertNull((new LandingPolicy)->panelRoute('operador'));
    }

    public function test_a_role_can_open_only_its_own_panel(): void
    {
        $policy = new LandingPolicy;

        $this->assertTrue($policy->canOpen('admin', 'dashboard.admin'));
        $this->assertTrue($policy->canOpen('bodeguero', 'dashboard.bodeguero'));

        $this->assertFalse($policy->canOpen('tecnico', 'dashboard.admin'));
        $this->assertFalse($policy->canOpen('quimico', 'dashboard.admin'));
        $this->assertFalse($policy->canOpen('bodeguero', 'dashboard.tecnico'));
    }

    /**
     * Guarda contra la deriva: si mañana se agrega o renombra una constante de
     * rol en User, la política tiene que reconocerla.
     */
    public function test_every_role_constant_of_the_user_model_is_known(): void
    {
        $policy = new LandingPolicy;

        $roles = [
            User::ROLE_ADMIN,
            User::ROLE_QUIMICO,
            User::ROLE_TECNICO,
            User::ROLE_BODEGUERO,
        ];

        foreach ($roles as $role) {
            $this->assertNotNull(
                $policy->panelRoute($role),
                "El rol {$role} no tiene panel en LandingPolicy.",
            );
        }
    }
}
