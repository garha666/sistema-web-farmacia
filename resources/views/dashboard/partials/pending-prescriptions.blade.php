@forelse ($prescriptionReviews as $review)
    @php($snapshot = $review->snapshot)
    <article class="mt-3 break-words rounded-lg border border-slate-200 bg-white p-3">
        <h3 class="font-semibold">{{ __('pos.checkout.prescription_number') }}: {{ $snapshot['prescription']['prescription_number'] }}</h3>
        <p class="text-sm">{{ __('pos.checkout.prescription_prescriber_name') }}: {{ $snapshot['prescription']['prescription_prescriber_name'] }}</p>
        <p class="text-sm">{{ __('pos.checkout.prescription_prescriber_rut') }}: {{ $snapshot['prescription']['prescription_prescriber_rut'] }}</p>
        <p class="text-sm">{{ __('pos.checkout.prescription_issued_at') }}: {{ $snapshot['prescription']['prescription_issued_at'] }}</p>
        <ul class="my-2 list-inside list-disc text-sm">
            @foreach ($snapshot['products'] as $item)
                <li>{{ __('pos.rx_review.items', ['name' => $item['name'], 'quantity' => $item['quantity'], 'category' => __('pos.rx_review.'.$item['category'])]) }}</li>
            @endforeach
        </ul>
        <p class="text-xs text-slate-600">{{ __('pos.rx_review.cashier', ['name' => $review->cashier->name]) }} · {{ __('pos.rx_review.expires', ['time' => $review->expires_at->format('H:i')]) }}</p>
        <form action="{{ route('dashboard.quimico.prescriptions.approve', $review) }}" method="POST" class="mt-3">
            @csrf
            <input type="hidden" name="fingerprint" value="{{ $review->fingerprint }}">
            <button class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800 focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">{{ __('pos.rx_review.approve') }}</button>
        </form>
    </article>
@empty
    <p class="mt-2 text-sm text-slate-600">{{ __('pos.rx_review.empty') }}</p>
@endforelse
@if ($prescriptionReviews->hasMorePages())
    <p class="mt-3 text-sm text-slate-600">{{ __('pos.rx_review.showing_oldest', ['count' => $prescriptionReviews->count()]) }}</p>
@endif
