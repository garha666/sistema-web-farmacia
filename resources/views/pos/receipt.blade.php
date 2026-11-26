@extends('layouts.pos')

@section('title', __('pos.receipt.title'))

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
    $percent = fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
@endphp

@section('content')
    {{-- B3 (RNF-10): ticket de 80 mm. El layout no expone un stack de estilos,
         así que la regla de impresión vive con la vista que la necesita. --}}
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 4mm;
            }

            body {
                font-size: 11pt;
            }

            /* El encabezado y la navegación del layout no van en el papel. */
            body > header {
                display: none;
            }

            main {
                padding: 0;
            }

            .receipt-page,
            .receipt-ticket {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>

    <div class="mx-auto max-w-2xl receipt-page">
        <article class="rounded-xl bg-white p-6 shadow-sm print:shadow-none receipt-ticket">
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
                    @if ($document?->folio !== null)
                        {{ __('pos.receipt.folio', ['folio' => $document->folio]) }}
                    @elseif ($document !== null)
                        {{ __('pos.receipt.folio_pending') }}
                    @else
                        {{ __('pos.receipt.no_folio') }}
                    @endif
                </p>
                <p class="text-xs text-slate-500">{{ $sale->sold_at->format('d-m-Y H:i') }}</p>
                @if ($document?->status === \App\Pos\Enum\DocumentStatus::pending)
                    <p class="mt-2 inline-block rounded bg-amber-100 px-3 py-1 text-sm font-bold tracking-widest text-amber-900">
                        {{ __('pos.receipt.provisional') }}
                    </p>
                    <p class="mt-1 text-xs text-amber-800">{{ __('pos.receipt.provisional_notice') }}</p>
                @endif
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
            <button type="button" id="print-receipt" class="rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">
                {{ __('pos.receipt.print') }}
            </button>
        </div>

        @if (in_array(auth()->user()?->role, \App\Models\User::rolesThatCanVoid(), true)
            && $sale->status === \App\Pos\Enum\SaleStatus::confirmed)
            <section class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 sm:p-5 print:hidden" aria-labelledby="void-sale-title">
                <h2 id="void-sale-title" class="text-base font-semibold text-rose-950">{{ __('pos.receipt.void_heading') }}</h2>
                <p class="mt-1 text-sm leading-6 text-rose-900">{{ __('pos.receipt.void_help') }}</p>

                <form method="POST" action="{{ route('pos.sales.void', $sale) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="void-reason" class="block text-sm font-semibold text-slate-900">{{ __('pos.receipt.void_reason_label') }}</label>
                        <p id="void-reason-hint" class="mt-1 text-xs text-slate-600">{{ __('pos.receipt.void_reason_hint') }}</p>
                        <textarea id="void-reason" name="reason" rows="3" minlength="3" maxlength="500" required
                            aria-describedby="void-reason-hint @error('reason') void-reason-error @enderror"
                            @error('reason') aria-invalid="true" @enderror
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-300">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p id="void-reason-error" role="alert" class="mt-1 text-sm font-medium text-rose-800">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-rose-700 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-700 focus-visible:ring-offset-2 sm:w-auto">
                        {{ __('pos.receipt.void_submit') }}
                    </button>
                </form>
            </section>
        @endif
    </div>

    <script nonce="{{ $cspNonce }}">
        // RNF-27: sin manejadores en línea; el botón se enlaza desde aquí.
        document.getElementById('print-receipt')?.addEventListener('click', () => window.print());
    </script>
@endsection
