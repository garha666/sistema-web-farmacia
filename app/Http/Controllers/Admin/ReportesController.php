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
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $tz = new DateTimeZone((string) config('pos.timezone'));
        $from = DateTimeImmutable::createFromFormat('Y-m-d', $validated['from'], $tz);
        $to = DateTimeImmutable::createFromFormat('Y-m-d', $validated['to'], $tz);

        $data = $this->reportes->ventasPorRango($from, $to);

        // RF-93: exportación a CSV (solo con ?export=1, nunca por Accept header).
        if ($request->boolean('export')) {
            $this->recordExport($request, 'ventas', $validated['from'], $validated['to']);

            $headers = [
                'Fecha', 'Cajero', 'Medio de pago', 'Estado', 'Total',
            ];

            $rows = $data['sales']->getCollection()->map(fn ($sale): array => [
                $sale->sold_at?->format('d-m-Y H:i'),
                $sale->user?->name ?? __('pos.common.none'),
                $sale->payment_method?->label() ?? __('pos.common.none'),
                $sale->status?->label() ?? __('pos.common.none'),
                '$'.number_format($sale->total, 0, ',', '.'),
            ])->toArray();

            $content = $this->csv->export($headers, $rows);

            $filename = 'reporte-ventas-'.$validated['from'].'_a_'.$validated['to'].'.csv';

            return response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

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
    public function arqueo(Request $request): View
    {
        $cashierId = null;

        if ($request->user()->isTecnico()) {
            $cashierId = $request->user()->id;
        } elseif ($request->user()->isAdmin()) {
            $cashierId = $request->filled('cashier_id') ? (int) $request->query('cashier_id') : null;
        }

        $turnos = $this->reportes->arqueoPorTurno($cashierId);

        return view('reportes.arqueo', [
            'turnos' => $turnos,
            'cashiers' => User::query()->where('role', 'tecnico')->orderBy('name')->get(),
            'selectedCashier' => $cashierId,
        ]);
    }

    /**
     * Registra la exportación en la bitácora de auditoría (RF-93, RF-97, RNF-39).
     *
     * @param  array{from: string, to: string}  $range
     */
    private function recordExport(Request $request, string $report, string $from, string $to): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()?->getKey(),
            'action' => 'report.exported',
            'reason' => "Reporte {$report}: {$from} a {$to}",
        ]);
    }
}
