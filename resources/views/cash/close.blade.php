@extends('layouts.admin')

@section('title', __('pos.cash_register.title'))

@section('content')
    <div class="mx-auto max-w-2xl space-y-5">
        <header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('pos.cash_register.title') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ __('pos.cash_register.blind_hint') }}</p>
        </header>
        <form method="POST" action="{{ route('cash.close', $register) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
            @csrf
            @method('PATCH')
            @if ($errors->any())
                <ul class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <fieldset>
                <legend class="font-semibold text-slate-900">{{ __('pos.cash_register.denominations') }}</legend>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($denominations as $denomination)
                        <div>
                            <label for="count-{{ $denomination }}" class="text-sm font-medium text-slate-700">
                                {{ __('pos.cash_register.count_label', ['amount' => number_format($denomination, 0, ',', '.')]) }}
                            </label>
                            <input id="count-{{ $denomination }}" name="denominations[{{ $denomination }}]"
                                value="{{ is_scalar(old('denominations.'.$denomination)) ? old('denominations.'.$denomination) : '' }}" type="number" inputmode="numeric"
                                min="0" max="10000" step="1" placeholder="0"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 tabular-nums">
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <div>
                <label for="reason" class="text-sm font-semibold text-slate-900">{{ __('pos.cash_register.close_reason') }}</label>
                <p id="reason-hint" class="mt-1 text-sm text-slate-600">{{ __('pos.cash_register.reason_hint') }}</p>
                <textarea id="reason" name="reason" rows="3" maxlength="500" aria-describedby="reason-hint"
                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">{{ is_string(old('reason')) ? old('reason') : '' }}</textarea>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">
                    {{ __('pos.cash_register.title') }}
                </button>
                <a href="{{ route('dashboard.tecnico') }}" class="text-sm font-semibold text-slate-700 underline">
                    {{ __('pos.cash_register.cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
