<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Parámetros del Punto de Venta
    |--------------------------------------------------------------------------
    |
    | Valores verificados en specs/001-pos/spec.md. Cambiarlos exige actualizar
    | la spec activa (constitución, principio 2).
    |
    */

    'timezone' => 'America/Santiago',

    'iva_rate' => 19,

    'discount' => [
        'general_cap_percent' => 10,
    ],

    'prescription' => [
        'validity_days' => 30,
    ],

    'folio' => [
        'receipt_from' => 1,
        'receipt_to' => 50000,
        'invoice_from' => 50001,
        'invoice_to' => 100000,
    ],

    'issuer' => [
        'name' => 'Farmacia Grupo A SpA',
        'rut' => '76.543.210-K',
        'business' => 'Farmacia',
        'address' => 'Av. Siempre Viva 742',
        'commune' => 'Santiago',
    ],

];
