@extends('layouts.admin')

@section('title', 'Auditoría')

@section('content')
    <div>
        <h2 class="text-lg font-semibold">Auditoría</h2>
        <p class="text-sm text-slate-500">Anulaciones y autorizaciones con autor, motivo y fecha (RF-36).</p>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Acción</th>
                    <th class="px-4 py-3">Autor</th>
                    <th class="px-4 py-3">Venta</th>
                    <th class="px-4 py-3">Motivo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-slate-600">{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $log->action }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $log->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            @if (str_ends_with($log->auditable_type ?? '', 'Sale'))
                                #{{ $log->auditable_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $log->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin registros de auditoría.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
