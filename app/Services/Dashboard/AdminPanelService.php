<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Convention;
use App\Models\Document;
use App\Models\Product;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Value\SalesDaySummary;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Datos del panel del Administrador (RF-47 a RF-51).
 *
 * El resumen de ventas sale de `SalesDayQuery`, compartido con los demás
 * paneles. Aquí sólo se consultan los bloques propios del Administrador, todos
 * acotados al día en la zona horaria del negocio.
 */
final class AdminPanelService
{
    public function __construct(
        private readonly SalesDayQuery $sales,
    ) {}

    /**
     * @return array{
     *     summary: SalesDaySummary,
     *     documents: Collection<int, Document>,
     *     inventory: array{belowMinimum: int, outOfStock: int},
     *     audit: Collection<int, AuditLog>,
     *     conventions: array{expiring: Collection<int, Convention>, expired: Collection<int, Convention>},
     *     cashCuts: Collection<int, CashRegister>
     * }
     */
    public function for(?DateTimeImmutable $day = null): array
    {
        $today = $day ?? CarbonImmutable::now(config('pos.timezone'));

        return [
            'summary' => $this->sales->forDay($today),
            'documents' => $this->documents(),
            'inventory' => $this->inventory(),
            'audit' => $this->audit(),
            'conventions' => $this->conventions($today),
            'cashCuts' => $this->cashCuts($today),
        ];
    }

    /**
     * Documentos pendientes o rechazados con reintento en línea (RF-47).
     *
     * @return Collection<int, Document>
     */
    private function documents(): Collection
    {
        return Document::query()
            ->with('sale')
            ->whereIn('status', [DocumentStatus::pending, DocumentStatus::rejected])
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /**
     * Productos vigentes bajo el mínimo y sin existencia (RF-48).
     *
     * @return array{belowMinimum: int, outOfStock: int}
     */
    private function inventory(): array
    {
        return [
            'belowMinimum' => Product::query()->active()->belowMinimum()->count(),
            'outOfStock' => Product::query()->active()->outOfStock()->count(),
        ];
    }

    /**
     * Últimos movimientos de auditoría (RF-49).
     *
     * @return Collection<int, AuditLog>
     */
    private function audit(): Collection
    {
        return AuditLog::query()
            ->with(['user', 'saleLine'])
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /**
     * Convenios activos próximos a vencer y convenios ya vencidos (RF-50).
     *
     * @return array{expiring: Collection<int, Convention>, expired: Collection<int, Convention>}
     */
    private function conventions(DateTimeImmutable $today): array
    {
        $day = $today->format('Y-m-d');
        $limit = $today
            ->modify('+'.max(0, (int) config('pos.convention.expiring_days')).' days')
            ->format('Y-m-d');

        return [
            'expiring' => Convention::query()
                ->where('active', true)
                ->whereDate('valid_until', '>=', $day)
                ->whereDate('valid_until', '<=', $limit)
                ->orderBy('valid_until')
                ->orderBy('name')
                ->get(),
            'expired' => Convention::query()
                ->whereDate('valid_until', '<', $day)
                ->orderBy('valid_until')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Turnos de caja abiertos y cerrados abiertos durante el día (RF-51).
     *
     * @return Collection<int, CashRegister>
     */
    private function cashCuts(DateTimeImmutable $today): Collection
    {
        return CashRegister::query()
            ->with('user')
            ->whereDate('opened_at', $today->format('Y-m-d'))
            ->orderBy('opened_at')
            ->get();
    }
}
