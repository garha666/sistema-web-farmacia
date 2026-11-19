@extends('layouts.admin')

@section('title', __('pos.panel.admin.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'admin'])

        {{-- RF-46: ventas del día con anuladas por separado. --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="day-sales">
            <h2 id="day-sales" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.admin.sales_heading') }}</h2>

            @php
                $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
            @endphp

            <dl class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @include('dashboard.partials.kpi', [
                    'id' => 'admin-confirmed',
                    'label' => __('pos.panel.admin.confirmed_count'),
                    'value' => $summary->salesCount(),
                ])
                @include('dashboard.partials.kpi', [
                    'id' => 'admin-sold',
                    'label' => __('pos.panel.admin.sold_amount'),
                    'value' => $money($summary->totalSales()),
                ])
                @include('dashboard.partials.kpi', [
                    'id' => 'admin-voided-count',
                    'label' => __('pos.panel.admin.voided_count'),
                    'value' => $summary->voidCount(),
                ])
                @include('dashboard.partials.kpi', [
                    'id' => 'admin-voided-amount',
                    'label' => __('pos.panel.admin.voided_amount'),
                    'value' => $money($summary->totalVoids()),
                ])
                {{-- D-11: anulaciones registradas hoy, misma cifra que el Químico. --}}
                @include('dashboard.partials.kpi', [
                    'id' => 'admin-registered-voids',
                    'label' => __('pos.panel.admin.registered_voids_count'),
                    'value' => $registeredVoids,
                ])
            </dl>

            <div class="mt-4">
                @include('dashboard.partials.payment-breakdown', ['summary' => $summary])
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            @include('dashboard.partials.documents-table', ['documents' => $documents])
            @include('dashboard.partials.inventory-alerts', ['inventory' => $inventory])
        </div>

        @include('dashboard.partials.audit-table', ['audit' => $audit])
        @include('dashboard.partials.conventions-table', ['conventions' => $conventions])
        @include('dashboard.partials.cash-cuts-table', ['cashCuts' => $cashCuts])
    </div>
@endsection
