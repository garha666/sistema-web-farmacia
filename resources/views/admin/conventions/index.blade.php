@extends('layouts.admin')

@section('title', 'Convenios')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">Convenios</h2>
            <p class="text-sm text-slate-500">Descuentos por convenio de salud (RF-39, RF-40).</p>
        </div>

        @if (auth()->user()?->isAdmin())
            <a href="{{ route('admin.conventions.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Nuevo convenio
            </a>
        @endif
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Descuento</th>
                    <th class="px-4 py-3">Vigencia</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($conventions as $convention)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $convention->name }}</td>
                        <td class="px-4 py-3">{{ rtrim(rtrim(number_format($convention->percent, 2, ',', '.'), '0'), ',') }}%</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $convention->valid_from->format('d-m-Y') }} al {{ $convention->valid_until->format('d-m-Y') }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($convention->active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Activo</span>
                            @else
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if (auth()->user()?->isAdmin())
                                <a href="{{ route('admin.conventions.edit', $convention) }}" class="text-sm font-semibold text-emerald-700 hover:underline">
                                    Editar
                                </a>

                                @if ($convention->active)
                                    <form method="POST" action="{{ route('admin.conventions.destroy', $convention) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-3 text-sm font-semibold text-red-600 hover:underline">
                                            Desactivar
                                        </button>
                                    </form>
                                @endif
                            @else
                                <span class="text-xs text-slate-400">Sólo lectura</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No hay convenios registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
