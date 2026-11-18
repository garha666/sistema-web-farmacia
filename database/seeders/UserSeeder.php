<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * T11 - RF-41: un usuario por rol para la demo manual (criterio de
 * finalización 4 de la spec 002).
 *
 * Es idempotente: se puede correr varias veces sin duplicar cuentas. La
 * contraseña es la de desarrollo (`password`) y el alta de usuarios sigue
 * fuera de alcance, como declara la spec.
 */
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrador de farmacia', 'email' => 'admin@farmacia.test', 'role' => User::ROLE_ADMIN],
            ['name' => 'Químico supervisor', 'email' => 'quimico@farmacia.test', 'role' => User::ROLE_QUIMICO],
            ['name' => 'Cajero de turno', 'email' => 'tecnico@farmacia.test', 'role' => User::ROLE_TECNICO],
            ['name' => 'Bodeguero', 'email' => 'bodeguero@farmacia.test', 'role' => User::ROLE_BODEGUERO],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                $user + ['password' => 'password'],
            );
        }
    }
}
