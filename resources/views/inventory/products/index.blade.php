@extends('layouts.admin')

@section('title', __('pos.inventory.products.title'))

@section('content')
    @php
        $canEditProducts = auth()->user()->role === 'admin';
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ __('pos.inventory.products.heading') }}</h2>
                <p class="text-sm text-slate-500">{{ __('pos.inventory.products.subtitle') }}</p>
            </div>

            @if ($canEditProducts)
                <a href="{{ route('inventory.products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.inventory.products.create') }}
                </a>
            @endif
        </div>

        @if (session('success'))
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ session('success') }}
            </p>
        @endif

        <form method="GET" action="{{ route('inventory.products.index') }}" class="flex flex-col gap-2 sm:flex-row" role="search">
            <label for="q" class="sr-only">{{ __('pos.inventory.products.search') }}</label>
            <input
                id="q"
                name="q"
                type="search"
                value="{{ $term }}"
                placeholder="{{ __('pos.inventory.products.search_placeholder') }}"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.inventory.products.search') }}
            </button>
        </form>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="min-w-[760px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.products.product') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('pos.inventory.products.price') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.products.category.label') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('pos.inventory.products.stock') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('pos.inventory.products.min_stock') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.products.sellable') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.products.status') }}</th>
                        @if ($canEditProducts)
                            <th scope="col" class="px-4 py-3 text-right">{{ __('pos.inventory.products.actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr class="{{ $product->active ? '' : 'bg-slate-50' }}">
                            <td class="px-4 py-3">
                                <span class="block font-medium text-slate-900">{{ $product->name }}</span>
                                @if (filled($product->active_ingredient))
                                    <span class="block text-xs text-slate-500">{{ $product->active_ingredient }}</span>
                                @endif
                                <span class="block text-xs tabular-nums text-slate-400">
                                    {{ filled($product->barcode) ? $product->barcode : __('pos.inventory.products.without_barcode') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                {{ '$'.number_format($product->price, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ __('pos.inventory.products.category.'.$product->sale_category->value) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $product->stock }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $product->min_stock }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $product->sellable ? __('pos.inventory.products.yes') : __('pos.inventory.products.no') }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($product->active)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                        {{ __('pos.inventory.products.in_catalog') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                        {{ __('pos.inventory.products.out_of_catalog') }}
                                    </span>
                                @endif
                            </td>
                            @if ($canEditProducts)
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('inventory.products.edit', $product) }}" class="rounded text-sm font-semibold text-emerald-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                        {{ __('pos.inventory.products.edit') }}
                                    </a>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canEditProducts ? 8 : 7 }}" class="px-4 py-8 text-center text-slate-500">{{ __('pos.inventory.products.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $products->links() }}
    </div>
@endsection
