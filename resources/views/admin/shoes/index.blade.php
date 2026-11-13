@php
    $tabs = [
        [null, 'All', $counts['all']],
        ['visible', 'Visible', $counts['visible']],
        ['hidden', 'Hidden', $counts['hidden']],
    ];
    $keptQuery = array_filter(['search' => $search, 'per_page' => $perPage === $perPageOptions[0] ? null : $perPage]);
    $iconButton = 'grid size-9 place-items-center transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent';
@endphp

<x-admin-layout title="Shoe library">
    <x-slot:header>
        <h1 class="rise text-3xl font-bold sm:text-4xl">Shoe library</h1>
        <p class="rise mt-2 text-white/85" style="animation-delay:.1s">The site lists visible shoes in this order. Hidden shoes stay here until you unhide them.</p>
    </x-slot:header>

    <section aria-label="Shoes" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
            <nav aria-label="Filter by visibility" class="no-scrollbar flex gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1">
                @foreach ($tabs as [$tabStatus, $label, $count])
                    <a href="{{ route('admin.shoes.index', array_filter([...$keptQuery, 'status' => $tabStatus])) }}"
                       @if ($status === $tabStatus) aria-current="page" @endif
                       class="flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition {{ $status === $tabStatus ? 'bg-white text-brand-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ $label }}
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $status === $tabStatus ? 'bg-brand-500/10' : 'bg-slate-200' }}">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('admin.shoes.index') }}" role="search" class="flex flex-wrap items-center gap-2">
                @if ($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <label class="relative min-w-0 flex-1 sm:w-64 sm:flex-none">
                    <span class="sr-only">Search shoes</span>
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search by name" maxlength="80"
                           class="h-10 w-full rounded-lg border border-slate-300 pr-3 pl-9 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <span class="sr-only sm:not-sr-only">Show</span>
                    <select name="per_page" data-auto-submit aria-label="Shoes per page"
                            class="h-10 rounded-lg border border-slate-300 bg-white px-2 text-sm text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-700">Search</button>
                @if ($search !== '')
                    <a href="{{ route('admin.shoes.index', array_filter(['status' => $status, 'per_page' => $keptQuery['per_page'] ?? null])) }}" class="h-10 content-center px-2 text-sm font-medium text-slate-500 hover:text-slate-900">Clear</a>
                @endif
            </form>
        </div>

        @if ($isFiltered && $shoes->total() > 0)
            <p class="border-b border-slate-200 bg-amber-50 px-5 py-2.5 text-sm text-amber-800">
                Reordering is off while a filter or search is on. <a href="{{ route('admin.shoes.index') }}" class="font-semibold underline">Show all shoes</a> to change the order.
            </p>
        @endif

        @if ($shoes->isEmpty())
            <div class="p-10 text-center">
                @if ($isFiltered)
                    <p class="text-slate-500">No shoes match {{ $search !== '' ? '“'.$search.'”' : 'this filter' }}.</p>
                    <a href="{{ route('admin.shoes.index') }}" class="mt-3 inline-block text-sm font-semibold text-brand-900 underline">Show all shoes</a>
                @else
                    <p class="text-slate-500">The library is empty.</p>
                @endif
            </div>
        @else
            <ol class="divide-y divide-slate-200">
                @foreach ($shoes as $shoe)
                    <li class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5 {{ $shoe->is_visible ? 'bg-white' : 'bg-slate-50' }}">
                        <div class="flex min-w-0 flex-1 items-center gap-4">
                            <span class="w-8 shrink-0 text-center text-lg font-extrabold text-flame" title="Position in the library">{{ $shoe->position }}</span>
                            <img src="{{ asset($shoe->thumb) }}" alt="" loading="lazy" width="80" height="80"
                                 class="size-16 shrink-0 rounded-xl border border-slate-200 bg-slate-100 object-cover sm:size-20 {{ $shoe->is_visible ? '' : 'opacity-50 grayscale' }}">
                            <div class="min-w-0">
                                <p class="truncate font-semibold sm:text-lg">{{ $shoe->name }}</p>
                                <p class="truncate text-sm text-slate-500">{{ $shoe->slug }}</p>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    @if ($shoe->is_visible)
                                        <span class="rounded-full bg-brand-500/10 px-2.5 py-0.5 text-xs font-medium text-brand-900">Visible</span>
                                    @else
                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-600">Hidden</span>
                                    @endif
                                    @if ($shoe->isArReady())
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">AR ready</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">3D only</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 sm:shrink-0 sm:justify-end sm:gap-2">
                            <div class="flex overflow-hidden rounded-lg border border-slate-300">
                                @foreach ([['up', 'Move up', 'M12 19V5M6 11l6-6 6 6', $shoe->position <= 1], ['down', 'Move down', 'M12 5v14M6 13l6 6 6-6', $shoe->position >= $lastPosition]] as [$direction, $label, $iconPath, $isAtEnd])
                                    <form method="POST" action="{{ route('admin.shoes.position.update', $shoe) }}" @class(['border-l border-slate-300' => $direction === 'down'])>
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button type="submit" @disabled($isFiltered || $isAtEnd) aria-label="{{ $label }}: {{ $shoe->name }}"
                                                title="{{ $isFiltered ? 'Show all shoes to reorder' : $label }}" class="{{ $iconButton }}">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $iconPath }}"/></svg>
                                        </button>
                                    </form>
                                @endforeach
                            </div>

                            <a href="{{ route('admin.shoes.edit', $shoe) }}" class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-700">Edit</a>

                            @if ($shoe->is_visible)
                                <form method="POST" action="{{ route('admin.shoes.visibility.destroy', $shoe) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hide from the site, keep in the library" aria-label="Hide {{ $shoe->name }}"
                                            class="inline-flex h-9 w-21 items-center justify-center gap-1.5 rounded-lg border border-slate-300 sm:w-24 text-sm font-medium transition hover:bg-slate-100">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 5.1A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7a13 13 0 0 1-3.2 4.3M6.6 6.6A13 13 0 0 0 2 12c1 2.5 5 7 10 7a9.6 9.6 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                                        Hide
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.shoes.visibility.store', $shoe) }}">
                                    @csrf
                                    <button type="submit" title="Show on the site again" aria-label="Unhide {{ $shoe->name }}"
                                            class="inline-flex h-9 w-21 items-center justify-center gap-1.5 rounded-lg border border-brand-500 sm:w-24 text-sm font-medium text-brand-900 transition hover:bg-brand-500/10">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12c1-2.5 5-7 10-7s9 4.5 10 7c-1 2.5-5 7-10 7s-9-4.5-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Unhide
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.shoes.destroy', $shoe) }}" data-confirm="Delete {{ $shoe->name }} from the library? This cannot be undone.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" aria-label="Delete {{ $shoe->name }}" title="Delete" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-200 px-2.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50 sm:px-3.5">
                                    <svg class="size-4 sm:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                                    <span class="hidden sm:inline">Delete</span>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($shoes->total() > 0)
            <div class="border-t border-slate-200 p-4 sm:px-5">
                {{ $shoes->onEachSide(1)->links('admin.partials.pagination') }}
            </div>
        @endif
    </section>

    <script>
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (! confirm(form.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
        document.querySelectorAll('[data-auto-submit]').forEach((select) => {
            select.addEventListener('change', () => select.form.requestSubmit());
        });
    </script>
</x-admin-layout>
