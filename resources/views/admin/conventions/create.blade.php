@extends('layouts.admin')

@section('title', 'Nuevo convenio')

@section('content')
    <div class="mx-auto max-w-xl rounded-xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Nuevo convenio</h2>
        <p class="mt-1 text-sm text-slate-500">Nombre, porcentaje de descuento y vigencia.</p>

        @include('admin.conventions._form', [
            'action' => route('admin.conventions.store'),
            'method' => 'POST',
            'convention' => null,
        ])
    </div>
@endsection
