{{--
    Estado de convenios (RF-50).

    Recibe dos colecciones: activos próximos a vencer y vencidos. Un convenio
    vencido e inactivo no ofrece «Activar»: el servidor también lo rechaza. Un
    convenio vencido todavía activo sí puede desactivarse.
--}}
<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="conventions-status">
    <h3 id="conventions-status" class="text-lg font-semibold text-slate-900">{{ __('pos.panel.admin.conventions_heading') }}</h3>

    @if ($conventions['expiring']->isEmpty() && $conventions['expired']->isEmpty())
        <p class="mt-3 text-sm text-slate-500">{{ __('pos.panel.admin.conventions_empty') }}</p>
    @else
        @foreach (['expiring', 'expired'] as $group)
            @if ($conventions[$group]->isNotEmpty())
                <h4 class="mt-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    {{ __("pos.panel.admin.conventions_{$group}") }}
                </h4>

                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-[460px] divide-y divide-slate-200 text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.convention_name') }}</th>
                                <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.convention_validity') }}</th>
                                <th scope="col" class="px-2 py-2">{{ __('pos.panel.admin.convention_status') }}</th>
                                <th scope="col" class="px-2 py-2 text-right">{{ __('pos.panel.admin.convention_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($conventions[$group] as $convention)
                                <tr>
                                    <td class="px-2 py-2 font-medium text-slate-800">{{ $convention->name }}</td>
                                    <td class="px-2 py-2 tabular-nums text-slate-600">
                                        {{ $convention->valid_from->format('d-m-Y') }}
                                        {{ __('pos.admin.conventions.until') }}
                                        {{ $convention->valid_until->format('d-m-Y') }}
                                    </td>
                                    <td class="px-2 py-2 text-slate-600">
                                        {{ $convention->active ? __('pos.admin.conventions.active') : __('pos.admin.conventions.inactive') }}
                                        @if ($group === 'expired')
                                            <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                                {{ __('pos.panel.admin.expired_badge') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        @if ($group === 'expired' && ! $convention->active)
                                            <span class="text-xs text-slate-400">{{ __('pos.panel.admin.expired_badge') }}</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.conventions.toggle', $convention) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded text-sm font-semibold text-emerald-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                                    {{ $convention->active ? __('pos.panel.admin.deactivate') : __('pos.panel.admin.activate') }}
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    @endif
</section>
