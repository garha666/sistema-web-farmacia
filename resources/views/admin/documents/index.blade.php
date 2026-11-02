@extends('layouts.admin')

@section('title', __('pos.admin.documents.title'))

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.admin.documents.heading') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pos.admin.documents.subtitle') }}</p>
        </div>

        <form method="GET" action="{{ route('admin.documents.index') }}" class="flex items-end gap-2">
            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.documents.status') }}</label>
                <select id="status" name="status" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">{{ __('pos.common.all') }}</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}" @selected($status === $option->value)>
                            {{ $option->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('pos.admin.documents.filter') }}
            </button>
        </form>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pos.admin.documents.document') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.documents.sale') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.documents.folio') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.documents.status') }}</th>
                    <th class="px-4 py-3">{{ __('pos.admin.documents.issued_at') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('pos.admin.documents.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $document->type->label() }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            @if ($document->sale !== null)
                                <a href="{{ route('admin.sales.show', $document->sale) }}" class="text-emerald-700 hover:underline">
                                    #{{ $document->sale->id }}
                                </a>
                            @else
                                {{ __('pos.common.none') }}
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $document->folio ?? __('pos.common.no_folio') }}</td>
                        <td class="px-4 py-3">
                            @if ($document->status === \App\Pos\Enum\DocumentStatus::issued)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">{{ $document->status->label() }}</span>
                            @elseif ($document->status === \App\Pos\Enum\DocumentStatus::pending)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">{{ $document->status->label() }}</span>
                            @else
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">{{ $document->status->label() }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $document->issued_at?->format('d-m-Y H:i') ?? __('pos.common.none') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($document->status !== \App\Pos\Enum\DocumentStatus::issued)
                                <form method="POST" action="{{ route('admin.documents.retry', $document) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-sm font-semibold text-amber-700 hover:underline">
                                        {{ __('pos.admin.documents.retry') }}
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">{{ __('pos.admin.documents.no_actions') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">{{ __('pos.admin.documents.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $documents->links() }}
    </div>
@endsection
