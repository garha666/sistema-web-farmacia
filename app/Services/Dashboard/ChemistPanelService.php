<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\AuditLog;
use App\Models\User;
use App\Pos\Value\SalesDaySummary;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Datos del panel del Químico (RF-52 a RF-55).
 *
 * El resumen del día sale de `SalesDayQuery`, compartido con los demás
 * paneles. Las autorizaciones son las del usuario en sesión y las anulaciones
 * las de todo el día; ambas se acotan a la fecha local del negocio.
 */
final class ChemistPanelService
{
    /**
     * Acciones auditadas que cuentan como autorización del supervisor (RF-53).
     */
    private const AUTHORIZATION_ACTIONS = [
        'sale.discount_authorized',
        'sale.line_discount_authorized',
        'sale.prescription_reuse',
    ];

    public function __construct(
        private readonly SalesDayQuery $sales,
    ) {}

    /**
     * @return array{
     *     summary: SalesDaySummary,
     *     authorizations: Collection<int, AuditLog>,
     *     voids: Collection<int, AuditLog>
     * }
     */
    public function for(User $chemist, ?DateTimeImmutable $day = null): array
    {
        $today = $day ?? CarbonImmutable::now(config('pos.timezone'));
        $date = $today->format('Y-m-d');

        return [
            'summary' => $this->sales->forDay($today),
            'authorizations' => $this->authorizations($chemist, $date),
            'voids' => $this->voids($date),
        ];
    }

    /**
     * Autorizaciones de hoy otorgadas por el usuario en sesión (RF-53).
     *
     * @return Collection<int, AuditLog>
     */
    private function authorizations(User $chemist, string $date): Collection
    {
        return AuditLog::query()
            ->with(['auditable', 'saleLine.sale'])
            ->where('user_id', $chemist->getKey())
            ->whereIn('action', self::AUTHORIZATION_ACTIONS)
            ->whereDate('created_at', $date)
            ->latest('id')
            ->get();
    }

    /**
     * Anulaciones registradas hoy, con su motivo y su autor (RF-54).
     *
     * @return Collection<int, AuditLog>
     */
    private function voids(string $date): Collection
    {
        return AuditLog::query()
            ->with(['user', 'auditable'])
            ->where('action', 'sale.void')
            ->whereDate('created_at', $date)
            ->latest('id')
            ->get();
    }
}
