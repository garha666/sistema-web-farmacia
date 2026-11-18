@extends('layouts.admin')

@section('title', __('pos.inventory.movements.title'))

@section('content')
    <div class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ __('pos.inventory.movements.heading') }}</h2>
                <p class="text-sm text-slate-500">{{ __('pos.inventory.movements.subtitle') }}</p>
            </div>

            <a href="{{ route('dashboard.bodeguero') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.inventory.movements.back') }}
            </a>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="min-w-[760px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.movements.date') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.movements.product') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.movements.type') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('pos.inventory.movements.quantity') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('pos.inventory.movements.resulting_stock') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.movements.reason') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('pos.inventory.movements.author') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 tabular-nums text-slate-600">{{ $movement->created_at?->format('d-m-Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $movement->product?->name ?? __('pos.common.none') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $movement->quantity < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $movement->quantity }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $movement->resulting_stock }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ filled($movement->note) ? $movement->note : __('pos.common.none') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->user?->name ?? __('pos.common.none') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">{{ __('pos.inventory.movements.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $movements->links() }}
    </div>
@endsection
