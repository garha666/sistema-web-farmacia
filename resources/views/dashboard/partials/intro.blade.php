{{--
    Encabezado común de los cuatro paneles (RF-41, RNF-16).

    Recibe el rol del panel y muestra su título, el rol de la sesión y la fecha
    del día en la zona horaria del negocio. Los indicadores los agrega cada
    panel en su propia tarea.
--}}
<header class="space-y-1">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __("pos.panel.{$role}.title") }}</h1>
    <p class="text-sm text-slate-600">{{ __("pos.panel.{$role}.lead") }}</p>
</header>

<dl @class(['grid gap-3', 'sm:grid-cols-2' => ! isset($pinConfigured), 'sm:grid-cols-3' => isset($pinConfigured)])>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('pos.panel.role') }}</dt>
        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ __('pos.role.'.auth()->user()->role) }}</dd>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('pos.panel.date') }}</dt>
        <dd class="mt-1 text-sm font-semibold tabular-nums text-slate-900">
            {{ now(config('pos.timezone'))->format('d-m-Y') }}
        </dd>
    </div>
    @isset($pinConfigured)
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('pos.nav.pos_pin') }}</dt>
            <dd class="mt-1 text-sm font-semibold">
                @if ($pinConfigured)
                    <span class="inline-block rounded-full bg-emerald-100 px-2 py-1 text-emerald-900">{{ __('pos.panel.quimico.pin_configured') }}</span>
                @else
                    <a class="inline-block rounded-full bg-amber-100 px-2 py-1 text-amber-900 underline" href="{{ route('account.pos-authorization.edit') }}">{{ __('pos.panel.quimico.pin_pending') }}</a>
                @endif
            </dd>
        </div>
    @endisset
</dl>
