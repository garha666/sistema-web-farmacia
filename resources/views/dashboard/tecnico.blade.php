@extends('layouts.admin')

@section('title', __('pos.panel.tecnico.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'tecnico'])
    </div>
@endsection
