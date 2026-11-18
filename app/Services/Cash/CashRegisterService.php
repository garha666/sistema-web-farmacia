<?php

declare(strict_types=1);

namespace App\Services\Cash;

use App\Models\CashRegister;
use App\Models\User;
use App\Pos\Calculator\CashArqueo;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use App\Pos\Exception\CashRegisterAlreadyOpen;
use App\Pos\Exception\CashRegisterNotOpen;
use App\Pos\Value\ArqueoResult;
use App\Pos\Value\PaymentBreakdown;
use App\Pos\Value\TurnSummary;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Apertura, resumen y cierre del turno de caja (RF-58, RF-59, RF-61).
 *
 * La lógica de arqueo vive en `App\Pos\Calculator\CashArqueo`; aquí sólo se
 * consulta, se transacciona y se graba el acta del cierre. Los totales se
 * agregan en una sola consulta agrupada por estado y medio de pago.
 */
final class CashRegisterService
{
    public function __construct(
        private readonly CashArqueo $arqueo,
    ) {}

    /**
     * Turno abierto del usuario, si lo tiene (RF-56, RF-57).
     */
    public function openFor(User $user): ?CashRegister
    {
        return CashRegister::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Abre un turno nuevo (RF-58, caso límite 8).
     */
    public function open(
        User $cashier,
        int $openingAmount,
        ?DateTimeImmutable $now = null,
    ): CashRegister {
        $at = $this->localTime($now);

        return DB::transaction(function () use ($cashier, $openingAmount, $at): CashRegister {
            if ($this->openFor($cashier) !== null) {
                throw new CashRegisterAlreadyOpen(__('pos.cash_register.error.already_open'));
            }

            try {
                return CashRegister::query()->create([
                    'user_id' => $cashier->id,
                    'opening_amount' => $openingAmount,
                    'opened_at' => $at->format('Y-m-d H:i:s'),
                ]);
            } catch (QueryException $exception) {
                // El índice único parcial es la garantía real ante dos
                // pestañas abiertas al mismo tiempo (S-9).
                if ($this->isUniqueViolation($exception)) {
                    throw new CashRegisterAlreadyOpen(__('pos.cash_register.error.already_open'), 0, $exception);
                }

                throw $exception;
            }
        }, 3);
    }

    /**
     * Totales del turno (RF-56, RF-61).
     */
    public function summary(CashRegister $register): TurnSummary
    {
        $rows = DB::table('sales')
            ->where('cash_register_id', $register->id)
            ->selectRaw('status, payment_method, COUNT(*) AS count, COALESCE(SUM(total), 0) AS amount')
            ->groupBy('status', 'payment_method')
            ->get();

        $salesCount = 0;
        $totalSales = 0;
        $voidCount = 0;
        $totalVoids = 0;
        $cashSales = 0;
        $cashVoids = 0;
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

                if ($row->payment_method === PaymentMethod::cash->value) {
                    $cashSales += $amount;
                }

                continue;
            }

            $voidCount += $count;
            $totalVoids += $amount;

            if ($row->payment_method === PaymentMethod::cash->value) {
                $cashVoids += $amount;
            }
        }

        return new TurnSummary(
            $salesCount,
            $totalSales,
            $voidCount,
            $totalVoids,
            $cashSales,
            $cashVoids,
            $this->orderedBreakdown($breakdown),
        );
    }

    /**
     * Arqueo del turno con el monto declarado (RF-59).
     */
    public function arqueo(CashRegister $register, int $declaredAmount): ArqueoResult
    {
        $summary = $this->summary($register);

        return new ArqueoResult(
            $this->arqueo->expected($register->opening_amount, $summary->cashSales(), $summary->cashVoids()),
            $declaredAmount,
        );
    }

    /**
     * Cierra el turno y graba el acta (RF-59, caso límite 7).
     *
     * Una vez cerrado, el turno no se vuelve a calcular: una anulación
     * posterior cambia la venta, pero no reescribe este registro.
     */
    public function close(
        User $cashier,
        CashRegister $register,
        int $declaredAmount,
        ?DateTimeImmutable $now = null,
    ): CashRegister {
        $at = $this->localTime($now);

        return DB::transaction(function () use ($register, $declaredAmount, $at): CashRegister {
            /** @var CashRegister $locked */
            $locked = CashRegister::query()
                ->whereKey($register->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new CashRegisterNotOpen(__('pos.cash_register.error.already_closed'));
            }

            $summary = $this->summary($locked);
            $arqueo = new ArqueoResult(
                $this->arqueo->expected($locked->opening_amount, $summary->cashSales(), $summary->cashVoids()),
                $declaredAmount,
            );

            $locked->update([
                'closed_at' => $at->format('Y-m-d H:i:s'),
                'total_sales' => $summary->totalSales(),
                'total_voids' => $summary->totalVoids(),
                'cash_sales' => $summary->cashSales(),
                'cash_voids' => $summary->cashVoids(),
                'expected_cash' => $arqueo->expected(),
                'declared_cash' => $arqueo->declared(),
                'difference' => $arqueo->difference(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    private function localTime(?DateTimeImmutable $now): DateTimeImmutable
    {
        return $now ?? new DateTimeImmutable('now', new DateTimeZone(config('pos.timezone')));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            || str_contains($exception->getMessage(), 'cash_registers_open_unique');
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
