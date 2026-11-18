{{--
    Enlaces de la administración (B12).

    Variantes:
      - sidebar: barra lateral de escritorio, con icono y fondo oscuro.
      - mobile:  barra horizontal de móvil, sólo texto.

    Se usa desde layouts/admin.blade.php con:
      @include('partials.nav-links', ['variant' => 'sidebar'])
--}}
@foreach (config('navigation.admin', []) as $item)
    @php
        $isActive = request()->routeIs($item['active']);
    @endphp

    @if ($variant === 'sidebar')
        <a
            href="{{ route($item['route']) }}"
            @if ($isActive) aria-current="page" @endif
            class="group flex items-center gap-3 rounded-lg px-3 py-2 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 {{ $isActive ? 'bg-white/10 text-white ring-1 ring-inset ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 {{ $isActive ? 'text-emerald-400' : 'text-slate-500 group-hover:text-slate-300' }}">
                <path d="{{ $item['icon'] }}" />
            </svg>
            <span class="truncate">{{ __($item['label']) }}</span>
        </a>
    @else
        <a
            href="{{ route($item['route']) }}"
            @if ($isActive) aria-current="page" @endif
            class="shrink-0 rounded-lg px-3 py-1.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 {{ $isActive ? 'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-200' : 'text-slate-600 hover:bg-slate-100' }}"
        >
            {{ __($item['label']) }}
        </a>
    @endif
@endforeach
