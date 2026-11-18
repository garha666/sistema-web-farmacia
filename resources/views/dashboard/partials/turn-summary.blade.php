{{--
    Resumen del turno abierto (RF-56, RF-61).

    Recibe el turno y su `TurnSummary` ya calculado por el servicio. El desglose
    por medio de pago corresponde a las ventas confirmadas; las anulaciones se
    informan aparte.
--}}
@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

<div class="grid gap-4 lg:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="turn-status">
        <h2 id="turn-status" class="text-lg font-semibold text-slate-900">{{ __('pos.cash_register.status_open') }}</h2>

        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-slate-600">{{ __('pos.cash_register.opened_at') }}</dt>
                <dd class="font-semibold tabular-nums text-slate-900">{{ $register->opened_at?->format('d-m-Y H:i') }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-slate-600">{{ __('pos.cash_register.opening_amount') }}</dt>
                <dd class="font-semibold tabular-nums text-slate-900">{{ $money($register->opening_amount) }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-slate-600">{{ __('pos.cash_register.sales_count') }} ({{ $summary->salesCount() }})</dt>
                <dd class="font-semibold tabular-nums text-slate-900">{{ $money($summary->totalSales()) }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-slate-600">{{ __('pos.cash_register.total_voids') }} ({{ $summary->voidCount() }})</dt>
                <dd class="font-semibold tabular-nums text-slate-900">{{ $money($summary->totalVoids()) }}</dd>
            </div>
        </dl>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="turn-breakdown">
        <h2 id="turn-breakdown" class="text-lg font-semibold text-slate-900">{{ __('pos.cash_register.breakdown') }}</h2>

        @if ($summary->hasSales())
            <table class="mt-3 w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th scope="col" class="py-2">{{ __('pos.cash_register.method') }}</th>
                        <th scope="col" class="py-2 text-right">{{ __('pos.cash_register.count') }}</th>
                        <th scope="col" class="py-2 text-right">{{ __('pos.cash_register.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary->breakdown() as $row)
                        <tr class="border-t border-slate-100">
                            <th scope="row" class="py-2 text-left font-medium text-slate-700">{{ $row->method()->label() }}</th>
                            <td class="py-2 text-right tabular-nums text-slate-600">{{ $row->count() }}</td>
                            <td class="py-2 text-right font-semibold tabular-nums text-slate-900">{{ $money($row->amount()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="mt-3 text-sm text-slate-500">{{ __('pos.cash_register.empty') }}</p>
        @endif
    </section>
</div>
