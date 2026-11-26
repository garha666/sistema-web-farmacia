@extends('layouts.admin')

@section('title', __($warehouse ? 'receipts.warehouse_title' : 'receipts.review_title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">@yield('title')</h1>
        @if (session('receipt_status'))
            <p role="status" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('receipt_status') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-900">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        @if ($warehouse)
            <form method="POST" enctype="multipart/form-data" action="{{ route('inventory.receipts.store') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                @csrf
                <label class="block text-sm font-medium" for="receipt-product">{{ __('receipts.fields.product_id') }}</label>
                <select id="receipt-product" name="product_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="">{{ __('receipts.choose_product') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) $product->id === (string) old('product_id', ''))>{{ $product->name }}</option>
                    @endforeach
                </select>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['lot' => 'text', 'expires_on' => 'date', 'expected_quantity' => 'number', 'received_quantity' => 'number'] as $field => $type)
                        <div class="min-w-0">
                            <label for="{{ $field }}" class="block text-sm font-medium">{{ __('receipts.fields.'.$field) }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" required
                                @if ($type === 'number') inputmode="numeric" min="{{ $field === 'expected_quantity' ? 1 : 0 }}" max="100000" step="1" @endif
                                @if ($type === 'date') min="{{ $today }}" @endif
                                @if ($type === 'text') maxlength="50" @endif
                                value="{{ is_scalar(old($field)) ? old($field) : '' }}" class="mt-1 w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                    @endforeach
                </div>
                <div>
                    <label for="photo" class="block text-sm font-medium">{{ __('receipts.fields.photo') }}</label>
                    <p id="photo-hint" class="text-xs text-slate-600">{{ __('receipts.photo_hint') }}</p>
                    <input id="photo" name="photo" required type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-hint" class="mt-2 block w-full min-w-0 text-sm">
                </div>
                <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ __('receipts.register') }}</button>
            </form>
        @endif

        <section aria-labelledby="receipt-history" class="space-y-3">
            <h2 id="receipt-history" class="text-lg font-semibold">{{ __('receipts.history') }}</h2>
            @forelse ($receipts as $receipt)
                <article class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="font-semibold">{{ $receipt->product->name }} <span class="text-sm font-normal text-slate-600">#{{ $receipt->id }}</span></h3>
                        <p class="rounded-md bg-slate-100 px-2 py-1 text-sm font-medium">{{ __('receipts.status.'.$receipt->status) }}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        @foreach (['lot' => $receipt->lot, 'expires_on' => $receipt->expires_on->format('d-m-Y'), 'expected_quantity' => $receipt->expected_quantity, 'received_quantity' => $receipt->received_quantity] as $field => $value)
                            <div class="min-w-0 break-words"><dt class="text-slate-600">{{ __('receipts.fields.'.$field) }}</dt><dd class="font-semibold tabular-nums">{{ $value }}</dd></div>
                        @endforeach
                        <div><dt class="text-slate-600">{{ __('receipts.difference') }}</dt><dd class="font-bold tabular-nums">{{ $receipt->difference() < 0 ? '−' : ($receipt->difference() > 0 ? '+' : '') }}{{ abs($receipt->difference()) }}</dd></div>
                    </dl>
                    <p class="text-sm text-slate-600">{{ __('receipts.recorded_by', ['name' => $receipt->receiver->name]) }}</p>
                    <a class="inline-block text-sm font-semibold text-emerald-800 underline" href="{{ route('inventory.receipts.photo', $receipt) }}">{{ __('receipts.download') }}</a>
                    @if ($receipt->reviewer !== null)
                        <p class="text-sm text-slate-600">{{ __('receipts.reviewed_by', ['name' => $receipt->reviewer->name, 'date' => $receipt->reviewed_at->format('d-m-Y H:i')]) }}</p>
                        <p class="break-words text-sm">{{ $receipt->review_reason }}</p>
                    @elseif (! $warehouse && $receipt->status === 'pending')
                        <form method="POST" action="{{ route('inventory.receipts.update', $receipt) }}" class="space-y-2 border-t border-slate-200 pt-3">
                            @csrf
                            @method('PATCH')
                            <label class="block text-sm font-medium" for="reason-{{ $receipt->id }}">{{ __('receipts.fields.reason') }}</label>
                            <textarea id="reason-{{ $receipt->id }}" name="reason" required rows="2" maxlength="500" class="w-full rounded-lg border border-slate-300 p-2"></textarea>
                            @if ($receipt->expires_on->toDateString() < $today)<p class="text-sm text-rose-800">{{ __('receipts.expired') }}</p>@endif
                            <div class="flex flex-wrap gap-2">
                                <button name="decision" value="approved" @disabled($receipt->expires_on->toDateString() < $today) class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ __('receipts.approve') }}</button>
                                <button name="decision" value="rejected" class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-800">{{ __('receipts.reject') }}</button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-600">{{ __('receipts.empty') }}</p>
            @endforelse
            {{ $receipts->links() }}
        </section>
    </div>
@endsection
