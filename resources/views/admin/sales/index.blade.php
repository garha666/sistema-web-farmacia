@extends('layouts.admin')

@section('title', 'Historial de ventas')

@php
    $money = fn (?int $pesos): string => $pesos === null ? '—' : '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div>
        <h2 class="text-lg font-semibold">Historial de ventas</h2>
        <p class="text-sm text-slate-500">Consulta por fecha, cajero, medio de pago, convenio y estado (RF-37).</p>
    </div>

    <form method="GET" action="{{ route('admin.sales.index') }}" class="mt-4 grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Desde</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hasta</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="payment_method" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Medio de pago</label>
            <select id="payment_method" name="payment_method" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($methods as $method)
                    <option value="{{ $method->value }}" @selected(($filters['payment_method'] ?? '') === $method->value)>
                        {{ $method->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="user_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cajero</label>
            <select id="user_id" name="user_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $cashier->id)>
                        {{ $cashier->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado de venta</label>
            <select id="status" name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                        {{ $status === \App\Pos\Enum\SaleStatus::voided ? 'Anulada' : 'Confirmada' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="document_status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado del documento</label>
            <select id="document_status" name="document_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($documentStatuses as $documentStatus)
                    <option value="{{ $documentStatus->value }}" @selected(($filters['document_status'] ?? '') === $documentStatus->value)>
                        {{ ucfirst($documentStatus->value) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="convention_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Convenio</label>
            <select id="convention_id" name="convention_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($conventions as $convention)
                    <option value="{{ $convention->id }}" @selected((string) ($filters['convention_id'] ?? '') === (string) $convention->id)>
                        {{ $convention->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Filtrar
            </button>
            <a href="{{ route('admin.sales.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                Limpiar
            </a>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Venta</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Cajero</th>
                    <th class="px-4 py-3">Pago</th>
                    <th class="px-4 py-3">Convenio</th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sales as $sale)
                    <tr data-sale-id="{{ $sale->id }}">
                        <td class="px-4 py-3 font-medium">#{{ $sale->id }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->sold_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $sale->payment_method->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($sale->convention_name !== null)
                                {{ $sale->convention_name }}
                                <span class="text-xs text-slate-500">({{ rtrim(rtrim(number_format($sale->convention_percent ?? 0, 2, ',', '.'), '0'), ',') }}%)</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($sale->document === null)
                                <span class="text-slate-400">—</span>
                            @else
                                <span class="font-medium">{{ $sale->document->type->label() }}</span>
                                <span class="text-slate-500">#{{ $sale->document->folio ?? 'sin folio' }}</span>
                                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $sale->document->status === \App\Pos\Enum\DocumentStatus::issued ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $sale->document->status === \App\Pos\Enum\DocumentStatus::pending ? 'bg-amber-100 text-amber-900' : '' }}
                                    {{ $sale->document->status === \App\Pos\Enum\DocumentStatus::rejected ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ ucfirst($sale->document->status->value) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $money($sale->total) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.sales.show', $sale) }}" class="text-sm font-semibold text-emerald-700 hover:underline">Ver</a>

                            @if ($sale->document !== null && $sale->document->status !== \App\Pos\Enum\DocumentStatus::issued)
                                <form method="POST" action="{{ route('admin.documents.retry', $sale->document) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="ml-3 text-sm font-semibold text-amber-700 hover:underline">Reintentar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No hay ventas con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $sales->links() }}
    </div>
@endsection
