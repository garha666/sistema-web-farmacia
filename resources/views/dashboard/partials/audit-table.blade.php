{{--
    Actividad reciente (RF-49).

    Recibe los últimos `AuditLog` con su autor y su línea de venta cargados. La
    acción se traduce cuando el grupo `pos.panel.audit_action` la conoce; si no,
    se muestra el identificador técnico para no ocultar el movimiento.
--}}
@php
    $actionLabel = function (string $action): string {
        $key = 'pos.panel.audit_action.'.$action;
        $label = __($key);

        return $label === $key ? $action : $label;
    };
@endphp

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="recent-audit">
    <h3 id="recent-audit" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.admin.audit_heading') }}</h3>

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-[560px] divide-y divide-slate-200 text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.date') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.author') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.action') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.sale') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.reason') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($audit as $log)
                    @php
                        $isLine = str_ends_with($log->auditable_type ?? '', 'SaleLine');
                        $isSale = str_ends_with($log->auditable_type ?? '', 'Sale');
                        $saleId = $isLine ? $log->saleLine?->sale_id : ($isSale ? $log->auditable_id : null);
                    @endphp
                    <tr>
                        <td class="px-2 py-2 tabular-nums text-slate-600">{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-2 py-2 text-slate-700">{{ $log->user?->name ?? __('pos.common.none') }}</td>
                        <td class="px-2 py-2 text-slate-700">{{ $actionLabel($log->action) }}</td>
                        <td class="px-2 py-2 tabular-nums text-slate-600">
                            {{ $saleId !== null ? '#'.$saleId : __('pos.common.none') }}
                        </td>
                        <td class="px-2 py-2 text-slate-700">{{ filled($log->reason) ? $log->reason : __('pos.common.none') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-2 py-6 text-center text-slate-500">{{ __('pos.panel.admin.audit_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
