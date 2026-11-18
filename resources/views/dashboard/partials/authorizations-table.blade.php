{{--
    Autorizaciones de hoy del usuario en sesión (RF-53).

    Recibe los `AuditLog` ya filtrados por `ChemistPanelService`. El monto sale
    del descuento global de la venta o del monto aplicado de la línea; una
    receta reutilizada no tiene monto y se muestra como «—».
--}}
@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');

    $actionLabel = function (string $action): string {
        $key = 'pos.panel.audit_action.'.$action;
        $label = __($key);

        return $label === $key ? $action : $label;
    };

    $saleOf = function ($log) {
        if (str_ends_with($log->auditable_type ?? '', 'SaleLine')) {
            return $log->saleLine?->sale;
        }

        return $log->auditable instanceof \App\Models\Sale ? $log->auditable : null;
    };

    $amountOf = function ($log) use ($saleOf): ?int {
        if ($log->action === 'sale.discount_authorized') {
            return $saleOf($log)?->global_discount;
        }

        if ($log->action === 'sale.line_discount_authorized') {
            return $log->discount_value;
        }

        return null;
    };
@endphp

<section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="chemist-authorizations">
    <h3 id="chemist-authorizations" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.quimico.authorizations_heading') }}</h3>
    <p class="text-sm text-slate-500">{{ __('pos.panel.quimico.authorizations_lead') }}</p>

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="hidden px-2 py-2 sm:table-cell">{{ __('pos.panel.quimico.date') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.quimico.action') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.quimico.sale') }}</th>
                    <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.quimico.amount_authorized') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.quimico.reason') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($authorizations as $log)
                    @php
                        $sale = $saleOf($log);
                        $amount = $amountOf($log);
                    @endphp
                    <tr>
                        <td class="hidden px-2 py-2 tabular-nums text-slate-600 sm:table-cell">{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-2 py-2 font-medium text-slate-800">{{ $actionLabel($log->action) }}</td>
                        <td class="px-2 py-2 tabular-nums text-slate-600">
                            {{ $sale !== null ? '#'.$sale->id : __('pos.common.none') }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold tabular-nums text-slate-900">
                            {{ $amount === null ? __('pos.common.none') : $money($amount) }}
                        </td>
                        <td class="px-2 py-2 text-slate-700">{{ filled($log->reason) ? $log->reason : __('pos.common.none') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-2 py-6 text-center text-slate-500">{{ __('pos.panel.quimico.authorizations_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
