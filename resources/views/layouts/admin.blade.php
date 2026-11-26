<!DOCTYPE html>
<html lang="es">
@php
    // RF-44, RNF-19: el grupo sale del rol cuando la pantalla no lo declara.
    $navGroup = $navGroup ?? app(\App\Pos\Policy\LandingPolicy::class)->navGroupFor(auth()->user()?->role);
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('pos.app.admin')) · {{ __('pos.app.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-slate-900 lg:flex">
        <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-500 text-white shadow-lg shadow-emerald-900/40">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
                    <path d="M12 5v14M5 12h14" />
                </svg>
            </span>
            <span class="min-w-0">
                <h1 class="block truncate text-sm font-bold text-white">{{ __('pos.app.brand') }}</h1>
                <span class="block text-xs font-medium text-slate-400">{{ __('pos.panel.group.'.$navGroup) }}</span>
            </span>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm font-medium" aria-label="{{ __('pos.panel.group.'.$navGroup) }}">
            @include('partials.nav-links', ['variant' => 'sidebar'])
        </nav>

        <div class="border-t border-white/10 p-3">
            @unless (auth()->user()?->isBodeguero() || auth()->user()?->isQuimico())
                <a
                    href="{{ route('pos.index') }}"
                    class="mb-2 flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                        <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
                    </svg>
                    {{ __('pos.nav.pos') }}
                </a>
            @endunless

            {{-- T19 - RF-81: cerrar la sesión desde cualquier panel. --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-400 transition-colors hover:bg-white/5 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                        <path d="M15 12H3m0 0 4-4m-4 4 4 4m5-11h4a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4" />
                    </svg>
                    {{ __('pos.login.logout') }}
                </button>
            </form>
        </div>
    </aside>

    <div class="flex min-h-screen min-w-0 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur lg:hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-600 text-white">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <h1 class="block truncate text-sm font-bold leading-tight">{{ __('pos.app.brand') }}</h1>
                        <span class="block text-xs text-slate-500">{{ __('pos.panel.group.'.$navGroup) }}</span>
                    </span>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @unless (auth()->user()?->isBodeguero() || auth()->user()?->isQuimico())
                        <a href="{{ route('pos.index') }}" class="shrink-0 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                            {{ __('pos.nav.pos') }}
                        </a>
                    @endunless

                    {{-- T19 - RF-81: cerrar sesión también desde la barra móvil. --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                        >
                            {{ __('pos.login.logout') }}
                        </button>
                    </form>
                </div>
            </div>

            <nav class="flex gap-1 overflow-x-auto border-t border-slate-100 px-3 py-2 text-sm font-medium" aria-label="{{ __('pos.panel.group.'.$navGroup) }}">
                @include('partials.nav-links', ['variant' => 'mobile'])
            </nav>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-100" role="status">
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

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
    </div>
</body>
</html>
