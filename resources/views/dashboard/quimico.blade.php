@extends('layouts.admin')

@section('title', __('pos.panel.quimico.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'quimico'])
    </div>
@endsection
