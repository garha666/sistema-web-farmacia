@extends('layouts.admin')

@section('title', __('pos.panel.bodeguero.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'bodeguero'])

        @if (session('success'))
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ session('success') }}
            </p>
        @endif

        {{-- RF-63: activos, bajo mínimo, sin stock y movimientos del día. --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="warehouse-summary">
            <h2 id="warehouse-summary" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.bodeguero.summary_heading') }}</h2>

            <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 p-4">
                    <dt class="text-sm text-slate-500">{{ __('pos.panel.bodeguero.active_count') }}</dt>
                    <dd id="inventory-active" class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $summary->active() }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 p-4">
                    <dt class="text-sm text-slate-500">{{ __('pos.panel.bodeguero.below_minimum') }}</dt>
                    <dd id="inventory-below" class="mt-1 text-2xl font-semibold tabular-nums text-amber-700">{{ $summary->belowMinimum() }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 p-4">
                    <dt class="text-sm text-slate-500">{{ __('pos.panel.bodeguero.out_of_stock') }}</dt>
                    <dd id="inventory-out" class="mt-1 text-2xl font-semibold tabular-nums text-red-700">{{ $summary->outOfStock() }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 p-4">
                    <dt class="text-sm text-slate-500">{{ __('pos.panel.bodeguero.movements_today') }}</dt>
                    <dd id="inventory-movements" class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $summary->movementsToday() }}</dd>
                </div>
            </dl>
        </section>

        {{-- RF-66, RNF-18: ajuste por teclado con cantidad y motivo. --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="warehouse-adjustment">
            <h2 id="warehouse-adjustment" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.bodeguero.adjustment_heading') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.panel.bodeguero.adjustment_lead') }}</p>

            @if ($errors->any())
                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="mt-4 grid gap-4 lg:grid-cols-2">
                @csrf

                <div class="lg:col-span-2">
                    <label for="product_id" class="text-sm font-semibold text-slate-700">{{ __('pos.panel.bodeguero.form_product') }}</label>
                    <select
                        id="product_id"
                        name="product_id"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                        <option value="">{{ __('pos.panel.bodeguero.form_product_placeholder') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                                {{ $product->name }} — {{ $product->stock }} {{ __('pos.inventory.unit') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="type" class="text-sm font-semibold text-slate-700">{{ __('pos.panel.bodeguero.form_type') }}</label>
                    <select id="type" name="type" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="in" @selected(old('type') === 'in')>{{ __('pos.inventory.movement_type.in') }}</option>
                        <option value="out" @selected(old('type') === 'out')>{{ __('pos.inventory.movement_type.out') }}</option>
                    </select>
                </div>

                <div>
                    <label for="quantity" class="text-sm font-semibold text-slate-700">{{ __('pos.panel.bodeguero.form_quantity') }}</label>
                    <input
                        id="quantity"
                        name="quantity"
                        type="number"
                        min="1"
                        step="1"
                        required
                        value="{{ old('quantity') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 tabular-nums"
                    >
                </div>

                <div class="lg:col-span-2">
                    <label for="reason" class="text-sm font-semibold text-slate-700">{{ __('pos.panel.bodeguero.form_reason') }}</label>
                    <input
                        id="reason"
                        name="reason"
                        type="text"
                        maxlength="500"
                        required
                        value="{{ old('reason') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

                <div class="lg:col-span-2">
                    <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto">
                        {{ __('pos.panel.bodeguero.form_submit') }}
                    </button>
                </div>
            </form>
        </section>

        {{-- RF-64: prioridad de reposición, del menor al mayor stock. --}}
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="warehouse-replenishment">
            <h2 id="warehouse-replenishment" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.bodeguero.replenishment_heading') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.panel.bodeguero.replenishment_lead') }}</p>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-2 py-2">{{ __('pos.inventory.movements.product') }}</th>
                            <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.bodeguero.stock_current') }}</th>
                            <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.bodeguero.min_stock') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($replenishment as $product)
                            <tr>
                                <td class="px-2 py-2 font-medium text-slate-800">{{ $product->name }}</td>
                                <td class="px-2 py-2 text-right tabular-nums text-amber-700">{{ $product->stock }} {{ __('pos.inventory.unit') }}</td>
                                <td class="px-2 py-2 text-right tabular-nums text-slate-600">{{ $product->min_stock }} {{ __('pos.inventory.unit') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-2 py-6 text-center text-slate-500">{{ __('pos.panel.bodeguero.replenishment_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- RF-65: últimos movimientos con producto, tipo, motivo, autor y fecha. --}}
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="warehouse-movements">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <h2 id="warehouse-movements" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.bodeguero.movements_heading') }}</h2>
                <a href="{{ route('inventory.movements.index') }}" class="rounded text-sm font-semibold text-emerald-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.panel.bodeguero.movements_link') }}
                </a>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="hidden px-2 py-2 sm:table-cell">{{ __('pos.inventory.movements.date') }}</th>
                            <th scope="col" class="px-2 py-2">{{ __('pos.inventory.movements.product') }}</th>
                            <th scope="col" class="px-2 py-2">{{ __('pos.inventory.movements.type') }}</th>
                            <th scope="col" class="px-2 py-2 text-right">{{ __('pos.inventory.movements.quantity') }}</th>
                            <th scope="col" class="hidden px-2 py-2 sm:table-cell">{{ __('pos.inventory.movements.reason') }}</th>
                            <th scope="col" class="px-2 py-2">{{ __('pos.inventory.movements.author') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="hidden px-2 py-2 tabular-nums text-slate-600 sm:table-cell">{{ $movement->created_at?->format('d-m-Y H:i') }}</td>
                                <td class="px-2 py-2 font-medium text-slate-800">{{ $movement->product?->name ?? __('pos.common.none') }}</td>
                                <td class="px-2 py-2 text-slate-600">{{ $movement->type->label() }}</td>
                                <td class="px-2 py-2 text-right tabular-nums {{ $movement->quantity < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $movement->quantity }}</td>
                                <td class="hidden px-2 py-2 text-slate-700 sm:table-cell">{{ filled($movement->note) ? $movement->note : __('pos.common.none') }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ $movement->user?->name ?? __('pos.common.none') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-2 py-6 text-center text-slate-500">{{ __('pos.panel.bodeguero.movements_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- RF-67: catálogo y libro de controlados en modo lectura. --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="warehouse-accesses">
            <h2 id="warehouse-accesses" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.bodeguero.accesses_heading') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.panel.bodeguero.accesses_lead') }}</p>

            <div class="mt-3 flex flex-wrap gap-3">
                <a href="{{ route('inventory.products.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.panel.bodeguero.catalog_link') }}
                </a>
                <a href="{{ route('admin.controlled-register.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.panel.bodeguero.controlled_link') }}
                </a>
            </div>
        </section>
    </div>
@endsection
