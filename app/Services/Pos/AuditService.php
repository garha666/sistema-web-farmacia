<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de autorizaciones y acciones sensibles (RF-36).
 */
final class AuditService
{
    public function record(string $action, Model $subject, ?int $userId, ?string $reason = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'reason' => $reason,
        ]);
    }
}
