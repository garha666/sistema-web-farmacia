<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Reportes\CsvExporter;
use App\Services\Reportes\ReportesService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Reportes de ventas, arqueo, descuentos e inventario (RF-92 a RF-96).
 *
 * Solo lectura: no modifica ventas, stock ni ningún dato transaccional.
 * Las exportaciones quedan registradas en la auditoría (RF-93, RF-97).
 */
final class ReportesController extends Controller
{
    public function __construct(
        private readonly ReportesService $reportes,
        private readonly CsvExporter $csv,
    ) {}

    /**
     * Reporte de ventas por rango de fechas (RF-92).
     *
     * Solo el Administrador ve las ventas de todos los cajeros.
     */
    public function ventas(Request $request): View|Response
    {
        [$from, $to, $validated] = $this->dateRange($request);

        // RF-93, D-24: CSV con ?export=1 o con Accept: text/csv.
        if ($this->wantsCsv($request)) {
            return $this->exportVentas($request, $from, $to, $validated['from'], $validated['to']);
        }

        $data = $this->reportes->ventasPorRango($from, $to);

        return view('reportes.ventas', [
            'from' => $validated['from'],
            'to' => $validated['to'],
            'sales' => $data['sales'],
            'totalSales' => $data['totalSales'],
            'totalAmount' => $data['totalAmount'],
            'byPaymentMethod' => $data['byPaymentMethod'],
        ]);
    }

    /**
     * Arqueo de caja por turno (RF-94).
     *
     * El Técnico ve sus propios turnos; el Administrador ve los de todos.
     */
    public function arqueo(Request $request): View|Response
    {
        $cashierId = null;

        if ($request->user()->isTecnico()) {
            $cashierId = $request->user()->id;
        } elseif ($request->user()->isAdmin()) {
            $cashierId = $request->filled('cashier_id') ? (int) $request->query('cashier_id') : null;
        }

        $turnos = $this->reportes->arqueoPorTurno($cashierId);

        if ($this->wantsCsv($request)) {
            return $this->exportArqueo($request, $turnos);
        }

        return view('reportes.arqueo', [
            'turnos' => $turnos,
            'cashiers' => User::query()->where('role', 'tecnico')->orderBy('name')->get(),
            'selectedCashier' => $cashierId,
        ]);
    }

    /**
     * Descuentos y anulaciones por rango de fechas (RF-95).
     *
     * El Químico ve sus propias autorizaciones; el Administrador ve todas.
     */
    public function descuentosAnulaciones(Request $request): View|Response
    {
        $cashierId = null;

        if ($request->user()->isQuimico()) {
            $cashierId = $request->user()->id;
        } elseif ($request->user()->isAdmin()) {
            $cashierId = $request->filled('cashier_id') ? (int) $request->query('cashier_id') : null;
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $items = $this->reportes->descuentosAnulacionesPorRango(
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $cashierId,
        );

        if ($this->wantsCsv($request)) {
            return $this->exportDescuentos($request, $items, $validated['from'] ?? null, $validated['to'] ?? null);
        }

        return view('reportes.descuentos-anulaciones', [
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
            'items' => $items,
            'cashiers' => User::query()->whereIn('role', ['quimico', 'admin'])->orderBy('name')->get(),
            'selectedCashier' => $cashierId,
        ]);
    }

    /**
     * Inventario y movimientos de stock de un rango (RF-96).
     *
     * Bodeguero y Administrador ven lo mismo; la ruta filtra por rol.
     */
    public function inventario(Request $request): View|Response
    {
        [$from, $to, $validated] = $this->dateRange($request);

        if ($this->wantsCsv($request)) {
            return $this->exportInventario($request, $from, $to, $validated['from'], $validated['to']);
        }

        $data = $this->reportes->inventario($from, $to);

        return view('reportes.inventario', [
            'from' => $validated['from'],
            'to' => $validated['to'],
            'belowMinimum' => $data['belowMinimum'],
            'outOfStock' => $data['outOfStock'],
            'valuation' => $data['valuation'],
            'movements' => $data['movements']->withQueryString(),
        ]);
    }

    /**
     * Rango de fechas del reporte; sin fechas, hoy (RNF-36).
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: array{from: string, to: string}}
     */
    private function dateRange(Request $request): array
    {
        $today = now((string) config('pos.timezone'))->format('Y-m-d');

        $request->merge([
            'from' => $request->query('from') ?: $today,
            'to' => $request->query('to') ?: $today,
        ]);

        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $tz = new DateTimeZone((string) config('pos.timezone'));

        return [
            DateTimeImmutable::createFromFormat('!Y-m-d', $validated['from'], $tz),
            DateTimeImmutable::createFromFormat('!Y-m-d', $validated['to'], $tz),
            $validated,
        ];
    }

    /**
     * CSV de ventas del rango completo (RF-93, RNF-38).
     */
    private function exportVentas(Request $request, DateTimeImmutable $from, DateTimeImmutable $to, string $fromDate, string $toDate): Response
    {
        $rows = $this->reportes->ventasParaExportar($from, $to)->map(fn ($sale): array => [
            $sale->sold_at?->format('d-m-Y H:i'),
            $sale->user?->name ?? __('pos.common.none'),
            $sale->payment_method?->label() ?? __('pos.common.none'),
            $sale->status?->label() ?? __('pos.common.none'),
            (int) $sale->total,
        ])->all();

        return $this->csvResponse($request, 'ventas', $fromDate, $toDate, __('pos.reportes.csv.ventas'), $rows);
    }

    /**
     * CSV del arqueo con los mismos turnos de la pantalla (RF-94, RF-97).
     *
     * El arqueo no tiene filtro de fechas: la exportación queda «sin rango».
     *
     * @param  array<int, array<string, mixed>>  $turnos
     */
    private function exportArqueo(Request $request, array $turnos): Response
    {
        $rows = array_map(fn (array $turno): array => [
            $turno['id'],
            $turno['user_name'],
            $turno['opened_at']?->format('d-m-Y H:i'),
            $turno['closed_at']?->format('d-m-Y H:i'),
            $turno['status'],
            $turno['opening_amount'],
            $turno['sale_count'],
            $turno['total_sales'],
            $turno['cash_sales'],
            $turno['expected_cash'],
            $turno['declared_cash'],
            $turno['difference'],
        ], $turnos);

        return $this->csvResponse($request, 'arqueo', null, null, __('pos.reportes.csv.arqueo'), $rows);
    }

    /**
     * CSV de descuentos y anulaciones con el filtro de la pantalla (RF-95, RF-97).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function exportDescuentos(Request $request, array $items, ?string $from, ?string $to): Response
    {
        $rows = array_map(fn (array $item): array => [
            $item['date']->format('d-m-Y H:i'),
            $item['author'],
            $item['action'],
            $item['amount'],
            $item['reason'],
        ], $items);

        return $this->csvResponse($request, 'descuentos-anulaciones', $from, $to, __('pos.reportes.csv.descuentos'), $rows);
    }

    /**
     * CSV de inventario: bajo el mínimo, sin stock y todos los movimientos del rango (RF-96, RF-97).
     */
    private function exportInventario(Request $request, DateTimeImmutable $from, DateTimeImmutable $to, string $fromDate, string $toDate): Response
    {
        $data = $this->reportes->existencias();
        $sections = __('pos.reportes.csv.sections');
        $rows = [];

        foreach ([['belowMinimum', 'below_minimum'], ['outOfStock', 'out_of_stock']] as [$list, $section]) {
            foreach ($data[$list] as $product) {
                $rows[] = [$sections[$section], $product->name, $product->stock, $product->min_stock, $product->price, '', '', '', '', '', ''];
            }
        }

        foreach ($this->reportes->movimientosParaExportar($from, $to) as $movement) {
            $rows[] = [
                $sections['movements'],
                $movement->product?->name ?? __('pos.common.none'),
                '', '', '',
                $movement->created_at?->format('d-m-Y H:i'),
                $movement->type->label(),
                $movement->quantity,
                $movement->resulting_stock,
                $movement->note,
                $movement->user?->name ?? __('pos.common.none'),
            ];
        }

        return $this->csvResponse($request, 'inventario', $fromDate, $toDate, __('pos.reportes.csv.inventario'), $rows);
    }

    /**
     * Pide CSV con `?export=1` o `Accept: text/csv` (RF-93, D-24).
     */
    private function wantsCsv(Request $request): bool
    {
        return $request->boolean('export') || $request->prefers(['text/html', 'text/csv']) === 'text/csv';
    }

    /**
     * Arma el CSV, deja la exportación en la auditoría y lo entrega (RF-97, RNF-39).
     *
     * El registro se escribe cuando el archivo ya está generado, así una
     * falla al armarlo no deja una exportación que nunca ocurrió.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|null>>  $rows
     */
    private function csvResponse(Request $request, string $report, ?string $from, ?string $to, array $headers, array $rows): Response
    {
        $content = $this->csv->export($headers, $rows);

        AuditLog::recordExport($request->user(), $report, $from, $to);

        $range = $from === null && $to === null ? '' : '-'.($from ?? 'inicio').'_a_'.($to ?? 'fin');

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporte-'.$report.$range.'.csv"',
        ]);
    }
}
