<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\SaleLine;
use App\Pos\Enum\DiscountType;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de autorizaciones y acciones sensibles (RF-36).
 */
final class AuditService
{
    public function record(
        string $action,
        Model $subject,
        ?int $userId,
        ?string $reason = null,
        ?DiscountType $discountType = null,
        ?int $discountValue = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'reason' => $reason,
            'discount_type' => $discountType?->value,
            'discount_value' => $discountValue,
        ]);
    }

    /**
     * Autorización de un descuento de línea (S12, RF-36).
     *
     * El subject es la `SaleLine` real de la venta ya confirmada, de modo que
     * el detalle de venta (RF-38) y la bitácora pueden mostrar el producto.
     */
    public function recordLineDiscountAuthorization(
        SaleLine $line,
        int $authorizedBy,
        DiscountType $type,
        int $discountValue,
        string $reason,
    ): AuditLog {
        return $this->record(
            action: 'sale.line_discount_authorized',
            subject: $line,
            userId: $authorizedBy,
            reason: $reason,
            discountType: $type,
            discountValue: $discountValue,
        );
    }
}
