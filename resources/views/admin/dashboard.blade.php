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

        @if ($awaitingReview->isNotEmpty())
            <a href="#review-title" class="mt-4 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-slate-900 shadow-lg transition hover:brightness-95">
                <span class="relative flex size-3 shrink-0">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-500 opacity-60"></span>
                    <span class="relative inline-flex size-3 rounded-full bg-brand-500"></span>
                </span>
                <span class="min-w-0 flex-1 text-sm font-medium sm:text-base">
                    {{ $awaitingReview->count() }} {{ Str::plural('shoe', $awaitingReview->count()) }} {{ $awaitingReview->count() === 1 ? 'is' : 'are' }} waiting for you
                </span>
                <span class="shrink-0 text-sm font-semibold text-brand-900">See below ↓</span>
            </a>
        @endif
    </x-slot:header>

    @if ($awaitingReview->isNotEmpty())
        <section aria-labelledby="review-title" class="mb-6 scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white sm:mb-8">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 p-5 sm:p-6">
                <div>
                    <h2 id="review-title" class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        Needs your review
                        <span class="rounded-full bg-brand-500 px-2.5 py-0.5 text-sm font-semibold text-white">{{ $awaitingReview->count() }}</span>
                    </h2>
                    <p class="mt-1 text-slate-600">New shoes from n8n that are waiting on you before they reach the site.</p>
                </div>
                <a href="{{ route('admin.shoe-submissions.create') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">All submissions</a>
            </div>

            <ul class="divide-y divide-slate-200">
                @foreach ($awaitingReview as $submission)
                    <li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
                        <div class="flex min-w-0 flex-1 items-center gap-4">
                            <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-100 text-slate-400 sm:size-20">
                                @if ($submission->photo_path)
                                    <img src="{{ route('admin.shoe-submissions.files.show', [$submission, 'photo']) }}" alt="" loading="lazy" class="size-full object-cover" onerror="this.remove()">
                                @endif
                                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 16c0-1 .6-2 2-2.4l4-1.1 2.5-4.5h3l1 3.5 4.3 1.5c1.3.5 2.2 1.7 2.2 3.1V18H3zM3 18h18"/></svg>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate font-semibold">{{ $submission->name }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $submission->status->badgeClasses() }}">{{ $submission->status->label() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $submission->status->nextStep() }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ $submission->slug }} · <time datetime="{{ $submission->updated_at->toIso8601String() }}" title="{{ $submission->updated_at->toDayDateTimeString() }}">{{ $submission->updated_at->diffForHumans() }}</time>
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('admin.shoe-submissions.show', $submission) }}" class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-700">
                            {{ $submission->status === \App\ShoeSubmissionStatus::ModelReady ? 'Review model' : 'Open' }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

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
