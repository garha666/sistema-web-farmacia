{{--
    Alertas de inventario del día (RF-48).

    Recibe los conteos de `AdminPanelService`. Los productos fuera de catálogo
    no cuentan: no se van a reponer. El enlace al catálogo aparece cuando la
    ruta existe (T16); hasta entonces el bloque sigue siendo informativo.
--}}
<section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="inventory-alerts">
    <h3 id="inventory-alerts" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.admin.inventory_heading') }}</h3>

    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-amber-900">{{ __('pos.panel.admin.below_minimum') }}</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-amber-900">{{ $inventory['belowMinimum'] }}</dd>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-red-900">{{ __('pos.panel.admin.out_of_stock') }}</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-red-900">{{ $inventory['outOfStock'] }}</dd>
        </div>
    </dl>

    @if (Route::has('inventory.products.index'))
        <a href="{{ route('inventory.products.index') }}" class="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:underline">
            {{ __('pos.panel.admin.inventory_link') }}
        </a>
    @endif
</section>
