@extends('layouts.admin')

@section('title', 'Documentos')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">Documentos</h2>
            <p class="text-sm text-slate-500">Estado de emisión y reintento de pendientes o rechazados (RF-26).</p>
        </div>

        <form method="GET" action="{{ route('admin.documents.index') }}" class="flex items-end gap-2">
            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</label>
                <select id="status" name="status" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}" @selected($status === $option->value)>
                            {{ ucfirst($option->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Filtrar
            </button>
        </form>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Venta</th>
                    <th class="px-4 py-3">Folio</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Emitido</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $document->type->label() }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            @if ($document->sale !== null)
                                <a href="{{ route('admin.sales.show', $document->sale) }}" class="text-emerald-700 hover:underline">
                                    #{{ $document->sale->id }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $document->folio ?? 'Sin folio' }}</td>
                        <td class="px-4 py-3">
                            @if ($document->status === \App\Pos\Enum\DocumentStatus::issued)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Emitido</span>
                            @elseif ($document->status === \App\Pos\Enum\DocumentStatus::pending)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Pendiente</span>
                            @else
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Rechazado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $document->issued_at?->format('d-m-Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($document->status !== \App\Pos\Enum\DocumentStatus::issued)
                                <form method="POST" action="{{ route('admin.documents.retry', $document) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-sm font-semibold text-amber-700 hover:underline">
                                        Reintentar
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">Sin acciones</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No hay documentos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $documents->links() }}
    </div>
@endsection
