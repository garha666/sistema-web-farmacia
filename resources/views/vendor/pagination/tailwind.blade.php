@if ($paginator->hasPages())
    <nav
        role="navigation"
        aria-label="{{ __('pos.common.pagination.label') }}"
        class="flex flex-col items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-sm sm:flex-row sm:justify-between"
    >
        <p class="text-sm text-slate-500">
            {{ __('pos.common.pagination.showing', [
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span
                    aria-disabled="true"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-400"
                >
                    {{ __('pos.common.pagination.previous') }}
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-1"
                >
                    {{ __('pos.common.pagination.previous') }}
                </a>
            @endif

            <span class="px-2 text-sm font-medium tabular-nums text-slate-600">
                {{ __('pos.common.pagination.page', [
                    'current' => $paginator->currentPage(),
                    'last' => $paginator->lastPage(),
                ]) }}
            </span>

            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-hidden="true" class="px-1 text-sm text-slate-400">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span
                                    aria-current="page"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold tabular-nums text-white"
                                >
                                    {{ $page }}
                                </span>
                            @else
                                <a
                                    href="{{ $url }}"
                                    aria-label="{{ __('pos.common.pagination.go_to', ['page' => $page]) }}"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium tabular-nums text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-1"
                                >
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-1"
                >
                    {{ __('pos.common.pagination.next') }}
                </a>
            @else
                <span
                    aria-disabled="true"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-400"
                >
                    {{ __('pos.common.pagination.next') }}
                </span>
            @endif
        </div>
    </nav>
@endif
