{{--
    Indicador del panel (RF-46, RF-48).

    Recibe la etiqueta ya traducida y el valor ya formateado; se usa dentro de
    un <dl> para que el lector de pantalla anuncie término y valor juntos.
--}}
<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
    <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $value }}</dd>
</div>
