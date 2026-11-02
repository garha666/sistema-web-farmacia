@extends('layouts.admin')

@section('title', __('pos.admin.sale.title'))

@php
    $money = fn (?int $pesos): string => $pesos === null ? __('pos.common.none') : '$'.number_format($pesos, 0, ',', '.');
    $percent = fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.admin.sale.heading', ['id' => $sale->id]) }}</h2>
            <p class="text-sm text-slate-500">{{ $sale->sold_at?->format('d-m-Y H:i') }} · {{ $sale->payment_method->label() }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.sales.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                {{ __('pos.admin.sale.back') }}
            </a>
            <a href="{{ route('pos.sales.receipt', $sale) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('pos.admin.sale.receipt') }}
            </a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl bg-white p-4 shadow-sm lg:col-span-2">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sale.lines') }}</h3>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2">{{ __('pos.admin.sale.product') }}</th>
                            <th class="py-2 text-right">{{ __('pos.admin.sale.quantity') }}</th>
                            <th class="py-2 text-right">{{ __('pos.admin.sale.price') }}</th>
                            <th class="py-2 text-right">{{ __('pos.admin.sale.discount') }}</th>
                            <th class="py-2 text-right">{{ __('pos.admin.sale.subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sale->lines as $line)
                            <tr>
                                <td class="py-2 font-medium">{{ $line->name }}</td>
                                <td class="py-2 text-right">{{ $line->quantity }}</td>
                                <td class="py-2 text-right">{{ $money($line->unit_price) }}</td>
                                <td class="py-2 text-right">{{ $money($line->discount_amount) }}</td>
                                <td class="py-2 text-right font-semibold">{{ $money($line->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.admin.sale.subtotal') }}</dt>
                    <dd>{{ $money($sale->subtotal) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.admin.sale.line_discounts') }}</dt>
                    <dd>-{{ $money($sale->line_discounts) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.admin.sale.global_discount') }}</dt>
                    <dd>-{{ $money($sale->global_discount) }}</dd>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <dt>{{ __('pos.admin.sale.net_iva') }}</dt>
                    <dd>{{ $money($sale->net) }} / {{ $money($sale->iva) }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-bold">
                    <dt>{{ __('pos.admin.sale.total') }}</dt>
                    <dd>{{ $money($sale->total) }}</dd>
                </div>
            </dl>
        </section>

        <div class="space-y-4">
            <section class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sale.status') }}</h3>
                <p class="mt-2">
                    @if ($sale->status === \App\Pos\Enum\SaleStatus::voided)
                        <span class="rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-800">{{ $sale->status->label() }}</span>
                    @else
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">{{ $sale->status->label() }}</span>
                    @endif
                </p>
                <p class="mt-3 text-sm text-slate-600">{{ __('pos.admin.sale.cashier', ['name' => $sale->user?->name ?? __('pos.common.none')]) }}</p>
                <p class="text-sm text-slate-600">{{ __('pos.admin.sale.customer_rut', ['rut' => $sale->customer_rut ?? __('pos.common.none')]) }}</p>
            </section>

            <section class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sale.convention') }}</h3>
                @if ($sale->convention_name !== null)
                    <p class="mt-2 font-medium">{{ $sale->convention_name }}</p>
                    <p class="text-sm text-slate-600">{{ __('pos.admin.sale.convention_discount', ['percent' => $percent($sale->convention_percent ?? 0)]) }}</p>
                    <p class="text-sm text-slate-600">{{ __('pos.admin.sale.convention_savings', ['amount' => $money($sale->global_discount)]) }}</p>
                @else
                    <p class="mt-2 text-sm text-slate-500">{{ __('pos.admin.sale.no_convention') }}</p>
                @endif
            </section>

            <section class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sale.document') }}</h3>
                @if ($sale->document !== null)
                    <p class="mt-2 font-medium">{{ $sale->document->type->label() }} #{{ $sale->document->folio ?? __('pos.common.no_folio') }}</p>
                    <p class="text-sm text-slate-600">{{ __('pos.admin.sale.document_status', ['status' => $sale->document->status->label()]) }}</p>
                    <p class="text-sm text-slate-600">{{ __('pos.admin.sale.issued_at', ['date' => $sale->document->issued_at?->format('d-m-Y H:i') ?? __('pos.common.none')]) }}</p>
                @else
                    <p class="mt-2 text-sm text-slate-500">{{ __('pos.admin.sale.no_document') }}</p>
                @endif

                @if ($sale->reversal !== null)
                    <p class="mt-3 text-sm text-slate-600">
                        {{ __('pos.admin.sale.credit_note', ['folio' => $sale->reversal->folio ?? __('pos.common.no_folio')]) }}
                    </p>
                @endif
            </section>

            @if ($sale->auditLogs->isNotEmpty())
                <section class="rounded-xl bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sale.audit') }}</h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($sale->auditLogs as $log)
                            <li>
                                <span class="font-medium">{{ $log->action }}</span>
                                · {{ $log->user?->name ?? __('pos.common.none') }}
                                @if ($log->reason)
                                    <span class="block text-slate-600">{{ $log->reason }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
@endsection
