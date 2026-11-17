@extends('layouts.pos')

@section('title', __('pos.cash.title'))

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <section aria-labelledby="search-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
            <h2 id="search-title" class="text-base font-semibold">{{ __('pos.cash.search') }}</h2>

            <form method="GET" action="{{ route('pos.index') }}" class="relative mt-3">
                <label for="q" class="sr-only">{{ __('pos.cash.search') }}</label>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400">
                    <path d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                </svg>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $term }}"
                    autofocus
                    autocomplete="off"
                    placeholder="{{ __('pos.cash.search_placeholder') }}"
                    class="w-full rounded-lg border border-slate-300 py-3 pl-10 pr-4 text-base transition-colors focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                >
            </form>

            <div class="mt-4 space-y-2">
                @if ($term !== '' && $results->isEmpty())
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                        {{ __('pos.cash.no_results', ['term' => $term]) }}
                    </p>
                @endif

                @foreach ($results as $product)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 transition-colors hover:border-emerald-200 hover:bg-emerald-50/40">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $product->name }}</p>
                            <p class="text-sm text-slate-500">
                                {{ $product->active_ingredient }}
                                @if ($product->barcode)
                                    · {{ $product->barcode }}
                                @endif
                            </p>
                            <p class="mt-1 text-sm">
                                <span class="font-semibold">{{ $money($product->price) }}</span>
                                <span class="text-slate-500">· {{ __('pos.cash.stock', ['stock' => $product->stock]) }}</span>
                                @if ($product->sale_category->requiresPrescription())
                                    <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                        {{ $product->sale_category === \App\Pos\Enum\SaleCategory::controlled
                                            ? __('pos.cash.controlled')
                                            : __('pos.cash.prescription') }}
                                    </span>
                                @endif
                            </p>
                        </div>

                        <form method="POST" action="{{ route('pos.cart.add') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button
                                type="submit"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:opacity-50"
                                @disabled($product->stock < 1)
                            >
                                {{ __('pos.cash.add') }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>

        <aside aria-labelledby="cart-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 lg:sticky lg:top-24">
            <h2 id="cart-title" class="text-base font-semibold">{{ __('pos.cash.cart') }}</h2>

            @if ($cart->isEmpty())
                <p class="mt-3 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                    {{ __('pos.cash.empty') }}
                </p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @foreach ($cart->lines() as $line)
                        @php
                            $lineBasePesos = $calculator->lineSubtotal($line)->pesos();
                            $lineDiscountPesos = $calculator->lineDiscount($line)->pesos();
                            $lineTotalPesos = $lineBasePesos - $lineDiscountPesos;
                        @endphp
                        <li class="py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $line->name() }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ __('pos.common.per_unit', ['price' => $money($line->unitPrice()->pesos())]) }}
                                    </p>
                                </div>
                                <p class="whitespace-nowrap font-semibold">
                                    {{ $money($lineTotalPesos) }}
                                </p>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                <form method="POST" action="{{ route('pos.cart.update', $line->productId()) }}" class="flex items-center gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="qty-{{ $line->productId() }}">{{ __('pos.cash.quantity') }}</label>
                                    <input
                                        id="qty-{{ $line->productId() }}"
                                        name="quantity"
                                        type="number"
                                        min="1"
                                        max="{{ $line->product->availableStock }}"
                                        value="{{ $line->quantity() }}"
                                        class="w-16 rounded border border-slate-300 px-2 py-1 text-right"
                                    >
                                    <button type="submit" class="rounded bg-slate-200 px-2 py-1 text-xs font-semibold hover:bg-slate-300">
                                        {{ __('pos.cash.update') }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('pos.cart.remove', $line->productId()) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">
                                        {{ __('pos.cash.remove') }}
                                    </button>
                                </form>
                            </div>

                            @if ($line->discount() !== null)
                                <div class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-emerald-50 px-2 py-1 text-xs transition-colors duration-200">
                                    <span class="flex min-w-0 items-center gap-1 font-semibold text-emerald-800">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0">
                                            <path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm2.53 5.03a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06L8 10.5l3.47-3.47a.75.75 0 0 1 1.06 0Z" />
                                        </svg>
                                        <span class="truncate">
                                            @if ($line->discount()->type === \App\Pos\Enum\DiscountType::percent)
                                                -{{ rtrim(rtrim(number_format($line->discount()->value, 2, ',', '.'), '0'), ',') }}%
                                            @else
                                                -{{ $money($lineDiscountPesos) }}
                                            @endif
                                            · {{ $line->discount()->reason }}
                                        </span>
                                    </span>
                                    <form method="POST" action="{{ route('pos.cart.discount.remove', $line->productId()) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="shrink-0 font-semibold text-red-700 underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-red-300 rounded" aria-label="{{ __('pos.cash.line_discount.remove') }}: {{ $line->name() }}">
                                            {{ __('pos.cash.line_discount.remove') }}
                                        </button>
                                    </form>
                                </div>
                            @else
                                <details class="group mt-2 rounded-lg border border-slate-200 bg-slate-50/70 open:bg-white" @if ((int) old('line_product_id') === $line->productId() && ($errors->has('line_reason') || $errors->has('line_value'))) open @endif>
                                    <summary class="flex cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform duration-200 group-open:rotate-90">
                                            <path d="M7.72 4.47a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L12.19 10 7.72 5.53a.75.75 0 0 1 0-1.06Z" />
                                        </svg>
                                        {{ __('pos.cash.line_discount.title') }}
                                    </summary>

                                    <form method="POST" action="{{ route('pos.cart.discount', $line->productId()) }}" class="space-y-2 border-t border-slate-200 p-2">
                                        @csrf
                                        <input type="hidden" name="line_product_id" value="{{ $line->productId() }}">

                                        <div class="grid grid-cols-[5.5rem_minmax(0,1fr)] items-center gap-2">
                                            <label class="text-xs font-medium text-slate-700" for="line-type-{{ $line->productId() }}">
                                                {{ __('pos.cash.discount.type') }}
                                            </label>
                                            <select
                                                id="line-type-{{ $line->productId() }}"
                                                name="line_type"
                                                class="w-full rounded border border-slate-300 bg-white px-2 py-1 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                            >
                                                <option value="percent" @selected(old('line_type') === 'percent')>{{ __('pos.cash.line_discount.percent') }}</option>
                                                <option value="amount" @selected(old('line_type') === 'amount')>{{ __('pos.cash.line_discount.amount') }}</option>
                                            </select>

                                            <label class="text-xs font-medium text-slate-700" for="line-value-{{ $line->productId() }}">
                                                {{ __('pos.cash.discount.value') }}
                                            </label>
                                            <input
                                                id="line-value-{{ $line->productId() }}"
                                                name="line_value"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                inputmode="decimal"
                                                value="{{ (int) old('line_product_id') === $line->productId() ? old('line_value') : '' }}"
                                                placeholder="{{ __('pos.cash.line_discount.value_placeholder') }}"
                                                class="w-full rounded border border-slate-300 px-2 py-1 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                            >

                                            <label class="text-xs font-medium text-slate-700" for="line-reason-{{ $line->productId() }}">
                                                {{ __('pos.cash.discount.reason_short') }}
                                            </label>
                                            <input
                                                id="line-reason-{{ $line->productId() }}"
                                                name="line_reason"
                                                type="text"
                                                maxlength="255"
                                                value="{{ (int) old('line_product_id') === $line->productId() ? old('line_reason') : '' }}"
                                                placeholder="{{ __('pos.cash.line_discount.reason_placeholder') }}"
                                                class="w-full rounded border border-slate-300 px-2 py-1 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                            >
                                        </div>

                                        @error('line_reason')
                                            <p class="text-xs text-red-700">{{ $message }}</p>
                                        @enderror
                                        @error('line_value')
                                            <p class="text-xs text-red-700">{{ $message }}</p>
                                        @enderror

                                        <button type="submit" class="w-full rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                            {{ __('pos.cash.line_discount.apply') }}
                                        </button>
                                    </form>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-600">{{ __('pos.common.subtotal') }}</dt>
                        <dd>{{ $money($totals->subtotal()->pesos()) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-600">{{ __('pos.common.discounts') }}</dt>
                        <dd>-{{ $money($totals->discounts()->pesos()) }}</dd>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500">
                        <dt>{{ __('pos.cash.iva_included', ['rate' => config('pos.iva_rate')]) }}</dt>
                        <dd>{{ $money($totals->iva()->pesos()) }}</dd>
                    </div>
                    <div class="mt-3 flex items-center justify-between rounded-lg bg-slate-900 px-3 py-2.5 text-white">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __('pos.common.total') }}</dt>
                        <dd class="text-xl font-bold tabular-nums" data-testid="cart-total">{{ $money($totals->total()->pesos()) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-700">{{ __('pos.cash.discount.title') }}</h3>

                    @if ($manualDiscount)
                        <p class="mt-2 text-sm text-slate-600">
                            @if ($manualDiscount->type === \App\Pos\Enum\DiscountType::percent)
                                {{ rtrim(rtrim(number_format($manualDiscount->value, 2, ',', '.'), '0'), ',') }}%
                            @else
                                {{ $money((int) $manualDiscount->value) }}
                            @endif
                            · {{ __('pos.cash.discount.reason_short') }}:
                            <span class="font-medium">{{ $manualDiscount->reason }}</span>
                        </p>

                        <form method="POST" action="{{ route('pos.discount.remove') }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">
                                {{ __('pos.cash.discount.remove') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('pos.discount.apply') }}" class="mt-2 space-y-2">
                            @csrf

                            <div class="flex gap-2">
                                <label class="sr-only" for="discount_type">{{ __('pos.cash.discount.type') }}</label>
                                <select
                                    id="discount_type"
                                    name="discount_type"
                                    class="w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm"
                                >
                                    <option value="percent" @selected(old('discount_type') === 'percent')>%</option>
                                    <option value="amount" @selected(old('discount_type') === 'amount')>$</option>
                                </select>

                                <label class="sr-only" for="discount_value">{{ __('pos.cash.discount.value') }}</label>
                                <input
                                    id="discount_value"
                                    name="discount_value"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value="{{ old('discount_value') }}"
                                    placeholder="{{ __('pos.cash.discount.value_placeholder') }}"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >
                            </div>

                            <label class="sr-only" for="discount_reason">{{ __('pos.cash.discount.reason') }}</label>
                            <input
                                id="discount_reason"
                                name="discount_reason"
                                type="text"
                                maxlength="255"
                                value="{{ old('discount_reason') }}"
                                placeholder="{{ __('pos.cash.discount.reason_placeholder') }}"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >

                            @error('discount_reason')
                                <p class="text-xs text-red-700">{{ $message }}</p>
                            @enderror
                            @error('discount_value')
                                <p class="text-xs text-red-700">{{ $message }}</p>
                            @enderror
                            @error('discount_supervisor')
                                <p class="text-xs text-red-700">{{ $message }}</p>
                            @enderror

                            <button type="submit" class="w-full rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                {{ __('pos.cash.discount.apply') }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <a
                href="{{ route('pos.checkout') }}"
                class="mt-4 block w-full rounded-lg bg-emerald-600 px-4 py-3 text-center text-base font-semibold text-white shadow-sm shadow-emerald-900/20 transition-colors hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 aria-disabled:pointer-events-none aria-disabled:bg-slate-300 aria-disabled:shadow-none"
                @if ($cart->isEmpty()) aria-disabled="true" tabindex="-1" @endif
            >
                {{ __('pos.cash.charge') }}
            </a>

            @if ($cart->isEmpty())
                <p class="mt-2 text-center text-xs text-slate-500">{{ __('pos.cash.empty_short') }}</p>
            @endif
        </aside>
    </div>

    @if ($errors->has('discount_supervisor'))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="discount-supervisor-title"
                class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
            >
                <h2 id="discount-supervisor-title" class="text-lg font-semibold">{{ __('pos.cash.discount.supervisor_title') }}</h2>
                <p class="mt-2 text-sm text-red-700">{{ $errors->first('discount_supervisor') }}</p>

                <form method="POST" action="{{ route('pos.discount.apply') }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="discount_type" value="{{ old('discount_type', 'percent') }}">
                    <input type="hidden" name="discount_value" value="{{ old('discount_value') }}">
                    <input type="hidden" name="discount_reason" value="{{ old('discount_reason') }}">

                    <div>
                        <label for="discount_supervisor_email" class="text-sm font-semibold text-slate-700">
                            {{ __('pos.cash.discount.supervisor_email') }}
                        </label>
                        <input
                            id="discount_supervisor_email"
                            name="supervisor_email"
                            type="email"
                            value="{{ old('supervisor_email') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                        @error('supervisor_email')
                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="discount_supervisor_password" class="text-sm font-semibold text-slate-700">
                            {{ __('pos.cash.discount.supervisor_password') }}
                        </label>
                        <input
                            id="discount_supervisor_password"
                            name="supervisor_password"
                            type="password"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <a href="{{ route('pos.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            {{ __('pos.cash.discount.cancel') }}
                        </a>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                            {{ __('pos.cash.discount.authorize') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($errors->has('line_discount_supervisor') && old('line_product_id'))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="line-discount-supervisor-title"
                class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
            >
                <h2 id="line-discount-supervisor-title" class="text-lg font-semibold">{{ __('pos.cash.discount.supervisor_title') }}</h2>
                <p class="mt-2 text-sm text-red-700">{{ $errors->first('line_discount_supervisor') }}</p>

                <form method="POST" action="{{ route('pos.cart.discount', ['product' => old('line_product_id')]) }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="line_product_id" value="{{ old('line_product_id') }}">
                    <input type="hidden" name="line_type" value="{{ old('line_type', 'percent') }}">
                    <input type="hidden" name="line_value" value="{{ old('line_value') }}">
                    <input type="hidden" name="line_reason" value="{{ old('line_reason') }}">

                    <div>
                        <label for="line_supervisor_email" class="text-sm font-semibold text-slate-700">
                            {{ __('pos.cash.discount.supervisor_email') }}
                        </label>
                        <input
                            id="line_supervisor_email"
                            name="line_supervisor_email"
                            type="email"
                            value="{{ old('line_supervisor_email') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                        @error('line_supervisor_email')
                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="line_supervisor_password" class="text-sm font-semibold text-slate-700">
                            {{ __('pos.cash.discount.supervisor_password') }}
                        </label>
                        <input
                            id="line_supervisor_password"
                            name="line_supervisor_password"
                            type="password"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <a href="{{ route('pos.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            {{ __('pos.cash.discount.cancel') }}
                        </a>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                            {{ __('pos.cash.discount.authorize') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
