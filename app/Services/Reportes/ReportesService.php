<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Consultas agregadas de reportes (RF-92, RF-94, RF-95, RF-96).
 *
 * Solo lecturas: no modifica ventas, stock ni ningún dato transaccional.
 */
final class ReportesService
{
    /**
     * Ventas de un rango de fechas con totales y desglose por medio de pago.
     *
     * @return array{
     *     sales: LengthAwarePaginator,
     *     totalSales: int,
     *     totalAmount: int,
     *     byPaymentMethod: array<string, int>
     * }
     */
    public function ventasPorRango(\DateTimeImmutable $from, \DateTimeImmutable $to, int $perPage = 20): array
    {
        $fromStart = $from->setTime(0, 0, 0);
        $toEnd = $to->setTime(23, 59, 59);

        $query = Sale::query()
            ->with('user')
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->latest('sold_at')
            ->latest('id');

        $sales = $query->paginate($perPage);

        $totalAmount = Sale::query()
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->sum('total');

        $byPaymentMethod = Sale::query()
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->selectRaw('payment_method, count(*) as count, sum(total) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->payment_method->value => ['count' => (int) $row->count, 'total' => (int) $row->total]])
            ->toArray();

        return [
            'sales' => $sales,
            'totalSales' => (int) $sales->total(),
            'totalAmount' => (int) $totalAmount,
            'byPaymentMethod' => $byPaymentMethod,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    private function salesQuery(array $filters): Builder
    {
        $query = Sale::query()->with('user');

        if (! empty($filters['from'])) {
            $query->whereDate('sold_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('sold_at', '<=', $filters['to']);
        }

        return $query;
    }
}
