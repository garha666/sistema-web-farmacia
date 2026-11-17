<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('pos.cash.title'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="h-1 w-full bg-gradient-to-r from-emerald-700 via-emerald-500 to-teal-400 print:hidden" aria-hidden="true"></div>

    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/20">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">{{ __('pos.app.brand') }}</span>
                    <h1 class="truncate text-lg font-bold leading-tight">{{ __('pos.app.pos') }}</h1>
                </span>
            </div>

            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-200">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                {{ __('pos.app.register') }}
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-4 sm:py-6">
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 ring-1 ring-inset ring-red-100" role="alert">
                <ul class="list-disc space-y-0.5 pl-5">
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
