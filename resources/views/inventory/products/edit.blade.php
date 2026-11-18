@extends('layouts.admin')

@section('title', __('pos.inventory.products.edit_title'))

@section('content')
    <div class="mx-auto max-w-xl rounded-xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">{{ __('pos.inventory.products.edit_title') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('pos.inventory.products.edit_help') }}</p>

        @include('inventory.products._form', [
            'action' => route('inventory.products.update', $product),
            'method' => 'PATCH',
            'product' => $product,
        ])
    </div>
@endsection
