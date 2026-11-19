<?php

declare(strict_types=1);

namespace App\Pos\Policy;

/**
 * Quién puede abrir un comprobante (RF-25, T23).
 *
 * El comprobante muestra los datos del cliente y el detalle de la venta, así
 * que no basta con tener sesión en el POS: el cajero ve los suyos y quien puede
 * anular (Químico y Administrador) ve cualquiera, porque la anulación lo
 * devuelve a esa misma pantalla (RF-32).
 *
 * La clase es pura: recibe el rol ya resuelto, nunca el usuario ni la BD.
 */
final class ReceiptPolicy
{
    public function canView(bool $canSeeAnyReceipt, ?int $sellerId, ?int $viewerId): bool
    {
        if ($canSeeAnyReceipt) {
            return true;
        }

        return $sellerId !== null && $sellerId === $viewerId;
    }
}
