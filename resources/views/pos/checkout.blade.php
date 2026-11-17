@extends('layouts.pos')

@section('title', __('pos.checkout.title'))

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <h2 class="text-lg font-semibold">{{ __('pos.checkout.heading') }}</h2>

        <ul class="mt-4 divide-y divide-slate-100 text-sm">
            @foreach ($cart->lines() as $line)
                @php
                    $lineBasePesos = $calculator->lineSubtotal($line)->pesos();
                    $lineDiscountPesos = $calculator->lineDiscount($line)->pesos();
                @endphp
                <li class="flex items-start justify-between gap-2 py-2">
                    <span class="min-w-0">
                        {{ $line->quantity() }} × {{ $line->name() }}
                        @if ($lineDiscountPesos > 0)
                            <span class="block text-xs text-emerald-700">
                                {{ __('pos.checkout.line_discount', [
                                    'amount' => $money($lineDiscountPesos),
                                    'reason' => $line->discount()->reason,
                                ]) }}
                            </span>
                        @endif
                    </span>
                    <span class="whitespace-nowrap font-medium">{{ $money($lineBasePesos - $lineDiscountPesos) }}</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-3 space-y-1 border-t border-slate-200 pt-3 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-600">{{ __('pos.common.subtotal') }}</span>
                <span>{{ $money($totals->subtotal()->pesos()) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">{{ __('pos.common.discounts') }}</span>
                <span>-{{ $money($totals->discounts()->pesos()) }}</span>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between rounded-lg bg-slate-900 px-3 py-2.5 text-white">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __('pos.checkout.total_to_pay') }}</span>
            <span class="text-xl font-bold tabular-nums">{{ $money($totals->total()->pesos()) }}</span>
        </div>

        @if ($manualDiscount)
            <p class="mt-1 text-xs text-slate-500">
                {{ __('pos.checkout.manual_discount', [
                    'reason' => $manualDiscount->reason,
                    'amount' => $money($totals->globalDiscount()->pesos()),
                ]) }}
            </p>
        @endif

        @if ($stockChanges !== [])
            <div role="alert" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                <h3 class="text-sm font-semibold text-amber-900">
                    {{ __('pos.checkout.stock_changed_title') }}
                </h3>
                <ul class="mt-2 space-y-1 text-sm text-amber-900">
                    @foreach ($stockChanges as $change)
                        <li>
                            {{ __('pos.checkout.stock_changed_line', [
                                'name' => $change['name'],
                                'requested' => $change['requested'],
                                'previous' => $change['previous'],
                            ]) }}
                            <span class="font-semibold">{{ __('pos.checkout.stock_current', ['available' => $change['available']]) }}</span>
                        </li>
                    @endforeach
                </ul>
                <a
                    href="{{ route('pos.index') }}"
                    class="mt-3 inline-block rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                >
                    {{ __('pos.checkout.adjust') }}
                </a>
            </div>
        @endif

        <form method="POST" action="{{ route('pos.checkout.store') }}" class="mt-6 space-y-4">
            @csrf

            {{-- RF-14/RF-16: el carrito cambió y el descuento de línea dejó de estar dentro del tope. --}}
            @if ($errors->has('line_discount_supervisor') || $errors->has('line_value'))
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">{{ __('pos.checkout.line_discount_blocked_title') }}</p>
                    <p class="mt-1">
                        {{ $errors->first('line_discount_supervisor') ?: $errors->first('line_value') }}
                    </p>
                    <a href="{{ route('pos.index') }}" class="mt-3 inline-block font-semibold underline">
                        {{ __('pos.checkout.back') }}
                    </a>
                </div>
            @endif

            <fieldset>
                <legend class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.payment_method') }}</legend>
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

            <fieldset>
                <legend class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.document_type') }}</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-3 text-sm hover:border-emerald-500">
                        <input
                            type="radio"
                            name="document_type"
                            value="receipt"
                            class="text-emerald-600"
                            data-document-type
                            @checked(old('document_type', 'receipt') === 'receipt')
                        >
                        {{ __('pos.checkout.receipt') }}
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-3 text-sm hover:border-emerald-500">
                        <input
                            type="radio"
                            name="document_type"
                            value="invoice"
                            class="text-emerald-600"
                            data-document-type
                            @checked(old('document_type') === 'invoice')
                        >
                        {{ __('pos.checkout.invoice') }}
                    </label>
                </div>
            </fieldset>

            {{-- S-2: campos tributarios de la factura; el script los muestra al elegirla. --}}
            <fieldset
                data-invoice-fields
                class="rounded-lg border border-slate-300 bg-slate-50 p-4"
                @if (old('document_type') !== 'invoice') hidden @endif
            >
                <legend class="px-1 text-sm font-semibold text-slate-700">{{ __('pos.checkout.invoice_data') }}</legend>
                <p class="text-xs text-slate-500">{{ __('pos.checkout.invoice_help') }}</p>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="invoice_business_name" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.invoice_business_name') }}</label>
                        <input id="invoice_business_name" name="invoice_business_name" type="text" maxlength="255"
                               value="{{ old('invoice_business_name') }}"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('invoice_business_name')
                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="invoice_business_activity" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.invoice_business_activity') }}</label>
                        <input id="invoice_business_activity" name="invoice_business_activity" type="text" maxlength="255"
                               value="{{ old('invoice_business_activity') }}"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('invoice_business_activity')
                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="invoice_address" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.invoice_address') }}</label>
                        <input id="invoice_address" name="invoice_address" type="text" maxlength="255"
                               value="{{ old('invoice_address') }}"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('invoice_address')
                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="invoice_commune" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.invoice_commune') }}</label>
                        <input id="invoice_commune" name="invoice_commune" type="text" maxlength="100"
                               value="{{ old('invoice_commune') }}"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('invoice_commune')
                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </fieldset>

            @if ($requiresPrescription)
                <fieldset class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <legend class="px-1 text-sm font-semibold text-amber-900">{{ __('pos.checkout.prescription_title') }}</legend>
                    <p class="text-xs text-amber-900">
                        {{ __('pos.checkout.prescription_help') }}
                    </p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="prescription_number" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_number') }}</label>
                            <input id="prescription_number" name="prescription_number" type="text" maxlength="50"
                                   value="{{ old('prescription_number') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('prescription_number')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="prescription_issued_at" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_issued_at') }}</label>
                            <input id="prescription_issued_at" name="prescription_issued_at" type="date"
                                   value="{{ old('prescription_issued_at') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('prescription_issued_at')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="prescription_prescriber_name" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_prescriber_name') }}</label>
                            <input id="prescription_prescriber_name" name="prescription_prescriber_name" type="text" maxlength="255"
                                   value="{{ old('prescription_prescriber_name') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('prescription_prescriber_name')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="prescription_prescriber_rut" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_prescriber_rut') }}</label>
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

            @if ($errors->has('prescription_reuse') || $errors->has('discount_supervisor') || $errors->has('supervisor_email'))
                <fieldset class="rounded-lg border border-red-300 bg-red-50 p-4">
                    <legend class="px-1 text-sm font-semibold text-red-800">{{ __('pos.checkout.supervisor_title') }}</legend>
                    <p class="text-xs text-red-800">
                        {{ $errors->first('prescription_reuse') ?: $errors->first('discount_supervisor') ?: $errors->first('supervisor_email') }}
                    </p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="supervisor_email" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.supervisor_email') }}</label>
                            <input id="supervisor_email" name="supervisor_email" type="email"
                                   value="{{ old('supervisor_email') }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('supervisor_email')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="supervisor_password" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.supervisor_password') }}</label>
                            <input id="supervisor_password" name="supervisor_password" type="password"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                </fieldset>
            @endif

            <div>
                <label for="convention_id" class="text-sm font-semibold text-slate-700">
                    {{ __('pos.checkout.convention') }}
                </label>
                <select
                    id="convention_id"
                    name="convention_id"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                >
                    <option value="">{{ __('pos.checkout.no_convention') }}</option>
                    @foreach ($conventions as $convention)
                        <option value="{{ $convention->id }}" @selected((string) old('convention_id') === (string) $convention->id)>
                            {{ $convention->name }} ({{ rtrim(rtrim(number_format($convention->percent, 2, ',', '.'), '0'), ',') }}%)
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="received_amount" class="text-sm font-semibold text-slate-700">
                    {{ __('pos.checkout.received_amount') }}
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
                <p class="mt-1 text-xs text-slate-500">{{ __('pos.checkout.received_amount_help') }}</p>
            </div>

            <div>
                <label for="customer_rut" class="text-sm font-semibold text-slate-700">
                    {{ __('pos.checkout.customer_rut') }}
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
                    {{ __('pos.checkout.back') }}
                </a>
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-4 py-3 text-base font-semibold text-white hover:bg-emerald-700">
                    {{ __('pos.checkout.confirm') }}
                </button>
            </div>
        </form>
    </div>

    <script>
        // S-2: los datos de factura sólo se piden cuando el documento es factura.
        (() => {
            const fields = document.querySelector('[data-invoice-fields]');
            const radios = document.querySelectorAll('[data-document-type]');

            if (!fields || radios.length === 0) {
                return;
            }

            const sync = () => {
                const selected = document.querySelector('[data-document-type]:checked');
                fields.hidden = selected?.value !== 'invoice';
            };

            radios.forEach((radio) => radio.addEventListener('change', sync));
            sync();
        })();
    </script>
@endsection
