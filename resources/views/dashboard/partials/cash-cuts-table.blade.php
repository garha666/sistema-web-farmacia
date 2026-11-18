{{--
    Cortes de caja del día (RF-51).

    Recibe los turnos abiertos y cerrados que comenzaron hoy, con su
    responsable. Un turno abierto todavía no tiene arqueo: esperado, declarado
    y diferencia se muestran como pendientes. La diferencia se pinta en
    esmeralda a favor y ámbar en contra.
--}}
@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');

    $difference = function ($cut) use ($money): array {
        if ($cut->difference === null) {
            return ['text' => __('pos.common.none'), 'class' => 'text-slate-400'];
        }

        if ($cut->difference > 0) {
            return ['text' => '+'.$money($cut->difference), 'class' => 'text-emerald-700'];
        }

        if ($cut->difference < 0) {
            return ['text' => '-'.$money(abs($cut->difference)), 'class' => 'text-amber-700'];
        }

        return ['text' => $money(0), 'class' => 'text-slate-700'];
    };
@endphp

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="cash-cuts">
    <h3 id="cash-cuts" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.admin.cash_cuts_heading') }}</h3>

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-[480px] divide-y divide-slate-200 text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.responsible') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.status') }}</th>
                    <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.admin.expected') }}</th>
                    <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.admin.declared') }}</th>
                    <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.admin.difference') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($cashCuts as $cut)
                    @php $diff = $difference($cut); @endphp
                    <tr>
                        <td class="px-2 py-2 text-slate-700">{{ $cut->user?->name ?? __('pos.common.none') }}</td>
                        <td class="px-2 py-2 text-slate-600">
                            {{ $cut->closed_at === null ? __('pos.panel.admin.open_register') : __('pos.panel.admin.closed_register') }}
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums text-slate-700">
                            {{ $cut->expected_cash === null ? __('pos.common.none') : $money($cut->expected_cash) }}
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums text-slate-700">
                            {{ $cut->declared_cash === null ? __('pos.common.none') : $money($cut->declared_cash) }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold tabular-nums {{ $diff['class'] }}">{{ $diff['text'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-2 py-6 text-center text-slate-500">{{ __('pos.panel.admin.cash_cuts_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
