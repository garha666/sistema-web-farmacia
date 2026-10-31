@extends('layouts.pos')

@section('title', 'Cobro')

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Cobro</h2>

        <ul class="mt-4 divide-y divide-slate-100 text-sm">
            @foreach ($cart->lines() as $line)
                <li class="flex justify-between py-2">
                    <span>{{ $line->quantity() }} × {{ $line->name() }}</span>
                    <span class="font-medium">{{ $money($line->unitPrice()->pesos() * $line->quantity()) }}</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 flex justify-between border-t border-slate-200 pt-3 text-lg font-bold">
            <span>Total a pagar</span>
            <span>{{ $money($totals->total()->pesos()) }}</span>
        </div>

        <form method="POST" action="{{ route('pos.checkout.store') }}" class="mt-6 space-y-4">
            @csrf

            <fieldset>
                <legend class="text-sm font-semibold text-slate-700">Medio de pago</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach ($methods as $method)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-3 text-sm hover:border-emerald-500">
                            <input
                                type="radio"
                                name="payment_method"
                                value="{{ $method->value }}"
                                class="text-emerald-600"
                                @checked($loop->first)
                            >
                            {{ $method->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label for="convention_id" class="text-sm font-semibold text-slate-700">
                    Convenio de salud (opcional)
                </label>
                <select
                    id="convention_id"
                    name="convention_id"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                >
                    <option value="">Sin convenio</option>
                    @foreach ($conventions as $convention)
                        <option value="{{ $convention->id }}" @selected((string) old('convention_id') === (string) $convention->id)>
                            {{ $convention->name }} ({{ rtrim(rtrim(number_format($convention->percent, 2, ',', '.'), '0'), ',') }}%)
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="received_amount" class="text-sm font-semibold text-slate-700">
                    Monto recibido (efectivo)
                </label>
                <input
                    id="received_amount"
                    name="received_amount"
                    type="number"
                    min="0"
                    step="1"
                    value="{{ old('received_amount') }}"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                >
                <p class="mt-1 text-xs text-slate-500">Obligatorio sólo si el pago es en efectivo.</p>
            </div>

            <div>
                <label for="customer_rut" class="text-sm font-semibold text-slate-700">
                    RUT del cliente (opcional)
                </label>
                <input
                    id="customer_rut"
                    name="customer_rut"
                    type="text"
                    maxlength="20"
                    value="{{ old('customer_rut') }}"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                >
            </div>

            <div class="flex gap-3">
                <a href="{{ route('pos.index') }}" class="rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">
                    Volver
                </a>
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-4 py-3 text-base font-semibold text-white hover:bg-emerald-700">
                    Confirmar venta
                </button>
            </div>
        </form>
    </div>
@endsection
