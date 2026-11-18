{{--
    Desglose por medio de pago de las ventas confirmadas del día (RF-46, RF-52, RF-75).

    Recibe el `SalesDaySummary` ya calculado por `SalesDayQuery` y, opcionalmente,
    el panel que lo muestra (`admin` o `quimico`) para elegir sus traducciones.
--}}
@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
    $panel = $panel ?? 'admin';
@endphp

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="day-breakdown">
    <h3 id="day-breakdown" class="text-lg font-semibold text-slate-900">{{ __("pos.panel.{$panel}.breakdown") }}</h3>

    @if ($summary->hasSales())
        <table class="mt-3 w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th scope="col" class="py-2">{{ __("pos.panel.{$panel}.method") }}</th>
                    <th scope="col" class="py-2 text-right">{{ __("pos.panel.{$panel}.count") }}</th>
                    <th scope="col" class="py-2 text-right">{{ __("pos.panel.{$panel}.amount") }}</th>
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
        <p class="mt-3 text-sm text-slate-500">{{ __("pos.panel.{$panel}.no_sales") }}</p>
    @endif
</section>
