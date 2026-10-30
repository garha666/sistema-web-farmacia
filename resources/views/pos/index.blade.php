@extends('layouts.pos')

@section('title', 'Punto de Venta')

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <section aria-labelledby="search-title" class="rounded-xl bg-white p-4 shadow-sm">
            <h2 id="search-title" class="text-base font-semibold">Buscar producto</h2>

            <form method="GET" action="{{ route('pos.index') }}" class="mt-3">
                <label for="q" class="sr-only">Buscar producto</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $term }}"
                    autofocus
                    autocomplete="off"
                    placeholder="Buscar producto por nombre, principio activo o código de barras"
                    class="w-full rounded-lg border border-slate-300 px-4 py-3 text-base focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                >
            </form>

            <div class="mt-4 space-y-2">
                @if ($term !== '' && $results->isEmpty())
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Sin resultados para “{{ $term }}”.</p>
                @endif

                @foreach ($results as $product)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
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
                                <span class="text-slate-500">· stock {{ $product->stock }}</span>
                                @if ($product->sale_category->requiresPrescription())
                                    <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                        {{ $product->sale_category === \App\Pos\Enum\SaleCategory::controlled ? 'Controlado' : 'Receta' }}
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
                                Agregar
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>

        <aside aria-labelledby="cart-title" class="rounded-xl bg-white p-4 shadow-sm">
            <h2 id="cart-title" class="text-base font-semibold">Carrito</h2>

            @if ($cart->isEmpty())
                <p class="mt-3 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                    Carrito vacío. Busca un producto para comenzar.
                </p>
            @else
                <table class="mt-3 w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-2">Producto</th>
                            <th class="pb-2 text-right">Cant.</th>
                            <th class="pb-2 text-right">Subtotal</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($cart->lines() as $line)
                            <tr>
                                <td class="py-2">
                                    <p class="font-medium">{{ $line->name() }}</p>
                                    <p class="text-xs text-slate-500">{{ $money($line->unitPrice()->pesos()) }} c/u</p>
                                </td>
                                <td class="py-2 text-right align-middle">
                                    <form method="POST" action="{{ route('pos.cart.update', $line->productId()) }}" class="flex items-center justify-end gap-1">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="qty-{{ $line->productId() }}">Cantidad</label>
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
                                            Actualizar
                                        </button>
                                    </form>
                                </td>
                                <td class="py-2 text-right font-semibold">
                                    {{ $money($line->unitPrice()->pesos() * $line->quantity()) }}
                                </td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('pos.cart.remove', $line->productId()) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">
                                            Quitar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <dl class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-600">Subtotal</dt>
                        <dd>{{ $money($totals->subtotal()->pesos()) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-600">Descuentos</dt>
                        <dd>-{{ $money($totals->discounts()->pesos()) }}</dd>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500">
                        <dt>IVA incluido ({{ config('pos.iva_rate') }}%)</dt>
                        <dd>{{ $money($totals->iva()->pesos()) }}</dd>
                    </div>
                    <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 text-lg font-bold">
                        <dt>Total</dt>
                        <dd data-testid="cart-total">{{ $money($totals->total()->pesos()) }}</dd>
                    </div>
                </dl>
            @endif

            <button
                type="button"
                class="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-3 text-base font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                @disabled($cart->isEmpty())
            >
                Cobrar
            </button>

            @if ($cart->isEmpty())
                <p class="mt-2 text-center text-xs text-slate-500">El carrito está vacío</p>
            @endif
        </aside>
    </div>
@endsection
