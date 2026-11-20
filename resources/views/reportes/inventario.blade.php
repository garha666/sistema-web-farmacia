@extends('layouts.admin')

@section('title', __('pos.reportes.inventario.title'))

@section('content')
    @php
        $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
        $lists = [
            ['title' => 'below_minimum_title', 'empty' => 'empty_below_minimum', 'items' => $belowMinimum],
            ['title' => 'out_of_stock_title', 'empty' => 'empty_out_of_stock', 'items' => $outOfStock],
        ];
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.reportes.inventario.title') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.reportes.inventario.subtitle') }}</p>
        </div>
        <a
            href="{{ route('admin.reportes.inventario', ['from' => $from, 'to' => $to, 'export' => 1]) }}"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
            {{ __('pos.reportes.inventario.export') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.reportes.inventario') }}" class="mt-4 grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 sm:col-span-2 lg:col-span-4">
                {{ $errors->first() }}
            </div>
        @endif
        <div>
            <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.inventario.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $from }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.inventario.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $to }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="flex items-end">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.reportes.inventario.filter') }}
            </button>
        </div>
    </form>

    <div class="mt-6 rounded-xl bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.inventario.summary') }}</h3>
        <div class="grid gap-3 text-sm sm:grid-cols-3">
            <div>
                <p class="text-xs text-slate-500">{{ __('pos.reportes.inventario.valuation') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $money($valuation) }}</p>
                <p class="text-xs text-slate-500">{{ __('pos.reportes.inventario.valuation_note') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">{{ __('pos.reportes.inventario.below_minimum_count') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $belowMinimum->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">{{ __('pos.reportes.inventario.out_of_stock_count') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $outOfStock->count() }}</p>
            </div>
        </div>
    </div>

    @foreach ($lists as $list)
        <section class="mt-6" aria-labelledby="{{ $list['title'] }}">
            <h3 id="{{ $list['title'] }}" class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.inventario.'.$list['title']) }}</h3>
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('pos.reportes.inventario.product') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.stock') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.min_stock') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.price') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                        @forelse ($list['items'] as $product)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $product->stock }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $product->min_stock }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $money($product->price) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">{{ __('pos.reportes.inventario.'.$list['empty']) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section class="mt-6" aria-labelledby="movements_title">
        <h3 id="movements_title" class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.reportes.inventario.movements_title') }}</h3>
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('pos.reportes.inventario.date') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.reportes.inventario.product') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.reportes.inventario.type') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.quantity') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('pos.reportes.inventario.resulting_stock') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.reportes.inventario.reason') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.reportes.inventario.author') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 tabular-nums text-slate-600">{{ $movement->created_at?->format('d-m-Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $movement->product?->name ?? __('pos.common.none') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $movement->quantity < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ sprintf('%+d', $movement->quantity) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $movement->resulting_stock ?? __('pos.common.none') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ filled($movement->note) ? $movement->note : __('pos.common.none') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->user?->name ?? __('pos.common.none') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">{{ __('pos.reportes.inventario.empty_movements') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $movements->links() }}
        </div>
    </section>
@endsection
