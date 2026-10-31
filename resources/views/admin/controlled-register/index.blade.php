@extends('layouts.admin')

@section('title', 'Libro de controlados')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">Libro de controlados</h2>
            <p class="text-sm text-slate-500">Ventas de medicamentos controlados con su receta asociada (RF-10).</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <form method="GET" action="{{ route('admin.controlled-register.index') }}" class="flex items-end gap-2">
                <div>
                    <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Desde</label>
                    <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"
                           class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hasta</label>
                    <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"
                           class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                    Filtrar
                </button>
            </form>

            <a href="{{ route('admin.controlled-register.export', $filters) }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Exportar CSV
            </a>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">RUT cliente</th>
                    <th class="px-4 py-3">Folio</th>
                    <th class="px-4 py-3">Receta</th>
                    <th class="px-4 py-3">Medicamento</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3">Venta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="px-4 py-3 text-slate-600">{{ $entry->sold_at->format('d-m-Y') }}</td>
                        <td class="px-4 py-3">{{ $entry->customer_rut ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $entry->folio ?? 'Sin folio' }}</td>
                        <td class="px-4 py-3">
                            {{ $entry->prescription->number }}
                            <span class="block text-xs text-slate-500">{{ $entry->prescription->prescriber_name }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $entry->medication }}</td>
                        <td class="px-4 py-3 text-right">{{ $entry->quantity }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.sales.show', $entry->sale) }}" class="text-emerald-700 hover:underline">
                                #{{ $entry->sale_id }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No hay registros de controlados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $entries->links() }}
    </div>
@endsection
