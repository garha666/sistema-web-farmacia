@extends('layouts.admin')

@section('title', __('pos.reportes.inventario.title'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.reportes.inventario.title') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.reportes.inventario.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <form method="GET" action="{{ route('admin.reportes.inventario') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.inventario.from') }}</label>
                    <input id="from" name="from" type="date" value="{{ $from }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.inventario.to') }}</label>
                    <input id="to" name="to" type="date" value="{{ $to }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        {{ __('pos.reportes.inventario.filter') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pos.reportes.inventario.product') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.stock') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.min_stock') }}</th>
                    <th class="px-4 py-3">{{ __('pos.reportes.inventario.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                @forelse ($items as $item)
                    <tr>
                        <td class="px-4 py-3 text-slate-600">{{ $item['name'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $item['stock'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $item['min_stock'] }}</td>
                        <td class="px-4 py-3">
                            @if ($item['is_out_of_stock'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-xs font-semibold">
                                    {{ __('pos.reportes.inventario.out_of_stock') }}
                                </span>
                            @elseif ($item['is_below_minimum'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 text-xs font-semibold">
                                    {{ __('pos.reportes.inventario.below_minimum') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold">
                                    {{ __('pos.reportes.inventario.ok') }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">{{ __('pos.reportes.inventario.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach ($items as $item)
        @if (count($item['movements']) > 0)
            <div class="mt-6 rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">{{ $item['name'] }}</h3>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('pos.reportes.inventario.date') }}</th>
                            <th class="px-4 py-3">{{ __('pos.reportes.inventario.type') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.quantity') }}</th>
                            <th class="px-4 py-3">{{ __('pos.reportes.inventario.reason') }}</th>
                            <th class="px-4 py-3">{{ __('pos.reportes.inventario.user') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($item['movements'] as $movement)
                            <tr>
                                <td class="px-4 py-3 tabular-nums text-slate-600">{{ $movement['date']->format('d-m-Y H:i') }}</td>
                                <td class="px-4 py-3">{{ __('pos.reportes.inventario.type_' . $movement['type']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $movement['quantity'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $movement['reason'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $movement['user'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach
@endsection
