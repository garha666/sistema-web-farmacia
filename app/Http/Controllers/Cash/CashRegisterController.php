<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\CloseCashRegisterRequest;
use App\Http\Requests\Cash\OpenCashRegisterRequest;
use App\Models\CashRegister;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Exception\CashRegisterAlreadyOpen;
use App\Pos\Exception\CashRegisterNotOpen;
use App\Services\Cash\CashRegisterService;
use App\Services\Reportes\CsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Turnos de caja del Técnico (RF-58, RF-59).
 *
 * El controlador sólo valida la forma y traduce excepciones del dominio a
 * mensajes; el arqueo y las transacciones viven en el servicio.
 */
final class CashRegisterController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $registers,
    ) {}

    /**
     * Abre una caja para el cajero en sesión (RF-58).
     */
    public function open(OpenCashRegisterRequest $request): RedirectResponse
    {
        try {
            $this->registers->open(
                $request->user(),
                (int) $request->validated('opening_amount'),
            );
        } catch (CashRegisterAlreadyOpen $exception) {
            throw ValidationException::withMessages([
                'opening_amount' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('dashboard.tecnico')->with('cash_opened', true);
    }

    /**
     * Muestra el turno y pide el efectivo declarado antes de cerrar (RF-59).
     */
    public function showClose(Request $request): View|RedirectResponse
    {
        $register = $this->registers->openFor($request->user());

        if ($register === null) {
            return redirect()
                ->route('dashboard.tecnico')
                ->withErrors(['cash' => __('pos.cash_register.error.none_open')]);
        }

        return view('cash.close', [
            'navGroup' => 'tecnico',
            'register' => $register,
            'denominations' => CloseCashRegisterRequest::DENOMINATIONS,
        ]);
    }

    /**
     * Cierra el turno y graba el arqueo (RF-59).
     */
    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister): RedirectResponse
    {
        abort_unless($cashRegister->user_id === $request->user()?->id, 403);

        if (! $cashRegister->isOpen()) {
            return redirect()
                ->route('dashboard.tecnico')
                ->withErrors(['cash' => __('pos.cash_register.error.already_closed')]);
        }

        try {
            $closed = $this->registers->close(
                $request->user(),
                $cashRegister,
                $request->declaredAmount(),
                reason: $request->validated('reason'),
            );
        } catch (CashRegisterNotOpen $exception) {
            return redirect()
                ->route('dashboard.tecnico')
                ->withErrors(['cash' => $exception->getMessage()]);
        }

        return redirect()
            ->route('dashboard.tecnico')
            ->with('cash_closed', $closed->difference)
            ->with('closed_register_id', $closed->id);
    }

    public function report(Request $request, CashRegister $cashRegister, CsvExporter $csv): View|Response
    {
        abort_unless($request->user()->isAdmin() || $request->user()->id === $cashRegister->user_id, 403);
        abort_if($cashRegister->isOpen(), 404);

        $currency = [
            'opening_amount' => $cashRegister->opening_amount,
            'total_sales' => $cashRegister->total_sales,
            'total_voids' => $cashRegister->total_voids,
            'expected_cash' => $cashRegister->expected_cash,
            'declared_amount' => $cashRegister->declared_cash,
            'difference' => $cashRegister->difference,
        ];

        $fields = [
            [__('pos.cash_register.report_turn'), $cashRegister->id, false],
            [__('pos.reportes.arqueo.cashier'), $cashRegister->user->name, false],
            [__('pos.cash_register.opened_at'), $cashRegister->opened_at->format('d-m-Y H:i'), false],
            [__('pos.cash_register.report_closed_at'), $cashRegister->closed_at->format('d-m-Y H:i'), false],
        ];

        foreach ($currency as $key => $amount) {
            $fields[] = [__('pos.cash_register.'.$key), $amount, true];
        }
        if ($cashRegister->closed_payment_breakdown !== null) {
            foreach (PaymentMethod::cases() as $method) {
                $fields[] = [$method->label(), $cashRegister->closed_payment_breakdown[$method->value] ?? 0, true];
            }
        }
        $fields[] = [__('pos.cash_register.close_reason'), $cashRegister->close_reason, false];

        if ($request->query('format') === 'csv') {
            return response($csv->export(
                [__('pos.cash_register.report_field'), __('pos.cash_register.report_value')],
                array_map(static fn (array $row): array => array_slice($row, 0, 2), $fields),
            ), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="cierre-caja-'.$cashRegister->id.'.csv"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return view('cash.report', ['register' => $cashRegister, 'fields' => $fields]);
    }
}
