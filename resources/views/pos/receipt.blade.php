@extends('layouts.pos')

@section('title', __('pos.receipt.title'))

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
    $percent = fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
@endphp

@section('content')
    <div class="mx-auto max-w-2xl">
        <article class="rounded-xl bg-white p-6 shadow-sm print:shadow-none">
            <header class="border-b border-dashed border-slate-300 pb-4 text-center">
                <p class="text-lg font-bold">{{ $issuer['name'] }}</p>
                <p class="text-sm text-slate-600">{{ __('pos.receipt.issuer_rut', ['rut' => $issuer['rut']]) }}</p>
                <p class="text-sm text-slate-600">
                    {{ __('pos.receipt.issuer_line', ['business' => $issuer['business'], 'address' => $issuer['address']]) }}
                </p>
                <p class="mt-3 text-sm font-semibold uppercase tracking-wide text-slate-700">
                    {{ __('pos.receipt.electronic', ['type' => $document?->type->label() ?? __('pos.document.type.receipt')]) }}
                </p>
                <p class="text-sm">
                    {{ __('pos.receipt.folio', ['folio' => $document?->folio ?? __('pos.common.pending')]) }}
                </p>
                <p class="text-xs text-slate-500">{{ $sale->sold_at->format('d-m-Y H:i') }}</p>
                @if ($sale->status->value === 'voided')
                    <p class="mt-2 inline-block rounded bg-rose-100 px-3 py-1 text-sm font-bold tracking-widest text-rose-700">
                        {{ __('pos.receipt.voided') }}
                    </p>
                    @if ($reversal)
                        <p class="mt-1 text-xs text-slate-500">
                            {{ __('pos.receipt.credit_note', ['folio' => $reversal->folio ?? __('pos.common.pending')]) }}
                        </p>
                    @endif
                    @if ($voidReason)
                        <p class="mt-1 text-xs text-rose-600">{{ __('pos.receipt.void_reason', ['reason' => $voidReason]) }}</p>
                    @endif
                @endif
            </header>

            {{-- RF-23/S-2: datos tributarios del receptor cuando se emitió factura. --}}
            @if ($document?->type === \App\Pos\Enum\DocumentType::invoice && $sale->invoiceDetail !== null)
                <section class="mt-4 rounded-lg border border-slate-200 p-3 text-sm" aria-labelledby="invoice-data-title">
                    <h2 id="invoice-data-title" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ __('pos.receipt.invoice_heading') }}
                    </h2>
                    <p class="mt-1">{{ __('pos.receipt.invoice_rut', ['rut' => $sale->invoiceDetail->rut]) }}</p>
                    <p>{{ __('pos.receipt.invoice_business_name', ['name' => $sale->invoiceDetail->business_name]) }}</p>
                    <p>{{ __('pos.receipt.invoice_business_activity', ['activity' => $sale->invoiceDetail->business_activity]) }}</p>
                    <p>{{ __('pos.receipt.invoice_address', ['address' => $sale->invoiceDetail->address]) }}</p>
                    <p>{{ __('pos.receipt.invoice_commune', ['commune' => $sale->invoiceDetail->commune]) }}</p>
                </section>
            @endif

            <table class="mt-4 w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="pb-2">{{ __('pos.receipt.product') }}</th>
                        <th class="pb-2 text-right">{{ __('pos.receipt.quantity') }}</th>
                        <th class="pb-2 text-right">{{ __('pos.receipt.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($sale->lines as $line)
                        <tr>
                            <td class="py-2">
                                {{ $line->name }}
                                <span class="block text-xs text-slate-500">
                                    {{ __('pos.common.per_unit', ['price' => $money($line->unit_price)]) }}
                                </span>
                            </td>
                            <td class="py-2 text-right">{{ $line->quantity }}</td>
                            <td class="py-2 text-right">{{ $money($line->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <dl class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.receipt.subtotal') }}</dt>
                    <dd>{{ $money($sale->subtotal) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-600">{{ __('pos.receipt.discounts') }}</dt>
                    <dd>-{{ $money($sale->line_discounts + $sale->global_discount) }}</dd>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <dt>{{ __('pos.receipt.net') }}</dt>
                    <dd>{{ $money($sale->net) }}</dd>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <dt>{{ __('pos.receipt.iva', ['rate' => config('pos.iva_rate')]) }}</dt>
                    <dd>{{ $money($sale->iva) }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-bold">
                    <dt>{{ __('pos.receipt.total') }}</dt>
                    <dd>{{ $money($sale->total) }}</dd>
                </div>
            </dl>

            <div class="mt-3 space-y-1 text-sm">
                <p>{{ __('pos.receipt.payment_method', ['method' => $sale->payment_method->label()]) }}</p>
                @if ($sale->received_amount !== null)
                    <p>{{ __('pos.receipt.received', ['amount' => $money($sale->received_amount)]) }}</p>
                    <p>
                        {{ __('pos.receipt.change', ['amount' => $money($sale->change_amount ?? 0)]) }}
                    </p>
                @endif
                @if ($sale->discount_origin === \App\Pos\Enum\DiscountOrigin::manual)
                    <p>
                        {{ __('pos.receipt.manual_discount', ['amount' => $money($sale->global_discount)]) }}
                    </p>
                    <p>{{ __('pos.receipt.reason', ['reason' => $sale->discount_reason]) }}</p>
                    @if ($sale->discountAuthorizedBy)
                        <p>{{ __('pos.receipt.authorized_by', ['name' => $sale->discountAuthorizedBy->name]) }}</p>
                    @endif
                @endif
                @if ($sale->convention_name !== null)
                    <p>
                        {{ __('pos.receipt.convention', [
                            'name' => $sale->convention_name,
                            'percent' => $percent($sale->convention_percent ?? 0),
                        ]) }}
                    </p>
                    <p>{{ __('pos.receipt.convention_savings', ['amount' => $money($sale->global_discount)]) }}</p>
                @endif
            </div>

            <footer class="mt-6 border-t border-dashed border-slate-300 pt-4 text-center text-xs text-slate-500">
                <p class="font-semibold text-slate-700">{{ __('pos.receipt.simulated') }}</p>
                <p>{{ __('pos.receipt.not_sii') }}</p>
            </footer>
        </article>

        <div class="mt-4 flex gap-3 print:hidden">
            <a href="{{ route('pos.index') }}" class="flex-1 rounded-lg bg-emerald-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('pos.receipt.new_sale') }}
            </a>
            <button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">
                {{ __('pos.receipt.print') }}
            </button>
        </div>
    </div>
@endsection
