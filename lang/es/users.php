<?php

return [
    'title' => 'Gestión de usuarios',
    'create' => 'Crear cuenta operativa',
    'directory' => 'Cuentas registradas',
    'created' => 'Cuenta creada. Ya puede ingresar al panel de su rol.',
    'deactivate' => 'Desactivar cuenta',
    'deactivated' => 'Cuenta desactivada. Su historial se conserva.',
    'inactive' => 'Cuenta desactivada',
    'cash_open' => 'Este técnico tiene caja abierta. Debe cerrar el turno antes de desactivar su cuenta.',
    'hint' => 'Usa una contraseña única de al menos 12 caracteres.',
    'password_bytes' => 'La contraseña no puede superar 72 bytes ni contener caracteres nulos.',
    'fields' => [
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'password_confirmation' => 'Confirmar contraseña',
        'role' => 'Rol',
    ],
    'roles' => [
        'tecnico' => 'Técnico',
        'bodeguero' => 'Bodeguero',
        'quimico' => 'Químico supervisor',
        'admin' => 'Administrador',
    ],
];
