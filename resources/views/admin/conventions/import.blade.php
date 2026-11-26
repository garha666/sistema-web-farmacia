@extends('layouts.admin')
@section('title', __('benefits.import'))
@section('content')
    <h2 class="text-lg font-semibold">{{ __('benefits.import') }}</h2>
    <a class="underline" href="{{ route('admin.conventions.import.template') }}">{{ __('benefits.template') }}</a>
    <form method="POST" action="{{ route('admin.conventions.import.store') }}" enctype="multipart/form-data" class="my-4 grid max-w-md gap-4">
        @csrf
        <label>{{ __('pos.checkout.convention') }} <select name="convention_id" required>
            <option value="">—</option>
            @foreach ($conventions as $item)<option value="{{ $item->id }}" @selected(old('convention_id') == $item->id)>{{ $item->name }}</option>@endforeach
        </select></label>
        <label>{{ __('benefits.file') }} <input type="file" name="csv" accept=".csv" required></label>
        @foreach ($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
        <button class="rounded bg-emerald-700 p-2 text-white">{{ __('benefits.import') }}</button>
    </form>
    @isset($summary)
        <p role="status">{{ __('benefits.summary', array_merge($summary, ['skipped' => count($summary['skipped'])])) }}</p>
        <ul>@foreach ($summary['skipped'] as $row => $reason)<li>{{ __('benefits.row', compact('row', 'reason')) }}</li>@endforeach</ul>
    @endisset
@endsection
