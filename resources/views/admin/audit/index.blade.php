@extends('layouts.admin')

@section('title', __('pos.admin.audit.title'))

@section('content')
    <div>
        <h2 class="text-lg font-semibold">{{ __('pos.admin.audit.heading') }}</h2>
        <p class="text-sm text-slate-500">{{ __('pos.admin.audit.subtitle') }}</p>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.date') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.action') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.author') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.sale') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.audit.reason') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-slate-600">{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $log->action }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $log->user?->name ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            @if (str_ends_with($log->auditable_type ?? '', 'Sale'))
                                #{{ $log->auditable_id }}
                            @else
                                {{ __('pos.common.none') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $log->reason ?? __('pos.common.none') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">{{ __('pos.admin.audit.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
