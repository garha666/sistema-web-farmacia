{{--
    Formulario de apertura del turno (RF-57, RF-58).

    Sólo se muestra cuando el cajero no tiene una caja abierta; explica que sin
    apertura no puede cobrar.
--}}
<section class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm" aria-labelledby="open-cash-title">
    <h2 id="open-cash-title" class="text-lg font-semibold text-amber-900">{{ __('pos.cash_register.open_title') }}</h2>
    <p class="mt-1 text-sm text-amber-900">{{ __('pos.cash_register.open_lead') }}</p>
    <p class="mt-1 text-sm font-medium text-amber-900">{{ __('pos.cash_register.no_turn') }}</p>
    <p class="mt-2 rounded-lg bg-white/70 px-3 py-2 text-sm font-medium text-amber-900">
        {{ __('pos.cash_register.open_warning') }}
    </p>

    <form method="POST" action="{{ route('cash.open') }}" class="mt-4 space-y-3">
        @csrf

        <div>
            <label for="opening_amount" class="text-sm font-semibold text-amber-900">{{ __('pos.cash_register.open_amount') }}</label>
            <input
                id="opening_amount"
                name="opening_amount"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                required
                value="{{ old('opening_amount') }}"
                class="mt-1 w-full max-w-xs rounded-lg border border-amber-300 bg-white px-3 py-2"
            >
            @error('opening_amount')
                <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
        >
            {{ __('pos.cash_register.open_submit') }}
        </button>
    </form>
</section>
