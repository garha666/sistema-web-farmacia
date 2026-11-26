@extends('layouts.admin')

@section('title', __('pos.admin.audit.title'))

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div>
        <h2 class="text-lg font-semibold">{{ __('pos.admin.audit.heading') }}</h2>
        <p class="text-sm text-slate-500">{{ __('pos.admin.audit.subtitle') }}</p>
    </div>

    <form method="GET" action="{{ route('admin.audit.index') }}" role="search" class="mt-4 flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label for="q" class="block text-xs font-medium text-slate-600">{{ __('pos.admin.audit.search_label') }}</label>
            <input
                id="q"
                name="q"
                type="search"
                value="{{ $search }}"
                maxlength="100"
                list="audit-actions"
                autocomplete="off"
                placeholder="{{ __('pos.admin.audit.search_placeholder') }}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
            >
            <datalist id="audit-actions">
                @foreach (__('pos.panel.audit_action') as $labels)
                    @foreach ($labels as $label)
                        <option value="{{ $label }}"></option>
                    @endforeach
                @endforeach
            </datalist>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.admin.audit.search') }}
            </button>
            @if ($search !== '')
                <a href="{{ route('admin.audit.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    {{ __('pos.admin.audit.clear') }}
                </a>
            @endif
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.date') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.action') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.author') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.sale') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.type') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.product') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.admin.audit.amount') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.reason') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                @forelse ($logs as $log)
                    @php
                        $isLine = str_ends_with($log->auditable_type ?? '', 'SaleLine');
                        $isSale = str_ends_with($log->auditable_type ?? '', 'Sale');
                        $saleId = $isLine ? $log->saleLine?->sale_id : ($isSale ? $log->auditable_id : null);
                        // T28 - RF-84: la acción se traduce cuando el grupo la conoce.
                        $actionKey = 'pos.panel.audit_action.'.$log->action;
                        $actionLabel = __($actionKey) === $actionKey ? $log->action : __($actionKey);
                    @endphp
                    <tr>
                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $actionLabel }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $log->user?->name ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-600">
                            {{ $saleId !== null ? '#'.$saleId : __('pos.common.none') }}
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            @if ($log->discount_type === 'percent')
                                %
                            @elseif ($log->discount_type === 'amount')
                                $
                            @else
                                {{ __('pos.common.none') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $isLine ? ($log->saleLine?->name ?? __('pos.common.none')) : __('pos.common.none') }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                            {{ $log->discount_value !== null ? '-'.$money((int) $log->discount_value) : __('pos.common.none') }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ filled($log->reason) ? $log->reason : __('pos.common.none') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">{{ $search !== '' ? __('pos.admin.audit.no_matches', ['query' => $search]) : __('pos.admin.audit.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
