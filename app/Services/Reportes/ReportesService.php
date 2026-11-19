<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
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
     * Arqueo de cada turno de caja con sus ventas y efectivo esperado.
     *
     * Para turnos abiertos, el efectivo esperado se calcula en tiempo real.
     * Para turnos cerrados, se usan los valores grabados al cerrar.
     *
     * @param  int|null  $cashierId  cuando se pide, solo los turnos de ese cajero
     * @return array<int, array>
     */
    public function arqueoPorTurno(?int $cashierId = null): array
    {
        $registersQuery = CashRegister::query()->with('user');

        if ($cashierId !== null) {
            $registersQuery->where('user_id', $cashierId);
        }

        $registers = $registersQuery->latest('opened_at')->get()->keyBy('id');

        $registerIds = $registers->keys()->toArray();

        if ($registerIds === []) {
            return [];
        }

        $sales = Sale::query()
            ->whereIn('cash_register_id', $registerIds)
            ->get();

        $salesByRegister = [];
        /** @var Sale $sale */
        foreach ($sales as $sale) {
            $salesByRegister[$sale->cash_register_id][] = $sale;
        }

        $result = [];

        foreach ($registers as $register) {
            $sales = $salesByRegister[$register->id] ?? [];

            $totalSales = 0;
            $cashSales = 0;
            $cashVoids = 0;
            $saleCount = 0;
            $voidCount = 0;

            /** @var Sale $sale */
            foreach ($sales as $sale) {
                if ($sale->status === SaleStatus::confirmed) {
                    $saleCount++;
                    $totalSales += (int) $sale->total;

                    if ($sale->payment_method === PaymentMethod::cash) {
                        $cashSales += (int) $sale->total;
                    }
                } else {
                    $voidCount++;

                    if ($sale->payment_method === PaymentMethod::cash) {
                        $cashVoids += (int) $sale->total;
                    }
                }
            }

            $openingAmount = (int) ($register->opening_amount ?? 0);

            if ($register->closed_at !== null) {
                // Turno cerrado: valores grabados al cerrar.
                $expectedCash = (int) ($register->expected_cash ?? 0);
                $declaredCash = (int) ($register->declared_cash ?? 0);
                $difference = (int) ($register->difference ?? 0);
                $status = 'cerrada';
            } else {
                // Turno abierto: calcular en tiempo real.
                $expectedCash = $openingAmount + $cashSales - $cashVoids;
                $declaredCash = null;
                $difference = null;
                $status = 'abierta';
            }

            $result[] = [
                'id' => $register->id,
                'user_name' => $register->user?->name ?? __('pos.common.none'),
                'opening_amount' => $openingAmount,
                'opened_at' => $register->opened_at,
                'closed_at' => $register->closed_at,
                'sale_count' => $saleCount,
                'total_sales' => $totalSales,
                'cash_sales' => $cashSales,
                'expected_cash' => $expectedCash,
                'declared_cash' => $declaredCash,
                'difference' => $difference,
                'is_closed' => $register->closed_at !== null,
                'status' => $status,
            ];
        }

        return $result;
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
