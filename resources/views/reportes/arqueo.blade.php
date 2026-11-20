@extends('layouts.admin')

@section('title', __('pos.reportes.arqueo.title'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.reportes.arqueo.title') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.reportes.arqueo.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            @if ($selectedCashier !== null && auth()->user()->isAdmin())
                <a
                    href="{{ route('admin.reportes.arqueo') }}"
                    class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.reportes.arqueo.clear_filter') }}
                </a>
            @endif
            <a
                href="{{ route('admin.reportes.arqueo', array_filter(['cashier_id' => $selectedCashier, 'export' => 1])) }}"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.reportes.arqueo.export') }}
            </a>
        </div>
    </div>

    @if (auth()->user()->isAdmin())
        <form method="GET" action="{{ route('admin.reportes.arqueo') }}" class="mt-4 flex flex-wrap items-end gap-2">
            <div>
                <label for="cashier_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.arqueo.cashier') }}</label>
                <select id="cashier_id" name="cashier_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">{{ __('pos.common.all') }}</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" {{ $selectedCashier === $cashier->id ? 'selected' : '' }}>
                            {{ $cashier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    @endif

    @if ($turnos === [])
        <div class="mt-4 rounded-xl bg-white p-6 shadow-sm text-center">
            <p class="text-slate-500">{{ __('pos.reportes.arqueo.no_registers') }}</p>
        </div>
    @else
        <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('pos.reportes.arqueo.register') }}</th>
                        <th class="px-4 py-3">{{ __('pos.reportes.arqueo.cashier') }}</th>
                        <th class="px-4 py-3">{{ __('pos.reportes.arqueo.opened') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pos.reportes.arqueo.opening') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pos.reportes.arqueo.sales') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pos.reportes.arqueo.cash_sales') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pos.reportes.arqueo.expected') }}</th>
                        @if (auth()->user()->isAdmin() || (! empty($turnos) && isset($turnos[0]['is_closed']) && $turnos[0]['is_closed']))
                            <th class="px-4 py-3 text-right">{{ __('pos.reportes.arqueo.declared') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('pos.reportes.arqueo.difference') }}</th>
                        @endif
                        <th class="px-4 py-3">{{ __('pos.reportes.arqueo.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                    @foreach ($turnos as $turno)
                        <tr>
                            <td class="px-4 py-3 tabular-nums text-slate-600">#{{ $turno['id'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $turno['user_name'] }}</td>
                            <td class="px-4 py-3 tabular-nums text-slate-600">{{ $turno['opened_at']->format('d-m-Y H:i') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ '$'.number_format($turno['opening_amount'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $turno['sale_count'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ '$'.number_format($turno['total_sales'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ '$'.number_format($turno['cash_sales'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ '$'.number_format($turno['expected_cash'], 0, ',', '.') }}</td>
                            @if (auth()->user()->isAdmin() || $turno['is_closed'])
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ $turno['declared_cash'] !== null ? '$'.number_format($turno['declared_cash'], 0, ',', '.') : __('pos.common.none') }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold
                                    {{ $turno['difference'] > 0 ? 'text-emerald-700' : ($turno['difference'] < 0 ? 'text-red-700' : 'text-slate-700') }}">
                                    {{ $turno['difference'] !== null
                                        ? ($turno['difference'] >= 0 ? '+' : '').'$'.number_format(abs($turno['difference']), 0, ',', '.')
                                        : __('pos.common.none') }}
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $turno['is_closed'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                    {{ $turno['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
