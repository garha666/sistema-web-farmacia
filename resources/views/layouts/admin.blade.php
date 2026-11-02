<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('pos.app.admin')) · {{ __('pos.app.brand') }}</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ __('pos.app.brand') }}</p>
                <h1 class="text-lg font-bold">{{ __('pos.app.admin') }}</h1>
            </div>

            <nav class="flex flex-wrap gap-2 text-sm font-medium" aria-label="{{ __('pos.app.admin') }}">
                <a href="{{ route('admin.sales.index') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">{{ __('pos.nav.sales') }}</a>
                <a href="{{ route('admin.documents.index') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">{{ __('pos.nav.documents') }}</a>
                <a href="{{ route('admin.controlled-register.index') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">{{ __('pos.nav.controlled') }}</a>
                <a href="{{ route('admin.conventions.index') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">{{ __('pos.nav.conventions') }}</a>
                <a href="{{ route('admin.audit.index') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">{{ __('pos.nav.audit') }}</a>
                <a href="{{ route('pos.index') }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">{{ __('pos.nav.pos') }}</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
