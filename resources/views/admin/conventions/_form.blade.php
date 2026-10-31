@php
    $isEdit = $convention !== null;
@endphp

<form method="POST" action="{{ $action }}" class="mt-5 space-y-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="name" class="text-sm font-semibold text-slate-700">Nombre</label>
        <input
            id="name"
            name="name"
            type="text"
            maxlength="100"
            required
            value="{{ old('name', $convention?->name) }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
        >
    </div>

    <div>
        <label for="percent" class="text-sm font-semibold text-slate-700">Descuento (%)</label>
        <input
            id="percent"
            name="percent"
            type="number"
            min="0.01"
            max="100"
            step="0.01"
            required
            value="{{ old('percent', $convention?->percent) }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
        >
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="valid_from" class="text-sm font-semibold text-slate-700">Vigente desde</label>
            <input
                id="valid_from"
                name="valid_from"
                type="date"
                required
                value="{{ old('valid_from', $convention?->valid_from?->toDateString()) }}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            >
        </div>

        <div>
            <label for="valid_until" class="text-sm font-semibold text-slate-700">Vigente hasta</label>
            <input
                id="valid_until"
                name="valid_until"
                type="date"
                required
                value="{{ old('valid_until', $convention?->valid_until?->toDateString()) }}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            >
        </div>
    </div>

    @if ($isEdit)
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="active" value="0">
            <input
                type="checkbox"
                name="active"
                value="1"
                class="rounded border-slate-300 text-emerald-600"
                @checked(old('active', $convention->active))
            >
            Convenio activo
        </label>
    @endif

    <div class="flex gap-3 pt-2">
        <a href="{{ route('admin.conventions.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
            Cancelar
        </a>
        <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Guardar
        </button>
    </div>
</form>
