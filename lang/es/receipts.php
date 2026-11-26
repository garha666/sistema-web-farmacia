<?php

return [
    'warehouse_title' => 'Recepción de mercadería',
    'review_title' => 'Revisión de recepciones controladas',
    'register' => 'Registrar recepción',
    'history' => 'Historial de recepciones',
    'choose_product' => 'Selecciona un producto',
    'photo_hint' => 'JPEG, PNG o WebP, máximo 1,5 MiB. No incluyas datos personales.',
    'photo_failed' => 'No se pudo guardar la evidencia. Intenta nuevamente.',
    'download' => 'Descargar foto',
    'empty' => 'No hay recepciones para mostrar.',
    'difference' => 'Diferencia',
    'recorded_by' => 'Registró :name',
    'reviewed_by' => 'Revisó :name el :date',
    'approve' => 'Aprobar recepción',
    'reject' => 'Rechazar recepción',
    'expired' => 'El lote venció mientras esperaba revisión. No se puede aprobar.',
    'movement' => 'Recepción #:id · Lote :lot',
    'sale_lot' => 'Lote :lot · :quantity u.',
    'fields' => [
        'product_id' => 'Producto', 'lot' => 'Lote', 'expires_on' => 'Vencimiento',
        'expected_quantity' => 'Unidades esperadas', 'received_quantity' => 'Unidades recibidas',
        'photo' => 'Foto de respaldo', 'reason' => 'Motivo de la decisión', 'decision' => 'Decisión',
    ],
    'status' => ['received' => 'Ingresada', 'pending' => 'Pendiente de revisión', 'approved' => 'Aprobada', 'rejected' => 'Rechazada'],
    'saved' => [
        'received' => 'Recepción registrada. Se incorporaron las unidades recibidas al stock.',
        'pending' => 'Recepción registrada. Las unidades no están disponibles hasta que el químico apruebe.',
        'approved' => 'Recepción aprobada. Se incorporaron las unidades recibidas al stock.',
        'rejected' => 'Recepción rechazada. El stock no cambió.',
    ],
];
