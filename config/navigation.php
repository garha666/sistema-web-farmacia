<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Navegación de la administración
    |--------------------------------------------------------------------------
    |
    | Cada ítem declara la ruta con nombre, el patrón que la marca como activa,
    | la clave de traducción de su etiqueta y el trazo de su icono (viewBox
    | 0 0 24 24). El partial resources/views/partials/nav-links.blade.php lo
    | renderiza en la barra lateral y en la barra móvil del layout admin.
    |
    */

    'admin' => [
        [
            'route' => 'admin.sales.index',
            'active' => 'admin.sales.*',
            'label' => 'pos.nav.sales',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z',
        ],
        [
            'route' => 'admin.documents.index',
            'active' => 'admin.documents.*',
            'label' => 'pos.nav.documents',
            'icon' => 'M9 12h6m-6 4h6M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2',
        ],
        [
            'route' => 'admin.controlled-register.index',
            'active' => 'admin.controlled-register.*',
            'label' => 'pos.nav.controlled',
            'icon' => 'M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4',
        ],
        [
            'route' => 'admin.conventions.index',
            'active' => 'admin.conventions.*',
            'label' => 'pos.nav.conventions',
            'icon' => 'M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        ],
        [
            'route' => 'admin.audit.index',
            'active' => 'admin.audit.*',
            'label' => 'pos.nav.audit',
            'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navegación de los demás paneles (T11)
    |--------------------------------------------------------------------------
    |
    | Cada rol ve sólo su panel. Las secciones propias de cada panel se suman
    | en T18, cuando existan sus rutas.
    |
    */

    'quimico' => [
        [
            'route' => 'dashboard.quimico',
            'active' => 'dashboard.quimico',
            'label' => 'pos.nav.panel',
            'icon' => 'M3 12l9-9 9 9M5 10v10h14V10',
        ],
    ],

    'tecnico' => [
        [
            'route' => 'dashboard.tecnico',
            'active' => 'dashboard.tecnico',
            'label' => 'pos.nav.panel',
            'icon' => 'M3 12l9-9 9 9M5 10v10h14V10',
        ],
    ],

    'bodeguero' => [
        [
            'route' => 'dashboard.bodeguero',
            'active' => 'dashboard.bodeguero',
            'label' => 'pos.nav.panel',
            'icon' => 'M3 12l9-9 9 9M5 10v10h14V10',
        ],
    ],

];
