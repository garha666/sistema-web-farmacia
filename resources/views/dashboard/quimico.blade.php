@extends('layouts.admin')

@section('title', __('pos.panel.quimico.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'quimico'])

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

        {{-- RF-55: salidas de lectura; el Químico no administra convenios ni auditoría. --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="chemist-read-only">
            <h2 id="chemist-read-only" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.quimico.read_only_heading') }}</h2>

            <div class="mt-3 flex flex-wrap gap-3">
                <a href="{{ route('admin.conventions.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.panel.quimico.conventions_link') }}
                </a>
                <a href="{{ route('admin.audit.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.panel.quimico.audit_link') }}
                </a>
            </div>
        </section>
    </div>
@endsection
