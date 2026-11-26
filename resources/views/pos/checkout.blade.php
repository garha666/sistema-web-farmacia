@extends('layouts.pos')

@section('title', __('pos.checkout.title'))

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div class="mx-auto max-w-7xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ __('pos.checkout.heading') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('pos.checkout.subtitle') }}</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('pos.checkout.academic_badge') }}</span>
        </div>

        @if ($stockChanges !== [])
            <div role="alert" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4">
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

        @if ($openRegister === null)
            {{-- RF-60: sin caja abierta no se puede cobrar; se indica cómo abrirla. --}}
            <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                <p class="font-semibold">{{ __('pos.cash_register.open_warning') }}</p>
                <a href="{{ route('dashboard.tecnico') }}" class="mt-3 inline-block rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">
                    {{ __('pos.cash_register.open_title') }}
                </a>
            </div>
        @else
        <form method="POST" action="{{ route('pos.checkout.store') }}" data-checkout-form data-preview-url="{{ route('pos.checkout.preview') }}" data-loading="{{ __('pos.checkout.benefit_loading') }}" data-preview-error="{{ __('pos.checkout.benefit_preview_error') }}" data-no-benefit="{{ __('pos.checkout.benefit_none_total', ['total' => ':total']) }}" data-unselected="{{ __('pos.checkout.benefit_unselected_total', ['total' => ':total']) }}" data-selected="{{ __('pos.checkout.benefit_selected', ['name' => ':name', 'amount' => ':amount']) }}" data-confirm="{{ __('pos.checkout.confirm_amount', ['total' => ':total']) }}" data-change="{{ __('pos.checkout.change_due', ['amount' => ':amount']) }}" data-short="{{ __('pos.checkout.change_short', ['amount' => ':amount']) }}" class="mt-6 grid gap-6 lg:grid-cols-12 lg:items-start">
            @csrf

            <div class="min-w-0 space-y-6 lg:col-span-8">
                {{-- RF-14/RF-16: el carrito cambió y el descuento de línea dejó de estar dentro del tope. --}}
                @if ($errors->has('line_discount_supervisor') || $errors->has('line_value'))
                    <div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                        <p class="font-semibold">{{ __('pos.checkout.line_discount_blocked_title') }}</p>
                        <p class="mt-1">
                            {{ $errors->first('line_discount_supervisor') ?: $errors->first('line_value') }}
                        </p>
                        <a href="{{ route('pos.index') }}" class="mt-3 inline-block font-semibold underline">
                            {{ __('pos.checkout.back') }}
                        </a>
                    </div>
                @endif

                {{-- Tarjeta 1: cliente, RUT, convenio y alta de afiliación. --}}
                <section aria-labelledby="client-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-5">
                    <h3 id="client-title" class="text-sm font-semibold text-slate-900">{{ __('pos.checkout.client_title') }}</h3>
                    <p id="benefit-hint" class="mt-1 text-sm text-slate-600">{{ __('pos.checkout.benefit_hint') }}</p>

                    <div class="mt-3 flex flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="customer_rut" class="block text-sm font-medium">{{ __('pos.checkout.customer_rut') }}</label>
                            <input
                                id="customer_rut"
                                name="customer_rut"
                                type="text"
                                maxlength="20"
                                autocomplete="off"
                                value="{{ old('customer_rut', $benefitRut) }}"
                                aria-describedby="benefit-hint rut-invoice-hint"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700"
                            >
                        </div>
                        <button
                            type="submit"
                            formaction="{{ route('pos.checkout.benefit') }}"
                            formnovalidate
                            data-benefit-lookup
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:border-emerald-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >{{ __('pos.checkout.benefit_lookup') }}</button>
                    </div>
                    <p id="rut-invoice-hint" data-rut-invoice-hint role="alert" hidden class="mt-2 text-sm font-medium text-amber-800">{{ __('pos.checkout.invoice_rut_required') }}</p>
                    @error('benefit_rut')<p role="alert" class="mt-2 text-sm text-rose-800">{{ $message }}</p>@enderror
                    @error('customer_rut')<p role="alert" class="mt-2 text-sm text-rose-800">{{ $message }}</p>@enderror
                    @if ($benefitRut !== null)
                        <p role="status" class="mt-2 text-sm text-slate-700">
                            {{ $selected?->name ?? __($conventions->isEmpty() ? 'pos.checkout.benefit_none' : 'pos.checkout.benefit_found') }}
                        </p>
                    @endif

                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <label for="convention_id" class="text-sm font-semibold text-slate-700">
                            {{ __('pos.checkout.convention') }}
                        </label>
                        <p id="convention-hint" class="mt-1 text-xs text-slate-600">{{ __('pos.checkout.benefit_select_help') }}</p>
                        <select
                            id="convention_id"
                            name="convention_id"
                            aria-describedby="convention-hint" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700"
                        >
                            <option value="">{{ __('pos.checkout.no_convention') }}</option>
                            @foreach ($conventions as $convention)
                                <option value="{{ $convention->id }}" data-benefit-option="{{ $convention->id }}" @selected($selected?->id === $convention->id)>
                                    {{ $convention->name }} ({{ rtrim(rtrim(number_format($convention->percent, 2, ',', '.'), '0'), ',') }}%)
                                </option>
                            @endforeach
                        </select>
                        @error('convention_id')<p role="alert" class="mt-2 text-sm text-rose-800">{{ $message }}</p>@enderror
                        <p role="status" aria-live="polite" data-preview-status class="mt-2 text-sm font-medium text-slate-700">{{ $selected ? __('pos.checkout.benefit_selected', ['name' => $selected->name, 'amount' => $money($totals->globalDiscount()->pesos())]) : __($benefitRut !== null && $conventions->isEmpty() ? 'pos.checkout.benefit_none_total' : 'pos.checkout.benefit_unselected_total', ['total' => $money($totals->total()->pesos())]) }}</p>
                    </div>

                    @if (auth()->user()->role === \App\Models\User::ROLE_TECNICO)
                        @if (session('membership_registered'))
                            <p class="mt-3 text-sm text-emerald-800" role="status">{{ __('pos.authorization.registered') }}</p>
                        @endif
                        @if ($membershipConventions->isNotEmpty() || $errors->membership->any())
                            <details @if ($errors->membership->any()) open @endif class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <summary class="cursor-pointer text-sm font-semibold">{{ __('pos.authorization.membership') }}</summary>
                                <p class="mt-2 text-sm text-slate-600">{{ __('pos.authorization.membership_help') }}</p>
                                <div class="mt-3 space-y-3">
                                    @foreach (['membership_rut' => 'text', 'membership_name' => 'text', 'membership_convention' => 'select', 'supervisor_email' => 'email', 'supervisor_password' => 'password'] as $field => $type)
                                        <div>
                                            <label for="membership-{{ $field }}" class="block text-sm font-medium">{{ __('pos.authorization.'.$field) }}</label>
                                            @if ($type === 'select')
                                                <select id="membership-{{ $field }}" name="{{ $field }}" form="membership-form" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                                    <option value="">{{ __('pos.authorization.choose_convention') }}</option>
                                                    @foreach ($membershipConventions as $convention)
                                                        <option value="{{ $convention->id }}" @selected((string) old($field) === (string) $convention->id)>{{ $convention->name }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input id="membership-{{ $field }}" name="{{ $field }}" form="membership-form" type="{{ $type }}" @if ($type !== 'password') value="{{ old($field, $field === 'membership_rut' ? $benefitRut : '') }}" @endif autocomplete="off" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                            @endif
                                            @error($field, 'membership')<p class="mt-1 text-sm text-rose-800" role="alert">{{ $message }}</p>@enderror
                                        </div>
                                    @endforeach
                                    <button type="submit" form="membership-form" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ __('pos.authorization.register') }}</button>
                                </div>
                            </details>
                        @endif
                    @endif
                </section>

                {{-- Tarjeta 2: receta y autorización clínica. --}}
                @if ($requiresPrescription)
                    @php
                        $reviewFields = $prescriptionReview?->snapshot['prescription'] ?? [];
                    @endphp
                    <section aria-labelledby="rx-title" class="rounded-xl border border-amber-300 bg-white p-4 shadow-sm ring-1 ring-amber-100 sm:p-5">
                        <h3 id="rx-title" class="text-sm font-semibold text-amber-900">{{ __('pos.checkout.prescription_title') }}</h3>
                        <p class="mt-1 text-xs text-amber-900">
                            {{ __('pos.checkout.prescription_help') }}
                        </p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="prescription_number" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_number') }}</label>
                                <input id="prescription_number" name="prescription_number" type="text" maxlength="50"
                                       value="{{ old('prescription_number', $reviewFields['prescription_number'] ?? '') }}"
                                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('prescription_number')
                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="prescription_issued_at" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_issued_at') }}</label>
                                <input id="prescription_issued_at" name="prescription_issued_at" type="date"
                                       value="{{ old('prescription_issued_at', $reviewFields['prescription_issued_at'] ?? '') }}"
                                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('prescription_issued_at')
                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="prescription_prescriber_name" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_prescriber_name') }}</label>
                                <input id="prescription_prescriber_name" name="prescription_prescriber_name" type="text" maxlength="255"
                                       value="{{ old('prescription_prescriber_name', $reviewFields['prescription_prescriber_name'] ?? '') }}"
                                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('prescription_prescriber_name')
                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="prescription_prescriber_rut" class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.prescription_prescriber_rut') }}</label>
                                <input id="prescription_prescriber_rut" name="prescription_prescriber_rut" type="text" maxlength="20"
                                       value="{{ old('prescription_prescriber_rut', $reviewFields['prescription_prescriber_rut'] ?? '') }}"
                                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('prescription_prescriber_rut')
                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 space-y-3 border-t border-amber-200 pt-4">
                            <h4 class="text-sm font-semibold text-amber-900">{{ __('pos.rx_review.title') }}</h4>
                            <p class="text-sm">{{ __('pos.rx_review.intro') }}</p>
                            @if ($prescriptionReview)
                                <p role="status" class="text-sm font-semibold">{{ __($prescriptionReview->pharmacist_id ? 'pos.rx_review.approved' : 'pos.rx_review.pending') }}</p>
                            @endif
                            @error('prescription_approval')<p role="alert" class="text-sm text-rose-800">{{ $message }}</p>@enderror
                            <button type="submit" name="request_review" value="1" formnovalidate class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-semibold">{{ __('pos.rx_review.send') }}</button>

                            <details @if ($errors->has('chemist_email') || $errors->has('pharmacist_pin')) open @endif class="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                                <summary class="cursor-pointer text-sm font-semibold text-amber-900">{{ __('pos.rx_review.authorize_pin') }}</summary>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="chemist_email" class="block text-sm font-medium">{{ __('pos.rx_review.chemist_email') }}</label>
                                        <select id="chemist_email" name="chemist_email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                            <option value="">{{ __('pos.rx_review.chemist_choose') }}</option>
                                            @foreach ($chemists as $chemist)
                                                <option value="{{ $chemist->email }}" @selected(old('chemist_email') === $chemist->email)>{{ $chemist->name }} — {{ $chemist->email }}</option>
                                            @endforeach
                                        </select>
                                        @error('chemist_email')<p role="alert" class="text-sm text-rose-800">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="pharmacist_pin" class="block text-sm font-medium">{{ __('pos.rx_review.pharmacist_pin') }}</label>
                                        <input id="pharmacist_pin" name="pharmacist_pin" type="password" inputmode="numeric" pattern="[0-9]{8,12}" maxlength="12" autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                        @error('pharmacist_pin')<p role="alert" class="text-sm text-rose-800">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </details>
                        </div>
                    </section>
                @endif

                {{-- Tarjeta 3: documento tributario y pago. --}}
                <section aria-labelledby="document-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-5">
                    <h3 id="document-title" class="text-sm font-semibold text-slate-900">{{ __('pos.checkout.document_and_payment') }}</h3>

                    <fieldset class="mt-3">
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
                        class="mt-3 rounded-lg border border-slate-300 bg-slate-50 p-4"
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

                    <fieldset class="mt-4">
                        <legend class="text-sm font-semibold text-slate-700">{{ __('pos.checkout.payment_method') }}</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ($methods as $method)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-3 text-sm hover:border-emerald-500">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="{{ $method->value }}"
                                        data-payment-method
                                        class="text-emerald-600"
                                        @checked(old('payment_method') === $method->value || (old('payment_method') === null && $loop->first))
                                    >
                                    {{ $method->label() }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="mt-4" data-received-block>
                        <label for="received_amount" class="text-sm font-semibold text-slate-700">
                            {{ __('pos.checkout.received_amount') }}
                        </label>
                        <input
                            id="received_amount"
                            name="received_amount"
                            type="number"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            value="{{ old('received_amount') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                        <p class="mt-1 text-xs text-slate-500">{{ __('pos.checkout.received_amount_help') }}</p>
                        <p data-change-inline role="status" aria-live="polite" hidden class="mt-1 text-sm font-semibold"></p>
                    </div>
                </section>
            </div>

            {{-- Columna derecha: resumen, total y confirmación. --}}
            <aside class="min-w-0 space-y-4 lg:col-span-4 lg:sticky lg:top-6 lg:self-start">
                <section aria-labelledby="summary-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h3 id="summary-title" class="text-sm font-semibold text-slate-900">{{ __('pos.checkout.summary_title') }}</h3>

                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
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

                    <div class="mt-3 space-y-2 border-t border-slate-200 pt-3 text-sm tabular-nums" aria-label="{{ __('pos.checkout.total_to_pay') }}">
                        <div class="flex justify-between">
                            <span class="text-slate-600">{{ __('pos.common.subtotal') }}</span>
                            <span>{{ $money($totals->subtotal()->pesos()) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">{{ __('pos.common.discounts') }}</span>
                            <span data-discounts>-{{ $money($totals->discounts()->pesos()) }}</span>
                        </div>
                        <div data-convention-row @if(!$selected) hidden @endif>
                            <div class="flex justify-between gap-2 text-emerald-800">
                                <span data-convention-label>{{ __('pos.checkout.convention_savings_label') }}</span>
                                <span class="shrink-0" data-convention-savings>{{ $selected ? '−'.$money($totals->globalDiscount()->pesos()) : '' }}</span>
                            </div>
                        </div>
                    </div>

                    <p data-controlled-note role="status" @if(!$controlledExcluded) hidden @endif class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ __('benefits.note') }}</p>

                    @if ($manualDiscount)
                        <p data-manual-summary @if($selected) hidden @endif class="mt-2 text-xs text-slate-500">
                            {{ __('pos.checkout.manual_discount', [
                                'reason' => $manualDiscount->reason,
                                'amount' => $money($totals->globalDiscount()->pesos()),
                            ]) }}
                        </p>
                    @endif

                    <div class="mt-4 rounded-xl bg-slate-900 px-4 py-4 text-white">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __('pos.checkout.total_to_pay') }}</span>
                            <output class="text-2xl font-bold tabular-nums" data-checkout-total>{{ $money($totals->total()->pesos()) }}</output>
                        </div>
                    </div>

                    <div data-change-row role="status" aria-live="polite" hidden class="mt-2 rounded-lg px-3 py-2 text-sm font-semibold">
                        <span data-change-value></span>
                    </div>

                    @if ($requiresPrescription)
                        <p id="checkout-approval-hint" data-approval-hint role="status" class="mt-3 text-sm text-amber-900">{{ __('pos.rx_review.checkout_waiting') }}</p>
                    @endif

                    <div class="mt-4 space-y-2">
                        <button type="submit" data-checkout-submit class="w-full rounded-lg bg-emerald-700 px-4 py-3 text-base font-semibold text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                            {{ __('pos.checkout.confirm_amount', ['total' => $money($totals->total()->pesos())]) }}
                        </button>
                        <a href="{{ route('pos.index') }}" class="block w-full rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700">
                            {{ __('pos.checkout.back') }}
                        </a>
                    </div>
                </section>
            </aside>
        </form>

        {{-- El alta de afiliación es un formulario independiente; sus campos se asocian con el atributo form. --}}
        <form id="membership-form" method="POST" action="{{ route('pos.checkout.membership') }}" class="hidden">
            @csrf
        </form>
        @endif
    </div>

    <script nonce="{{ $cspNonce }}">
        // La vista previa usa el mismo cálculo del servidor que el cobro; el navegador solo presenta cifras.
        (() => {
            const form = document.querySelector('[data-checkout-form]');
            const lookup = form?.querySelector('[data-benefit-lookup]');
            if (!form || !lookup || !window.fetch) return;

            const select = form.querySelector('#convention_id');
            const rut = form.querySelector('#customer_rut');
            const submit = form.querySelector('[data-checkout-submit]');
            const status = form.querySelector('[data-preview-status]');
            const total = document.querySelector('[data-checkout-total]');
            const row = document.querySelector('[data-convention-row]');
            const savings = document.querySelector('[data-convention-savings]');
            const label = document.querySelector('[data-convention-label]');
            const note = document.querySelector('[data-controlled-note]');
            const invoiceHint = document.querySelector('[data-rut-invoice-hint]');
            const received = form.querySelector('#received_amount');
            const receivedBlock = form.querySelector('[data-received-block]');
            const changeRow = document.querySelector('[data-change-row]');
            const changeText = changeRow.querySelector('[data-change-value]');
            const changeInline = document.querySelector('[data-change-inline]');
            const currency = (value) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(value);
            const replace = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.replace(`:${key}`, value), template);
            const isInvoice = () => form.querySelector('[data-document-type]:checked')?.value === 'invoice';
            const isCash = () => form.querySelector('[data-payment-method]:checked')?.value === 'cash';
            const isValidRut = (raw) => {
                const input = raw.replace(/\s/g, '').toUpperCase();
                const match = input.match(/^(\d{1,8}|\d{1,2}(?:\.\d{3}){1,2})-?([0-9K])$/);
                const body = match?.[1].replaceAll('.', '').replace(/^0+/, '');
                if (!body) return false;
                const sum = [...body].reverse().reduce((accumulator, digit, index) => accumulator + Number(digit) * (2 + index % 6), 0);
                return '0123456789K'[(11 - sum % 11) % 11] === match[2];
            };
            let sequence = 0;
            let rutTimer;
            let previewReady = true;
            let currentTotal = @json($totals->total()->pesos());

            const updateRutRequirement = () => {
                const missing = isInvoice() && !isValidRut(rut.value.trim());
                rut.setCustomValidity(missing ? @json(__('pos.checkout.invoice_rut_required')) : '');
                rut.setAttribute('aria-invalid', missing ? 'true' : 'false');
                if (invoiceHint) invoiceHint.hidden = !missing;
            };

            const updateChange = () => {
                const cash = isCash();
                if (receivedBlock) receivedBlock.hidden = !cash;
                const ready = cash && currentTotal !== null;
                if (changeRow) changeRow.hidden = !ready;
                if (changeInline) changeInline.hidden = !ready;
                if (!ready) return;

                const amount = received.valueAsNumber;
                if (!Number.isSafeInteger(amount) || amount <= 0) {
                    if (changeRow) changeRow.hidden = true;
                    if (changeInline) changeInline.hidden = true;
                    return;
                }

                const difference = amount - currentTotal;
                const text = difference >= 0
                    ? replace(form.dataset.change, { amount: currency(difference) })
                    : replace(form.dataset.short, { amount: currency(-difference) });
                const tone = difference >= 0
                    ? { row: 'bg-emerald-50 text-emerald-800', inline: 'text-emerald-700' }
                    : { row: 'bg-amber-50 text-amber-800', inline: 'text-amber-700' };

                if (changeRow) changeRow.className = `mt-2 rounded-lg px-3 py-2 text-sm font-semibold ${tone.row}`;
                if (changeInline) changeInline.className = `mt-1 text-sm font-semibold ${tone.inline}`;
                if (changeText) changeText.textContent = text;
                if (changeInline) changeInline.textContent = text;
                if (changeRow) changeRow.hidden = false;
                if (changeInline) changeInline.hidden = false;
            };

            const approvalHint = form.querySelector('[data-approval-hint]');
            const reviewedRecipe = @json($prescriptionReview?->pharmacist_id ? $prescriptionReview->snapshot['prescription'] : null);
            const expiresAt = @json($prescriptionReview?->expires_at?->getTimestamp());
            const recipeFields = ['prescription_number', 'prescription_prescriber_name', 'prescription_prescriber_rut', 'prescription_issued_at'];
            const updateSubmit = () => {
                let ready = true;
                if (approvalHint) {
                    const hasPanelApproval = reviewedRecipe && Date.now() < expiresAt * 1000
                        && recipeFields.every((field) => form.elements[field].value.trim() === reviewedRecipe[field]);
                    const email = form.elements.chemist_email;
                    const hasPin = recipeFields.every((field) => form.elements[field].value.trim() !== '')
                        && email.value.trim() !== '' && email.validity.valid
                        && /^[0-9]{8,12}$/.test(form.elements.pharmacist_pin.value);
                    ready = Boolean(hasPanelApproval || hasPin);
                    approvalHint.hidden = ready;
                }
                submit.disabled = !previewReady || !ready;
            };
            if (approvalHint) {
                form.addEventListener('input', updateSubmit);
                form.addEventListener('change', updateSubmit);
                window.addEventListener('pageshow', updateSubmit);
                if (expiresAt) window.setTimeout(updateSubmit, Math.max(0, expiresAt * 1000 - Date.now()));
            }
            updateSubmit();

            const preview = async (refreshOptions = false) => {
                const current = ++sequence;
                previewReady = false;
                currentTotal = null;
                updateChange();
                updateSubmit();
                status.textContent = form.dataset.loading;
                row.hidden = true;
                note.hidden = true;
                total.textContent = '—';
                submit.textContent = form.dataset.loading;

                try {
                    const response = await fetch(form.dataset.previewUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
                        body: JSON.stringify({ benefit_rut: rut.value, convention_id: select.value || null, lookup: refreshOptions }),
                    });
                    const data = await response.json();
                    if (current !== sequence) return;
                    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || form.dataset.previewError);

                    if (refreshOptions) {
                        select.replaceChildren(new Option(@json(__('pos.checkout.no_convention')), ''));
                        data.conventions.forEach((item) => select.add(new Option(`${item.name} (${Number(item.percent).toLocaleString('es-CL')}%)`, String(item.id))));
                        select.value = data.selected_convention_id ?? '';
                        rut.value = data.benefit_rut || '';
                    }
                    const shownTotal = currency(data.total);
                    currentTotal = data.total;
                    total.textContent = shownTotal;
                    submit.textContent = replace(form.dataset.confirm, { total: shownTotal });
                    note.hidden = !data.controlled_excluded;
                    document.querySelector('[data-discounts]').textContent = `−${currency(data.discounts)}`;
                    const manual = document.querySelector('[data-manual-summary]');
                    if (manual) manual.hidden = Boolean(select.value);
                    if (select.value) {
                        const chosen = data.conventions.find((item) => String(item.id) === select.value);
                        label.textContent = chosen ? `${chosen.name} · ${Number(chosen.percent).toLocaleString('es-CL')}%` : @json(__('pos.checkout.convention_savings_label'));
                        savings.textContent = `−${currency(data.convention_savings)}`;
                        row.hidden = false;
                        status.textContent = replace(form.dataset.selected, { name: chosen?.name || '', amount: currency(data.convention_savings) });
                    } else {
                        status.textContent = replace(data.benefit_rut && !data.conventions.length ? form.dataset.noBenefit : form.dataset.unselected, { total: shownTotal });
                        if (refreshOptions && data.benefit_rut && data.conventions.length > 1) status.textContent = @json(__('pos.checkout.benefit_found'));
                    }
                    previewReady = true;
                    updateSubmit();
                    updateRutRequirement();
                    updateChange();
                } catch (error) {
                    if (current !== sequence) return;
                    status.textContent = error.message || form.dataset.previewError;
                }
            };

            select.addEventListener('change', () => preview());
            lookup.addEventListener('click', (event) => {
                event.preventDefault();
                rut.setCustomValidity('');
                const value = rut.value.trim();
                if (value !== '' && !isValidRut(value)) {
                    rut.setCustomValidity(@json(__('pos.checkout.benefit_invalid')));
                    rut.reportValidity();
                    return;
                }
                select.value = '';
                clearTimeout(rutTimer);
                preview(true);
            });
            rut.addEventListener('input', () => {
                rut.setCustomValidity('');
                note.hidden = true;
                ++sequence;
                select.value = '';
                previewReady = false;
                updateSubmit();
                currentTotal = null;
                total.textContent = '—';
                row.hidden = true;
                submit.textContent = form.dataset.loading;
                status.textContent = form.dataset.loading;
                updateRutRequirement();
                updateChange();
                select.length = 1;
                clearTimeout(rutTimer);
                if (!rut.value.trim() || isValidRut(rut.value)) {
                    rutTimer = setTimeout(() => preview(), 300);
                } else {
                    status.textContent = @json(__('pos.checkout.benefit_invalid'));
                }
            });
            received?.addEventListener('input', updateChange);
            form.querySelectorAll('[data-payment-method]').forEach((radio) => radio.addEventListener('change', updateChange));
            form.querySelectorAll('[data-document-type]').forEach((radio) => radio.addEventListener('change', updateRutRequirement));

            updateRutRequirement();
            updateChange();
        })();

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
