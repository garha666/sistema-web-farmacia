@extends('layouts.admin')

@section('title', __('pos.panel.quimico.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'quimico', 'pinConfigured' => filled(auth()->user()->pharmacist_pin_hash)])

    <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm sm:p-5" aria-labelledby="rx-reviews-title"
        data-prescription-queue="{{ route('dashboard.quimico.prescriptions.pending') }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="rx-reviews-title" class="text-lg font-semibold">{{ __('pos.rx_review.panel') }}</h2>
            <output data-queue-count aria-label="{{ __('pos.rx_review.total') }}" class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold tabular-nums text-emerald-900">{{ $prescriptionReviews->total() }}</output>
        </div>
        @if (session('prescription_approved'))<p role="status" class="mt-2 text-emerald-800">{{ __('pos.rx_review.approved_notice') }}</p>@endif
        @error('review')<p role="alert" class="mt-2 text-rose-800">{{ $message }}</p>@enderror
        <p data-queue-error role="status" class="mt-2 text-sm text-amber-900" hidden>{{ __('pos.rx_review.refresh_failed') }}</p>
        <div data-queue-list>
            @include('dashboard.partials.pending-prescriptions')
        </div>
    </section>

        {{-- RF-52: ventas del día con el mismo desglose que el panel del Administrador. --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="chemist-day-sales">
            <h2 id="chemist-day-sales" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.quimico.sales_heading') }}</h2>

            @php
                $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
            @endphp

            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                @include('dashboard.partials.kpi', [
                    'label' => __('pos.panel.quimico.confirmed_count'),
                    'value' => $summary->salesCount(),
                ])
                @include('dashboard.partials.kpi', [
                    'label' => __('pos.panel.quimico.sold_amount'),
                    'value' => $money($summary->totalSales()),
                ])
            </dl>

            <div class="mt-4">
                @include('dashboard.partials.payment-breakdown', [
                    'summary' => $summary,
                    'panel' => 'quimico',
                ])
            </div>
        </section>

        {{-- RF-53: autorizaciones propias de hoy. --}}
        @include('dashboard.partials.authorizations-table', ['authorizations' => $authorizations])

        {{-- RF-54: anulaciones de hoy con motivo y autor. --}}
        @include('dashboard.partials.voids-table', ['voids' => $voids])

    </div>
@endsection
