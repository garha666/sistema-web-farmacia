{{--
    Anulaciones registradas hoy (RF-54).

    Recibe los `AuditLog` de acción `sale.void` con su autor cargado. La venta
    se resuelve desde el subject polimórfico y el motivo es obligatorio en el
    flujo de anulación, pero se contempla un registro sin motivo.
--}}
<section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="chemist-voids">
    <h3 id="chemist-voids" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.quimico.voids_heading') }}</h3>
    <p class="text-sm text-slate-500">{{ __('pos.panel.quimico.voids_lead') }}</p>

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="hidden px-2 py-2 sm:table-cell">{{ __('pos.panel.quimico.date') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.quimico.sale') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.quimico.reason') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.quimico.author') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($voids as $log)
                    <tr>
                        <td class="hidden px-2 py-2 tabular-nums text-slate-600 sm:table-cell">{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-2 py-2 tabular-nums text-slate-600">
                            {{ $log->auditable_id !== null ? '#'.$log->auditable_id : __('pos.common.none') }}
                        </td>
                        <td class="px-2 py-2 text-slate-700">{{ filled($log->reason) ? $log->reason : __('pos.common.none') }}</td>
                        <td class="px-2 py-2 text-slate-700">{{ $log->user?->name ?? __('pos.common.none') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-2 py-6 text-center text-slate-500">{{ __('pos.panel.quimico.voids_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
