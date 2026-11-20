@extends('layouts.admin')

@section('title', __('pos.reportes.ventas.title'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.reportes.ventas.title') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.reportes.ventas.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <a
                href="{{ route('admin.reportes.ventas', ['from' => $from, 'to' => $to, 'export' => 1]) }}"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.reportes.ventas.export') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reportes.ventas') }}" class="mt-4 grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 sm:col-span-2 lg:col-span-4">
                {{ $errors->first() }}
            </div>
        @endif
        <div>
            <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.ventas.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $from }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.ventas.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $to }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="flex items-end">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('pos.reportes.ventas.filter') }}
            </button>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pos.reportes.ventas.date') }}</th>
                    <th class="px-4 py-3">{{ __('pos.reportes.ventas.cashier') }}</th>
                    <th class="px-4 py-3">{{ __('pos.reportes.ventas.payment') }}</th>
                    <th class="px-4 py-3">{{ __('pos.reportes.ventas.status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.reportes.ventas.total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                @forelse ($sales as $sale)
                    <tr>
                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $sale->sold_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->user?->name ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3">{{ $sale->payment_method?->label() ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3">{{ $sale->status?->label() ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ '$'.number_format($sale->total, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">{{ __('pos.reportes.ventas.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $sales->withQueryString()->links() }}
    </div>

    <div class="mt-6 rounded-xl bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">{{ __('pos.reportes.ventas.summary') }}</h3>
        <div class="grid gap-2 sm:grid-cols-3 text-sm">
            <div>
                <p class="text-xs text-slate-500">{{ __('pos.reportes.ventas.transactions') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $totalSales }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">{{ __('pos.reportes.ventas.total_sold') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ '$'.number_format($totalAmount, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">{{ __('pos.reportes.ventas.avg_per_sale') }}</p>
                <p class="text-lg font-semibold tabular-nums">
                    @php
                        $avg = $totalSales > 0 ? (int) round($totalAmount / $totalSales) : 0;
                    @endphp
                    {{ '$'.number_format($avg, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    @if (count($byPaymentMethod) > 0)
        <div class="mt-6 rounded-xl bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">{{ __('pos.reportes.ventas.by_payment') }}</h3>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('pos.reportes.ventas.payment_method') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pos.reportes.ventas.count') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pos.reportes.ventas.amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($byPaymentMethod as $method => $data)
                        <tr>
                            <td class="px-4 py-3">{{ \App\Pos\Enum\PaymentMethod::from($method)->label() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $data['count'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ '$'.number_format($data['total'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
