@extends('layouts.admin')

@section('title', __('pos.admin.conventions.create_title'))

@section('content')
    <div class="mx-auto max-w-xl rounded-xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">{{ __('pos.admin.conventions.create_title') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('pos.admin.conventions.create_help') }}</p>

        @include('admin.conventions._form', [
            'action' => route('admin.conventions.store'),
            'method' => 'POST',
            'convention' => null,
        ])
    </div>
@endsection
