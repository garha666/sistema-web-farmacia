<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensajes de validación en español (RNF-4, RNF-9, P6)
|--------------------------------------------------------------------------
|
| Laravel 11 no incluye la carpeta lang/ por defecto. Este archivo traduce
| las reglas usadas por el POS y el panel de administración, y los nombres
| de los campos visibles para el usuario.
|
*/

return [

    'accepted' => 'El campo :attribute debe ser aceptado.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'enum' => 'El :attribute seleccionado no es válido.',
    'gt' => [
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
    ],
    'in' => 'El :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'file' => 'El archivo :attribute no debe superar los :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'unique' => 'El valor del campo :attribute ya está en uso.',

    'custom' => [],

    'attributes' => [
        'name' => 'nombre',
        'percent' => 'porcentaje de descuento',
        'valid_from' => 'vigencia desde',
        'valid_until' => 'vigencia hasta',
        'active' => 'estado activo',
        'from' => 'fecha desde',
        'to' => 'fecha hasta',
        'payment_method' => 'medio de pago',
        'status' => 'estado de venta',
        'document_status' => 'estado del documento',
        'user_id' => 'cajero',
        'convention_id' => 'convenio',
        'received_amount' => 'monto recibido',
        'customer_rut' => 'RUT del cliente',
        'product_id' => 'producto',
        'quantity' => 'cantidad',
        'discount_type' => 'tipo de descuento',
        'discount_value' => 'valor del descuento',
        'discount_reason' => 'motivo del descuento',
        'supervisor_email' => 'correo del supervisor',
        'supervisor_password' => 'contraseña del supervisor',
        'prescription_number' => 'número de receta',
        'prescription_prescriber_name' => 'nombre del prescriptor',
        'prescription_prescriber_rut' => 'RUT del prescriptor',
        'prescription_issued_at' => 'fecha de emisión de la receta',
        'reason' => 'motivo',
    ],

];
