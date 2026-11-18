@extends('layouts.admin')

@section('title', __('pos.panel.admin.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'admin'])
    </div>
@endsection
