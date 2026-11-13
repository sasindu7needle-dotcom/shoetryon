<x-admin-layout title="Dashboard">
    <x-slot:header>
        <h1 class="rise text-3xl font-bold sm:text-4xl">Welcome back, {{ auth()->user()->name }}</h1>
        <p class="rise mt-2 text-white/85" style="animation-delay:.1s">Here is how the collection looks on the site right now.</p>

        <dl class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            @foreach ([
                ['Shoes in library', $stats['total']],
                ['Visible on site', $stats['visible']],
                ['Hidden', $stats['hidden']],
                ['AR ready', $stats['arReady']],
            ] as [$label, $value])
                <div class="rounded-2xl bg-white/15 p-4 backdrop-blur-md sm:p-5">
                    <dt class="text-sm text-white/80">{{ $label }}</dt>
                    <dd class="mt-1 text-3xl font-extrabold sm:text-4xl">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-slot:header>

    <section aria-labelledby="on-site-title" class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="flame-dots pointer-events-none absolute inset-x-0 top-0 h-28"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 p-5 sm:p-6">
            <div>
                <h2 id="on-site-title" class="text-2xl font-bold tracking-tight">On the site now</h2>
                <p class="mt-1 text-slate-600">
                    @if ($stats['visible'] > $visibleShoes->count())
                        The first {{ $visibleShoes->count() }} of {{ $stats['visible'] }} visible shoes, in the order shoppers see them.
                    @else
                        Shoes shoppers see, in the order they see them.
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.shoes.index') }}" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-700">Manage shoe library</a>
        </div>

        @if ($visibleShoes->isEmpty())
            <p class="relative p-10 text-center text-slate-500">No shoes are visible on the site. Unhide some in the shoe library.</p>
        @else
            <ul class="relative grid grid-cols-2 gap-4 p-5 sm:grid-cols-3 sm:p-6 lg:grid-cols-4">
                @foreach ($visibleShoes as $shoe)
                    <li>
                        <a href="{{ route('admin.shoes.edit', $shoe) }}" class="shoe-card group block rounded-xl p-2">
                            <img src="{{ asset($shoe->thumb) }}" alt="" loading="lazy" class="aspect-square w-full rounded-lg bg-slate-100 object-cover">
                            <span class="mt-2 block truncate px-1 text-sm font-semibold">{{ $shoe->name }}</span>
                            <span class="block px-1 pb-1 text-xs {{ $shoe->isArReady() ? 'text-emerald-600' : 'text-slate-400' }}">
                                {{ $shoe->isArReady() ? 'AR ready' : '3D only' }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-admin-layout>
