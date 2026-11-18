{{--
    Documentos pendientes o rechazados con reintento en línea (RF-47).

    Recibe la colección ya filtrada por `AdminPanelService`; un documento
    emitido no llega aquí y por lo tanto tampoco ofrece reintento. Una venta
    anulada ya no puede reemitirse, así que se muestra sin acción.
--}}
<section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="admin-documents">
    <h3 id="admin-documents" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.admin.documents_heading') }}</h3>
    <p class="text-sm text-slate-500">{{ __('pos.panel.admin.documents_lead') }}</p>

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.document') }}</th>
                    <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.sale') }}</th>
                    <th scope="col" class="hidden px-2 py-2 sm:table-cell">{{ __('pos.panel.admin.date') }}</th>
                    <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.admin.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <tr>
                        <td class="px-2 py-2 font-medium text-slate-800">{{ $document->type->label() }}</td>
                        <td class="px-2 py-2 text-slate-600">
                            @if ($document->sale !== null)
                                <a href="{{ route('admin.sales.show', $document->sale) }}" class="tabular-nums text-emerald-700 hover:underline">
                                    #{{ $document->sale->id }}
                                </a>
                            @else
                                {{ __('pos.common.none') }}
                            @endif
                        </td>
                        <td class="hidden px-2 py-2 tabular-nums text-slate-600 sm:table-cell">{{ $document->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-2 py-2 text-right">
                            @if ($document->sale?->status !== \App\Pos\Enum\SaleStatus::voided)
                                <form method="POST" action="{{ route('admin.documents.retry', $document) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded text-sm font-semibold text-amber-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2">
                                        {{ __('pos.panel.admin.retry') }}
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">{{ __('pos.panel.admin.no_actions') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-2 py-6 text-center text-slate-500">{{ __('pos.panel.admin.documents_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
