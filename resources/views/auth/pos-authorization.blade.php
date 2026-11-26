@extends('layouts.admin')

@section('title', __('pos.authorization.title'))

@section('content')
        <section class="mt-4 max-w-xl rounded-xl bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">{{ __('pos.rx_review.pin_title') }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ __('pos.rx_review.pin_format') }}</p>
            @if (session('pharmacist_pin_updated'))
                <p role="status" class="mt-2 text-sm text-emerald-800">{{ __('pos.rx_review.pin_saved') }}</p>
            @endif
            <form action="{{ route('account.pharmacist-pin.update') }}" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PUT')
                @foreach (['current_password', 'pharmacist_pin', 'pharmacist_pin_confirmation'] as $field)
                    <div>
                        <label for="rx-{{ $field }}" class="block text-sm font-medium">{{ $field === 'current_password' ? __('pos.authorization.current_password') : __('pos.rx_review.'.$field) }}</label>
                        <input id="rx-{{ $field }}" name="{{ $field }}" type="password" required
                               @if ($field !== 'current_password') inputmode="numeric" pattern="[0-9]{8,12}" maxlength="12" @endif
                               autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error($field, 'pin')<p class="mt-1 text-sm text-rose-800" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ __('pos.rx_review.pin_save') }}</button>
            </form>
        </section>
@endsection
