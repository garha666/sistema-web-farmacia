<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\CloseCashRegisterRequest;
use App\Http\Requests\Cash\OpenCashRegisterRequest;
use App\Models\CashRegister;
use App\Pos\Exception\CashRegisterAlreadyOpen;
use App\Pos\Exception\CashRegisterNotOpen;
use App\Services\Cash\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'summary' => $this->registers->summary($register),
            'arqueo' => $this->registers->arqueo($register, $register->opening_amount),
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
                (int) $request->validated('declared_amount'),
            );
        } catch (CashRegisterNotOpen $exception) {
            return redirect()
                ->route('dashboard.tecnico')
                ->withErrors(['cash' => $exception->getMessage()]);
        }

        return redirect()
            ->route('dashboard.tecnico')
            ->with('cash_closed', $closed->difference);
    }
}
