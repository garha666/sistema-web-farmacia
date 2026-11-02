@extends('layouts.admin')

@section('title', __('pos.admin.controlled.title'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.admin.controlled.heading') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.admin.controlled.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <form method="GET" action="{{ route('admin.controlled-register.index') }}" class="flex items-end gap-2">
                <div>
                    <label for="from" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.controlled.from') }}</label>
                    <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"
                           class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.controlled.to') }}</label>
                    <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"
                           class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                    {{ __('pos.admin.controlled.filter') }}
                </button>
            </form>

            <a href="{{ route('admin.controlled-register.export', $filters) }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('pos.admin.controlled.export') }}
            </a>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pos.admin.controlled.date') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.controlled.customer_rut') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.controlled.folio') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.controlled.prescription') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.controlled.medication') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.admin.controlled.quantity') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.controlled.sale') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="px-4 py-3 text-slate-600">{{ $entry->sold_at->format('d-m-Y') }}</td>
                        <td class="px-4 py-3">{{ $entry->customer_rut ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3">{{ $entry->folio ?? __('pos.common.no_folio') }}</td>
                        <td class="px-4 py-3">
                            {{ $entry->prescription->number }}
                            <span class="block text-xs text-slate-500">{{ $entry->prescription->prescriber_name }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $entry->medication }}</td>
                        <td class="px-4 py-3 text-right">{{ $entry->quantity }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.sales.show', $entry->sale) }}" class="text-emerald-700 hover:underline">
                                #{{ $entry->sale_id }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">{{ __('pos.admin.controlled.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $entries->links() }}
    </div>
@endsection
