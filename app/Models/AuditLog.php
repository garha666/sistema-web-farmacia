<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de acciones sensibles (RF-33, RF-36): autor, acción, motivo y fecha.
 */
final class AuditLog extends Model
{
    /** RF-84: intento fallido de inicio de sesión. */
    public const LOGIN_FAILED = 'auth.login_failed';

    /** RF-84: intento fallido de autorización de supervisor. */
    public const SUPERVISOR_FAILED = 'auth.supervisor_failed';

    /** RF-97, RNF-39: exportación de un reporte a CSV. */
    public const REPORT_EXPORTED = 'report.exported';

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'reason',
        'discount_type',
        'discount_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Línea autorizada cuando el subject es un descuento de línea (S12).
     *
     * @return BelongsTo<SaleLine, $this>
     */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'auditable_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * T28 - RF-84: registra un intento fallido de inicio de sesión.
     *
     * Se guarda la cuenta atacada (o nada si el correo no existe) y nunca la
     * contraseña ni el correo escrito, para no dejar credenciales en la
     * bitácora.
     */
    public static function recordLoginFailure(?User $user): self
    {
        return self::query()->create([
            'user_id' => $user?->getKey(),
            'action' => self::LOGIN_FAILED,
        ]);
    }

    /**
     * T40 - RF-97, RNF-39: registra la exportación de un reporte.
     *
     * Guarda el usuario, el tipo de reporte y el rango pedido (la marca de
     * tiempo es `created_at`). Un reporte sin filtro de fechas queda «sin rango».
     */
    public static function recordExport(?User $user, string $report, ?string $from, ?string $to): self
    {
        $range = $from === null && $to === null
            ? 'sin rango'
            : ($from ?? '…').' a '.($to ?? '…');

        return self::query()->create([
            'user_id' => $user?->getKey(),
            'action' => self::REPORT_EXPORTED,
            'reason' => "Reporte {$report}: {$range}",
        ]);
    }

    /**
     * T28 - RF-84: registra un intento fallido de autorización de supervisor.
     *
     * El autor es el cajero que lo intentó; la contraseña probada no se guarda.
     */
    public static function recordSupervisorFailure(?User $cashier): self
    {
        return self::query()->create([
            'user_id' => $cashier?->getKey(),
            'action' => self::SUPERVISOR_FAILED,
        ]);
    }
}
