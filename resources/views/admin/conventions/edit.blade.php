@extends('layouts.admin')

@section('title', __('pos.admin.conventions.edit_title'))

@section('content')
    <div class="mx-auto max-w-xl rounded-xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">{{ __('pos.admin.conventions.edit_title') }}</h2>

        @include('admin.conventions._form', [
            'action' => route('admin.conventions.update', $convention),
            'method' => 'PATCH',
            'convention' => $convention,
        ])
    </div>
@endsection
