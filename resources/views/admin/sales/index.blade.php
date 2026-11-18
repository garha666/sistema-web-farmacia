@extends('layouts.admin')

@section('title', __('pos.admin.sales.title'))

@php
    $money = fn (?int $pesos): string => $pesos === null ? __('pos.common.none') : '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div>
        <h2 class="text-lg font-semibold">{{ __('pos.admin.sales.heading') }}</h2>
        <p class="text-sm text-slate-500">{{ __('pos.admin.sales.subtitle') }}</p>
    </div>

    <form method="GET" action="{{ route('admin.sales.index') }}" class="mt-4 grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sales.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sales.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="payment_method" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sales.payment_method') }}</label>
            <select id="payment_method" name="payment_method" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">{{ __('pos.common.all') }}</option>
                @foreach ($methods as $method)
                    <option value="{{ $method->value }}" @selected(($filters['payment_method'] ?? '') === $method->value)>
                        {{ $method->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="user_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sales.cashier') }}</label>
            <select id="user_id" name="user_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">{{ __('pos.common.all') }}</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $cashier->id)>
                        {{ $cashier->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sales.sale_status') }}</label>
            <select id="status" name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">{{ __('pos.common.all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="document_status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sales.document_status') }}</label>
            <select id="document_status" name="document_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">{{ __('pos.common.all') }}</option>
                @foreach ($documentStatuses as $documentStatus)
                    <option value="{{ $documentStatus->value }}" @selected(($filters['document_status'] ?? '') === $documentStatus->value)>
                        {{ $documentStatus->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="convention_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sales.convention') }}</label>
            <select id="convention_id" name="convention_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">{{ __('pos.common.all') }}</option>
                @foreach ($conventions as $convention)
                    <option value="{{ $convention->id }}" @selected((string) ($filters['convention_id'] ?? '') === (string) $convention->id)>
                        {{ $convention->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('pos.admin.sales.filter') }}
            </button>
            <a href="{{ route('admin.sales.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                {{ __('pos.admin.sales.clear') }}
            </a>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pos.admin.sales.sale') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.sales.date') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.sales.cashier') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.sales.payment') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.sales.convention') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.sales.document') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.admin.sales.total') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.admin.sales.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                @forelse ($sales as $sale)
                    <tr data-sale-id="{{ $sale->id }}">
                        <td class="px-4 py-3 font-medium tabular-nums">#{{ $sale->id }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $sale->sold_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->user?->name ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3">{{ $sale->payment_method->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($sale->convention_name !== null)
                                {{ $sale->convention_name }}
                                <span class="tabular-nums text-xs text-slate-500">({{ rtrim(rtrim(number_format($sale->convention_percent ?? 0, 2, ',', '.'), '0'), ',') }}%)</span>
                            @else
                                {{ __('pos.common.none') }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($sale->document === null)
                                <span class="text-slate-400">{{ __('pos.common.none') }}</span>
                            @else
                                <span class="font-medium">{{ $sale->document->type->label() }}</span>
                                @if ($sale->document->folio !== null)
                                    <span class="text-slate-500">#{{ $sale->document->folio }}</span>
                                @else
                                    <span class="text-slate-500">{{ __('pos.common.no_folio') }}</span>
                                @endif
                                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $sale->document->status === \App\Pos\Enum\DocumentStatus::issued ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $sale->document->status === \App\Pos\Enum\DocumentStatus::pending ? 'bg-amber-100 text-amber-900' : '' }}
                                    {{ $sale->document->status === \App\Pos\Enum\DocumentStatus::rejected ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ $sale->document->status->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $money($sale->total) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.sales.show', $sale) }}" class="rounded text-sm font-semibold text-emerald-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('pos.admin.sales.view') }}</a>

                            @if ($sale->document !== null
                                && $sale->document->status !== \App\Pos\Enum\DocumentStatus::issued
                                && $sale->status !== \App\Pos\Enum\SaleStatus::voided)
                                <form method="POST" action="{{ route('admin.documents.retry', $sale->document) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="ml-3 rounded text-sm font-semibold text-amber-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2">{{ __('pos.admin.sales.retry') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">{{ __('pos.admin.sales.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $sales->links() }}
    </div>
@endsection
