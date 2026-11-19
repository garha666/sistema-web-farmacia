<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\AuditLog;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use App\Pos\Value\PaymentBreakdown;
use App\Pos\Value\SalesDaySummary;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Única consulta agregada del día (RF-46, RF-52, RF-75).
 *
 * Los paneles del Administrador, del Químico y del Técnico toman sus totales de
 * aquí, de modo que el mismo día no pueda dar cifras distintas en cada panel.
 * Una sola consulta agrupa por estado y medio de pago.
 *
 * También expone las anulaciones registradas en el día (D-11): los eventos de
 * anulación con fecha de hoy, que el Administrador cuenta y el Químico lista.
 * Al salir de aquí, ambos paneles no pueden discrepar (RF-46, RF-54).
 */
final class SalesDayQuery
{
    public function forDay(DateTimeImmutable $day): SalesDaySummary
    {
        $rows = DB::table('sales')
            ->whereDate('sold_at', $day->format('Y-m-d'))
            ->selectRaw('status, payment_method, COUNT(*) AS count, COALESCE(SUM(total), 0) AS amount')
            ->groupBy('status', 'payment_method')
            ->get();

        $salesCount = 0;
        $totalSales = 0;
        $voidCount = 0;
        $totalVoids = 0;
        /** @var array<string, PaymentBreakdown> $breakdown */
        $breakdown = [];

        foreach ($rows as $row) {
            $count = (int) $row->count;
            $amount = (int) $row->amount;

            if ($row->status === SaleStatus::confirmed->value) {
                $salesCount += $count;
                $totalSales += $amount;
                $breakdown[$row->payment_method] = new PaymentBreakdown(
                    PaymentMethod::from($row->payment_method),
                    $amount,
                    $count,
                );

                continue;
            }

            $voidCount += $count;
            $totalVoids += $amount;
        }

        return new SalesDaySummary(
            $salesCount,
            $totalSales,
            $voidCount,
            $totalVoids,
            $this->orderedBreakdown($breakdown),
        );
    }

    /**
     * Anulaciones registradas en el día (D-11, RF-46 y RF-54).
     *
     * Son los eventos de anulación con fecha de hoy, sin importar cuándo se
     * vendió: la venta de ayer anulada hoy cuenta aquí. El Administrador usa la
     * cifra y el Químico la lista; al compartir la consulta, coinciden.
     *
     * @return Collection<int, AuditLog>
     */
    public function registeredVoidsOn(DateTimeImmutable $day): Collection
    {
        return AuditLog::query()
            ->with(['user', 'auditable'])
            ->where('action', 'sale.void')
            ->whereDate('created_at', $day->format('Y-m-d'))
            ->latest('id')
            ->get();
    }

    /**
     * Ordena el desglose como el enum de medios de pago, no por orden de
     * llegada de las filas.
     *
     * @param  array<string, PaymentBreakdown>  $breakdown
     * @return array<int, PaymentBreakdown>
     */
    private function orderedBreakdown(array $breakdown): array
    {
        $order = array_flip(array_map(
            fn (PaymentMethod $method): string => $method->value,
            PaymentMethod::cases(),
        ));

        uksort($breakdown, fn (string $a, string $b): int => $order[$a] <=> $order[$b]);

        return array_values($breakdown);
    }
}
