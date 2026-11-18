@extends('layouts.guest')

@section('title', __('pos.login.title'))

@section('content')
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('pos.login.heading') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('pos.login.lead') }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                <ul class="list-disc space-y-0.5 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="email" class="text-sm font-semibold text-slate-700">{{ __('pos.login.email') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    inputmode="email"
                    autocomplete="username"
                    required
                    autofocus
                    value="{{ old('email') }}"
                    class="mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 @error('email') border-red-400 @else border-slate-300 @enderror"
                >
            </div>

            <div>
                <label for="password" class="text-sm font-semibold text-slate-700">{{ __('pos.login.password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 @error('password') border-red-400 @else border-slate-300 @enderror"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
            >
                {{ __('pos.login.submit') }}
            </button>
        </form>
    </div>
@endsection
