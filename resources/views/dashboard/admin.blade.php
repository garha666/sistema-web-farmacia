@extends('layouts.admin')

@section('title', __('pos.panel.admin.title'))

@section('content')
    @php
        $today = now(config('pos.timezone'));
        $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
    @endphp

    <div class="space-y-5">
        <header class="rounded-xl bg-slate-900 p-5 text-white sm:p-7">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-slate-300">
                <span class="rounded-full bg-emerald-400/15 px-2.5 py-1 text-emerald-200">
                    {{ __('pos.panel.role') }}: {{ __('pos.role.'.auth()->user()->role) }}
                </span>
                <time class="tabular-nums" datetime="{{ $today->toDateString() }}">
                    <span class="sr-only">{{ __('pos.panel.date') }}: </span>{{ $today->format('d-m-Y') }}
                </time>
            </div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('pos.panel.admin.title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-300">{{ __('pos.panel.admin.lead') }}</p>

            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('admin.users.index') }}" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-200 focus-visible:ring-2 focus-visible:ring-emerald-300">{{ __('users.title') }}</a>
                <a href="{{ route('account.pos-authorization.edit') }}" class="rounded-lg border border-slate-500 px-3 py-2 text-sm font-semibold hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-emerald-300">{{ __('pos.authorization.title') }}</a>
            </div>
        </header>

        <div class="grid items-start gap-5 xl:grid-cols-3">

            {{-- RF-46: ventas del día con anuladas por separado. --}}
            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 xl:col-span-2" aria-labelledby="day-sales">
                <h2 id="day-sales" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.admin.sales_heading') }}</h2>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="min-w-0 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-900">{{ __('pos.panel.admin.sold_amount') }}</dt>
                        <dd id="admin-sold" class="mt-1 break-words text-3xl font-bold tracking-tight tabular-nums text-emerald-950">{{ $money($summary->totalSales()) }}</dd>
                    </div>
                    @include('dashboard.partials.kpi', [
                        'id' => 'admin-confirmed',
                        'label' => __('pos.panel.admin.confirmed_count'),
                        'value' => $summary->salesCount(),
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
            </section>

            <div class="grid min-w-0 gap-5 md:grid-cols-2 xl:grid-cols-1">
                @include('dashboard.partials.payment-breakdown', ['summary' => $summary])
                @include('dashboard.partials.inventory-alerts', ['inventory' => $inventory])
            </div>
        </div>

        @include('dashboard.partials.documents-table', ['documents' => $documents])
        @include('dashboard.partials.audit-table', ['audit' => $audit])
        @include('dashboard.partials.conventions-table', ['conventions' => $conventions])
        @include('dashboard.partials.cash-cuts-table', ['cashCuts' => $cashCuts])
    </div>
@endsection
