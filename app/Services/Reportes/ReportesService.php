<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * El listado trae todas las ventas del rango (con su estado); los totales,
     * las transacciones y el desglose cuentan sólo las confirmadas, igual que
     * el panel del día (D-11).
     *
     * @return array{
     *     sales: LengthAwarePaginator,
     *     totalSales: int,
     *     totalAmount: int,
     *     byPaymentMethod: array<string, array{count: int, total: int}>
     * }
     */
    public function ventasPorRango(\DateTimeImmutable $from, \DateTimeImmutable $to, int $perPage = 20): array
    {
        $sales = $this->ventasDelRangoQuery($from, $to)->paginate($perPage);

        $confirmed = fn (): Builder => $this->rangoQuery($from, $to)
            ->where('status', SaleStatus::confirmed);

        $byPaymentMethod = $confirmed()
            ->selectRaw('payment_method, count(*) as count, sum(total) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->payment_method->value => ['count' => (int) $row->count, 'total' => (int) $row->total]])
            ->toArray();

        return [
            'sales' => $sales,
            'totalSales' => (int) $confirmed()->count(),
            'totalAmount' => (int) $confirmed()->sum('total'),
            'byPaymentMethod' => $byPaymentMethod,
        ];
    }

    /**
     * Todas las ventas del rango, sin paginar, para la exportación (RF-93).
     *
     * @return Collection<int, Sale>
     */
    public function ventasParaExportar(\DateTimeImmutable $from, \DateTimeImmutable $to): Collection
    {
        return $this->ventasDelRangoQuery($from, $to)->get();
    }

    private function rangoQuery(\DateTimeImmutable $from, \DateTimeImmutable $to): Builder
    {
        return Sale::query()->whereBetween('sold_at', [
            $from->setTime(0, 0, 0),
            $to->setTime(23, 59, 59),
        ]);
    }

    private function ventasDelRangoQuery(\DateTimeImmutable $from, \DateTimeImmutable $to): Builder
    {
        return $this->rangoQuery($from, $to)
            ->with('user')
            ->latest('sold_at')
            ->latest('id');
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

    /**
     * Descuentos y anulaciones de un rango de fechas (RF-95).
     *
     * @param  ?int  $userId  cuando se pide, solo las autorizaciones de ese usuario
     * @return array<int, array>
     */
    public function descuentosAnulacionesPorRango(
        ?string $from,
        ?string $to,
        ?int $userId = null,
    ): array {
        $query = AuditLog::query()
            ->with('user')
            ->whereIn('action', [
                'sale.discount_authorized',
                'sale.line_discount_authorized',
                'sale.prescription_reuse',
                'sale.void',
            ]);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        $items = $query->latest('created_at')->get()->map(function ($log): array {
            $amount = 0;
            $actionLabel = match ($log->action) {
                'sale.discount_authorized' => __('pos.panel.audit_action.sale.discount_authorized'),
                'sale.line_discount_authorized' => __('pos.panel.audit_action.sale.line_discount_authorized'),
                'sale.prescription_reuse' => __('pos.panel.audit_action.sale.prescription_reuse'),
                'sale.void' => __('pos.panel.audit_action.sale.void'),
                default => $log->action,
            };

            if ($log->action === 'sale.void' && $log->auditable_type === 'App\Models\Sale') {
                $sale = Sale::query()->find($log->auditable_id);
                $amount = $sale?->total ?? 0;
            } else {
                $amount = (int) ($log->discount_value ?? 0);
            }

            return [
                'author' => $log->user?->name ?? __('pos.common.none'),
                'action' => $actionLabel,
                'amount' => $amount,
                'reason' => $log->reason,
                'date' => $log->created_at,
            ];
        });

        return $items->values()->all();
    }

    /**
     * Inventario vigente y movimientos de stock de un rango (RF-96).
     *
     * Las listas salen de los mismos scopes del panel (`belowMinimum`,
     * `outOfStock`); «bajo el mínimo» excluye lo agotado para no repetir
     * productos. La valorización usa el precio de venta (S-20).
     *
     * @return array{
     *     belowMinimum: Collection<int, Product>,
     *     outOfStock: Collection<int, Product>,
     *     valuation: int,
     *     movements: LengthAwarePaginator
     * }
     */
    public function inventario(\DateTimeImmutable $from, \DateTimeImmutable $to, int $perPage = 50): array
    {
        return [
            ...$this->existencias(),
            'movements' => $this->movementsQuery($from, $to)->paginate($perPage),
        ];
    }

    /**
     * Existencias vigentes: bajo el mínimo, sin stock y valorización (RF-96).
     *
     * @return array{belowMinimum: Collection<int, Product>, outOfStock: Collection<int, Product>, valuation: int}
     */
    public function existencias(): array
    {
        $catalog = fn (): Builder => Product::query()->active();

        return [
            'belowMinimum' => $catalog()->belowMinimum()->where('stock', '>', 0)->orderBy('name')->get(),
            'outOfStock' => $catalog()->outOfStock()->orderBy('name')->get(),
            'valuation' => (int) $catalog()->where('stock', '>', 0)->sum(DB::raw('stock * price')),
        ];
    }

    /**
     * Movimientos de stock del rango completo, sin paginar, para el CSV (RF-97).
     *
     * @return Collection<int, StockMovement>
     */
    public function movimientosParaExportar(\DateTimeImmutable $from, \DateTimeImmutable $to): Collection
    {
        return $this->movementsQuery($from, $to)->get();
    }

    /**
     * @return Builder<StockMovement>
     */
    private function movementsQuery(\DateTimeImmutable $from, \DateTimeImmutable $to): Builder
    {
        return StockMovement::query()
            ->with(['product', 'user'])
            ->whereBetween('created_at', [$from->setTime(0, 0, 0), $to->setTime(23, 59, 59)])
            ->latest('created_at')
            ->latest('id');
    }
}
