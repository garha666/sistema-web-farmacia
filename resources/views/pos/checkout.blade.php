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

            @if ($requiresPrescription)
                <fieldset class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <legend class="px-1 text-sm font-semibold text-amber-900">Receta médica</legend>
                    <p class="text-xs text-amber-900">
                        El carrito incluye medicamentos que requieren receta. Completa los datos antes de cobrar.
                    </p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="prescription_number" class="text-sm font-semibold text-slate-700">Número de receta</label>
                            <input id="prescription_number" name="prescription_number" type="text" maxlength="50"
                                   value="{{ old('prescription_number') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('prescription_number')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="prescription_issued_at" class="text-sm font-semibold text-slate-700">Fecha de emisión</label>
                            <input id="prescription_issued_at" name="prescription_issued_at" type="date"
                                   value="{{ old('prescription_issued_at') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('prescription_issued_at')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="prescription_prescriber_name" class="text-sm font-semibold text-slate-700">Nombre del prescriptor</label>
                            <input id="prescription_prescriber_name" name="prescription_prescriber_name" type="text" maxlength="255"
                                   value="{{ old('prescription_prescriber_name') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('prescription_prescriber_name')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="prescription_prescriber_rut" class="text-sm font-semibold text-slate-700">RUT del prescriptor</label>
                            <input id="prescription_prescriber_rut" name="prescription_prescriber_rut" type="text" maxlength="20"
                                   value="{{ old('prescription_prescriber_rut') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('prescription_prescriber_rut')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </fieldset>
            @endif

            @if ($errors->has('prescription_reuse'))
                <fieldset class="rounded-lg border border-red-300 bg-red-50 p-4">
                    <legend class="px-1 text-sm font-semibold text-red-800">Autorización de supervisor</legend>
                    <p class="text-xs text-red-800">{{ $errors->first('prescription_reuse') }}</p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="supervisor_email" class="text-sm font-semibold text-slate-700">Correo del supervisor</label>
                            <input id="supervisor_email" name="supervisor_email" type="email"
                                   value="{{ old('supervisor_email') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('supervisor_email')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="supervisor_password" class="text-sm font-semibold text-slate-700">Contraseña</label>
                            <input id="supervisor_password" name="supervisor_password" type="password"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                </fieldset>
            @endif

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
