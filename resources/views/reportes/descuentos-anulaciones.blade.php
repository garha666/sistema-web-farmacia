@extends('layouts.admin')

@section('title', __('pos.reportes.descuentos.title'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.reportes.descuentos.title') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.reportes.descuentos.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <a
                href="{{ route('admin.reportes.descuentos-anulaciones', ['from' => $from, 'to' => $to, 'export' => 1]) }}"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.reportes.descuentos.export') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reportes.descuentos-anulaciones') }}" class="mt-4 grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.descuentos.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $from }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.descuentos.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $to }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        @if (auth()->user()->isAdmin())
            <div>
                <label for="cashier_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.descuentos.cashier') }}</label>
                <select id="cashier_id" name="cashier_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">{{ __('pos.common.all') }}</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" {{ $selectedCashier === $cashier->id ? 'selected' : '' }}>
                            {{ $cashier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="flex items-end">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('pos.reportes.descuentos.filter') }}
            </button>
        </div>
    </form>

    @if ($items === [])
        <div class="mt-4 rounded-xl bg-white p-6 shadow-sm text-center">
            <p class="text-slate-500">{{ __('pos.reportes.descuentos.empty') }}</p>
        </div>
    @else
        <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('pos.reportes.descuentos.date') }}</th>
                        <th class="px-4 py-3">{{ __('pos.reportes.descuentos.author') }}</th>
                        <th class="px-4 py-3">{{ __('pos.reportes.descuentos.action') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pos.reportes.descuentos.amount') }}</th>
                        <th class="px-4 py-3">{{ __('pos.reportes.descuentos.reason') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                    @foreach ($items as $item)
                        <tr>
                            <td class="px-4 py-3 tabular-nums text-slate-600">{{ $item['date']->format('d-m-Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $item['author'] }}</td>
                            <td class="px-4 py-3">{{ $item['action'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ '$'.number_format($item['amount'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $item['reason'] ?? __('pos.common.none') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">{{ __('pos.reportes.descuentos.summary') }}</h3>
            <div class="grid gap-2 sm:grid-cols-3 text-sm">
                <div>
                    <p class="text-xs text-slate-500">{{ __('pos.reportes.descuentos.total_items') }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ count($items) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ __('pos.reportes.descuentos.total_amount') }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ '$'.number_format(array_sum(array_column($items, 'amount')), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
    @endif
@endsection
