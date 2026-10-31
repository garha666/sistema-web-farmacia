@extends('layouts.admin')

@section('title', 'Editar convenio')

@section('content')
    <div class="mx-auto max-w-xl rounded-xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Editar convenio</h2>

        @include('admin.conventions._form', [
            'action' => route('admin.conventions.update', $convention),
            'method' => 'PATCH',
            'convention' => $convention,
        ])
    </div>
@endsection
