@php
    $pageLink = 'grid h-9 min-w-9 place-items-center rounded-lg px-2.5 text-sm font-medium transition';
@endphp

<div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
    <p class="text-sm text-slate-600">
        Showing <span class="font-semibold text-slate-900">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
        of <span class="font-semibold text-slate-900">{{ $paginator->total() }}</span> {{ Str::plural('shoe', $paginator->total()) }}
    </p>

    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pages" class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Previous page" class="{{ $pageLink }} cursor-not-allowed text-slate-300">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" class="{{ $pageLink }} text-slate-700 hover:bg-slate-100">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
            @endif

            {{-- Page numbers, with … standing in for long runs of pages. --}}
            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-hidden="true" class="{{ $pageLink }} text-slate-400">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $pageLink }} bg-brand-900 text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Page {{ $page }}" class="{{ $pageLink }} text-slate-700 hover:bg-slate-100">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            {{-- Phones show where they are instead of every page number. --}}
            <span class="px-2 text-sm font-medium text-slate-700 sm:hidden">
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" class="{{ $pageLink }} text-slate-700 hover:bg-slate-100">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="Next page" class="{{ $pageLink }} cursor-not-allowed text-slate-300">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </span>
            @endif
        </nav>
    @endif
</div>
