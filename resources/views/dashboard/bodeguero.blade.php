@extends('layouts.admin')

@section('title', __('pos.panel.bodeguero.title'))

@section('content')
    <div class="space-y-6">
        @include('dashboard.partials.intro', ['role' => 'bodeguero'])
    </div>
@endsection
