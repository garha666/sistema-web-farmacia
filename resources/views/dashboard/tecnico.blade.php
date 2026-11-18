@extends('layouts.admin')

@section('title', __('pos.panel.tecnico.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'tecnico'])

        @if (session('cash_opened'))
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ __('pos.cash_register.status_open') }}
            </p>
        @endif

        @if (session()->has('cash_closed'))
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ __('pos.cash_register.confirm') }}
            </p>
        @endif

        @if ($register === null)
            @include('dashboard.partials.open-cash-form')
        @else
            @include('dashboard.partials.turn-summary', [
                'register' => $register,
                'summary' => $summary,
            ])
        @endif

        <div class="flex flex-wrap gap-3">
            <a
                href="{{ route('pos.index') }}"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
            >
                {{ __('pos.cash_register.go_pos') }}
            </a>

            @if ($register !== null)
                <a
                    href="{{ route('cash.close.show') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                >
                    {{ __('pos.cash_register.go_close') }}
                </a>
            @endif
        </div>
    </div>
@endsection
