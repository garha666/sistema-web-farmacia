@extends('layouts.admin')

@section('title', __('pos.cash_register.title'))

@section('content')
    @php
        $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
    @endphp

    <div class="space-y-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('pos.cash_register.title') }}</h1>
            <p class="text-sm text-slate-600">{{ __('pos.cash_register.lead') }}</p>
        </header>

        @error('cash')
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                {{ $message }}
            </p>
        @enderror

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="turn-totals">
                <h2 id="turn-totals" class="text-lg font-semibold text-slate-900">{{ __('pos.cash_register.sales_count') }}</h2>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-600">{{ __('pos.cash_register.opened_at') }}</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ $register->opened_at?->format('d-m-Y H:i') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-600">{{ __('pos.cash_register.opening_amount') }}</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ $money($register->opening_amount) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-600">{{ __('pos.cash_register.total_sales') }} ({{ $summary->salesCount() }})</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ $money($summary->totalSales()) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-600">{{ __('pos.cash_register.total_voids') }} ({{ $summary->voidCount() }})</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ $money($summary->totalVoids()) }}</dd>
                    </div>
                </dl>

                <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.cash_register.breakdown') }}</h3>

                @if ($summary->hasSales())
                    <table class="mt-2 w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th scope="col" class="py-2">{{ __('pos.cash_register.method') }}</th>
                                <th scope="col" class="py-2 text-right">{{ __('pos.cash_register.count') }}</th>
                                <th scope="col" class="py-2 text-right">{{ __('pos.cash_register.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary->breakdown() as $row)
                                <tr class="border-t border-slate-100">
                                    <th scope="row" class="py-2 text-left font-medium text-slate-700">{{ $row->method()->label() }}</th>
                                    <td class="py-2 text-right tabular-nums text-slate-600">{{ $row->count() }}</td>
                                    <td class="py-2 text-right font-semibold tabular-nums text-slate-900">{{ $money($row->amount()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="mt-2 text-sm text-slate-500">{{ __('pos.cash_register.empty') }}</p>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="turn-arqueo">
                <h2 id="turn-arqueo" class="text-lg font-semibold text-slate-900">{{ __('pos.cash_register.expected_cash') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('pos.cash_register.expected_hint') }}</p>
                <p class="mt-3 text-3xl font-bold tabular-nums text-slate-900" data-expected="{{ $arqueo->expected() }}">
                    {{ $money($arqueo->expected()) }}
                </p>

                <form method="POST" action="{{ route('cash.close', $register) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="declared_amount" class="text-sm font-semibold text-slate-700">{{ __('pos.cash_register.declared_amount') }}</label>
                        <input
                            id="declared_amount"
                            name="declared_amount"
                            type="number"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            required
                            value="{{ old('declared_amount') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                        <p class="mt-1 text-xs text-slate-500">{{ __('pos.cash_register.declared_hint') }}</p>

                        @error('declared_amount')
                            <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700" aria-live="polite">
                        <span class="font-semibold">{{ __('pos.cash_register.difference') }}:</span>
                        <span data-difference>{{ __('pos.cash_register.difference_pending') }}</span>
                    </p>

                    <div class="flex gap-3">
                        <a
                            href="{{ route('dashboard.tecnico') }}"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                        >
                            {{ __('pos.cash_register.cancel') }}
                        </a>
                        <button
                            type="submit"
                            class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                        >
                            {{ __('pos.cash_register.confirm') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <script nonce="{{ $cspNonce }}">
        (function () {
            const input = document.getElementById('declared_amount');
            const expected = Number(document.querySelector('[data-expected]').dataset.expected);
            const output = document.querySelector('[data-difference]');
            const pending = @json(__('pos.cash_register.difference_pending'));
            const labels = {
                balanced: @json(__('pos.cash_register.kind.balanced')),
                over: @json(__('pos.cash_register.kind.over')),
                short: @json(__('pos.cash_register.kind.short')),
            };
            const money = (value) => '$' + new Intl.NumberFormat('es-CL').format(Math.abs(value));

            const update = () => {
                if (input.value === '') {
                    output.textContent = pending;

                    return;
                }

                const difference = Number(input.value) - expected;
                const kind = difference === 0 ? 'balanced' : (difference > 0 ? 'over' : 'short');

                output.textContent = money(difference) + ' (' + labels[kind] + ')';
            };

            input.addEventListener('input', update);
            update();
        })();
    </script>
@endsection
