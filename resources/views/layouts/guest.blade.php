<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('pos.login.title')) · {{ __('pos.app.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    {{-- RNF-25: pantallas sin sesión, en la identidad visual de S-8. --}}
    <div class="h-1 w-full bg-gradient-to-r from-emerald-700 via-emerald-500 to-teal-400" aria-hidden="true"></div>

    <main class="mx-auto flex w-full max-w-md flex-col justify-center px-4 py-10 sm:py-16">
        <div class="mb-6 flex items-center gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/20">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
                    <path d="M12 5v14M5 12h14" />
                </svg>
            </span>
            <span class="min-w-0">
                <span class="block truncate text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">{{ __('pos.app.brand') }}</span>
                <h1 class="truncate text-lg font-bold leading-tight">{{ __('pos.app.pos') }}</h1>
            </span>
        </div>

        @yield('content')
    </main>
</body>
</html>
