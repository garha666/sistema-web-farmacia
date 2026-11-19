<?php

declare(strict_types=1);

namespace App\Pos\Policy;

/**
 * Aterrizaje y acceso por rol (RF-41, RF-43, RF-45).
 *
 * Función pura: recibe el rol tal como lo guarda `users.role` y devuelve el
 * nombre de la ruta del panel que le corresponde. Un rol ausente o no
 * reconocido no tiene panel (caso límite 1), y un rol sólo puede abrir el
 * panel que esta tabla le asigna (RF-43).
 *
 * Los roles no se importan de `App\Models\User` para no arrastrar Eloquent al
 * dominio; el test de aterrizaje comprueba que cada constante del modelo sea
 * reconocida acá.
 */
final class LandingPolicy
{
    /** @var array<string, string> */
    private const PANELS = [
        'admin' => 'dashboard.admin',
        'quimico' => 'dashboard.quimico',
        'tecnico' => 'dashboard.tecnico',
        'bodeguero' => 'dashboard.bodeguero',
    ];

    public function panelRoute(?string $role): ?string
    {
        return self::PANELS[$role ?? ''] ?? null;
    }

    public function canOpen(string $role, string $panel): bool
    {
        return $this->panelRoute($role) === $panel;
    }

    /**
     * Grupo de navegación que corresponde al rol (RF-44, RNF-19).
     *
     * Las pantallas compartidas entre roles —auditoría, convenios y libro de
     * controlados— no declaran grupo propio. Si el layout cayera en «admin»,
     * el Químico o el Bodeguero verían secciones que su rol no puede abrir y
     * terminarían en un 403 al pulsarlas. El grupo se deriva del rol, así que
     * una pantalla nueva queda bien por defecto.
     */
    public function navGroupFor(?string $role): string
    {
        return $this->panelRoute($role) === null ? 'admin' : (string) $role;
    }
}
