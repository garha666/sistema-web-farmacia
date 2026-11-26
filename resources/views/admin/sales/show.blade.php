@extends('layouts.admin')

@section('title', __('pos.admin.sale.title'))

@php
    $money = fn (?int $pesos): string => $pesos === null ? __('pos.common.none') : '$'.number_format($pesos, 0, ',', '.');
    $percent = fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $actionLabels = [
        'sale.prescription_reuse' => __('pos.admin.sale.authorization_prescription_reuse'),
        'sale.discount_authorized' => __('pos.admin.sale.authorization_discount'),
        'sale.line_discount_authorized' => __('pos.admin.sale.authorization_line_discount'),
    ];
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('pos.admin.sale.heading', ['id' => $sale->id]) }}</h2>
            <p class="text-sm text-slate-500">{{ $sale->sold_at?->format('d-m-Y H:i') }} · {{ $sale->payment_method->label() }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.sales.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2">
                {{ __('pos.admin.sale.back') }}
            </a>
            <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('pos.sales.receipt', $sale) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('pos.admin.sale.receipt') }}
            </a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="min-w-0 rounded-xl bg-white p-4 shadow-sm lg:col-span-2">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sale.lines') }}</h3>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-[30rem] divide-y divide-slate-200 text-sm">
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
                                <td class="py-2 font-medium">
                                    {{ $line->name }}
                                    @foreach ($line->lots as $lot)
                                        <span class="block break-words text-xs font-normal text-slate-600">
                                            {{ __('receipts.sale_lot', ['lot' => $lot->lot, 'quantity' => $lot->pivot->quantity]) }}
                                        </span>
                                    @endforeach
                                </td>
                                <td class="py-2 text-right tabular-nums">{{ $line->quantity }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $money($line->unit_price) }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $money($line->discount_amount) }}</td>
                                <td class="py-2 text-right font-semibold tabular-nums">{{ $money($line->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.admin.sale.subtotal') }}</dt>
                    <dd class="tabular-nums">{{ $money($sale->subtotal) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.admin.sale.line_discounts') }}</dt>
                    <dd class="tabular-nums">-{{ $money($sale->line_discounts) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.admin.sale.global_discount') }}</dt>
                    <dd class="tabular-nums">-{{ $money($sale->global_discount) }}</dd>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <dt>{{ __('pos.admin.sale.net_iva') }}</dt>
                    <dd class="tabular-nums">{{ $money($sale->net) }} / {{ $money($sale->iva) }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-bold">
                    <dt>{{ __('pos.admin.sale.total') }}</dt>
                    <dd class="tabular-nums">{{ $money($sale->total) }}</dd>
                </div>
            </dl>
        </section>

        <div class="min-w-0 space-y-4">
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

            @php
                $prescription = $sale->prescription;
            @endphp
            @if ($prescription !== null
                && $prescription->number !== null
                && $prescription->prescriber_name !== null
                && $prescription->prescriber_rut !== null
                && $prescription->issued_on !== null)
                <section class="rounded-xl bg-white p-4 shadow-sm" aria-labelledby="prescription-title">
                    <h3 id="prescription-title" class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        {{ __('pos.admin.sale.prescription') }}
                    </h3>
                    <p class="mt-2 break-words font-medium">{{ $prescription->number }}</p>
                    <dl class="mt-2 space-y-1 break-words text-sm text-slate-600">
                        <div>{{ __('pos.admin.sale.prescription_prescriber', ['name' => $prescription->prescriber_name]) }}</div>
                        <div>{{ __('pos.admin.sale.prescription_rut', ['rut' => $prescription->prescriber_rut]) }}</div>
                        <div>{{ __('pos.admin.sale.prescription_issued_on', ['date' => $prescription->issued_on->format('d-m-Y')]) }}</div>
                        <div>{{ __('pos.admin.sale.prescription_valid_until', ['date' => $prescription->validUntil()->format('d-m-Y')]) }}</div>
                    </dl>
                </section>
            @endif

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
                    <p class="mt-2 font-medium">
                        {{ $sale->document->type->label() }}
                        @if ($sale->document->folio !== null)
                            #{{ $sale->document->folio }}
                        @else
                            {{ __('pos.common.no_folio') }}
                        @endif
                    </p>
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

            @if ($authorizations->isNotEmpty())
                <section class="rounded-xl bg-white p-4 shadow-sm" aria-labelledby="authorizations-title">
                    <h3 id="authorizations-title" class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('pos.admin.sale.authorizations') }}</h3>
                    <ul class="mt-2 space-y-3 text-sm">
                        @foreach ($authorizations as $log)
                            <li class="break-words">
                                <span class="font-medium">
                                    {{ $actionLabels[$log->action] ?? __('pos.admin.sale.authorization_other') }}
                                </span>
                                <span class="block text-slate-600">
                                    {{ __('pos.admin.sale.authorized_by', ['name' => $log->user?->name ?? __('pos.common.none')]) }}
                                </span>
                                @if ($log->discount_value !== null)
                                    <span class="block text-slate-600">
                                        {{ __('pos.admin.sale.authorization_amount', ['amount' => $money($log->discount_value)]) }}
                                    </span>
                                @endif
                                @if ($log->reason)
                                    <span class="block text-slate-600">{{ $log->reason }}</span>
                                @endif
                                <span class="block text-xs text-slate-500">{{ $log->created_at?->format('d-m-Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
@endsection
