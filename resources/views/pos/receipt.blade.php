@extends('layouts.pos')

@section('title', 'Comprobante')

@php
    $money = fn (int $pesos): string => '$'.number_format($pesos, 0, ',', '.');
@endphp

@section('content')
    <div class="mx-auto max-w-2xl">
        <article class="rounded-xl bg-white p-6 shadow-sm print:shadow-none">
            <header class="border-b border-dashed border-slate-300 pb-4 text-center">
                <p class="text-lg font-bold">{{ $issuer['name'] ?? 'Farmacia Grupo A SpA' }}</p>
                <p class="text-sm text-slate-600">RUT {{ $issuer['rut'] ?? '76.543.210-K' }}</p>
                <p class="text-sm text-slate-600">{{ $issuer['business'] ?? 'Farmacia' }} · {{ $issuer['address'] ?? '' }}</p>
                <p class="mt-3 text-sm font-semibold uppercase tracking-wide text-slate-700">
                    {{ $document?->type->label() ?? 'Boleta' }} electrónica
                </p>
                <p class="text-sm">Folio {{ $document?->folio ?? 'pendiente' }}</p>
                <p class="text-xs text-slate-500">{{ $sale->sold_at->format('d-m-Y H:i') }}</p>
            </header>

            <table class="mt-4 w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="pb-2">Producto</th>
                        <th class="pb-2 text-right">Cant.</th>
                        <th class="pb-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($sale->lines as $line)
                        <tr>
                            <td class="py-2">
                                {{ $line->name }}
                                <span class="block text-xs text-slate-500">{{ $money($line->unit_price) }} c/u</span>
                            </td>
                            <td class="py-2 text-right">{{ $line->quantity }}</td>
                            <td class="py-2 text-right">{{ $money($line->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <dl class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-600">Subtotal</dt>
                    <dd>{{ $money($sale->subtotal) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-600">Descuentos</dt>
                    <dd>-{{ $money($sale->line_discounts + $sale->global_discount) }}</dd>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <dt>Neto</dt>
                    <dd>{{ $money($sale->net) }}</dd>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <dt>IVA ({{ config('pos.iva_rate') }}%)</dt>
                    <dd>{{ $money($sale->iva) }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-bold">
                    <dt>Total</dt>
                    <dd>{{ $money($sale->total) }}</dd>
                </div>
            </dl>

            <div class="mt-3 space-y-1 text-sm">
                <p>Medio de pago: <span class="font-medium">{{ $sale->payment_method->label() }}</span></p>
                @if ($sale->received_amount !== null)
                    <p>Recibido: {{ $money($sale->received_amount) }}</p>
                    <p>Vuelto: <span class="font-semibold">{{ $money($sale->change_amount ?? 0) }}</span></p>
                @endif
            </div>

            <footer class="mt-6 border-t border-dashed border-slate-300 pt-4 text-center text-xs text-slate-500">
                <p class="font-semibold text-slate-700">Documento simulado — sin validez tributaria</p>
                <p>No corresponde a una boleta electrónica del SII.</p>
            </footer>
        </article>

        <div class="mt-4 flex gap-3 print:hidden">
            <a href="{{ route('pos.index') }}" class="flex-1 rounded-lg bg-emerald-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-emerald-700">
                Nueva venta
            </a>
            <button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">
                Imprimir
            </button>
        </div>
    </div>
@endsection
