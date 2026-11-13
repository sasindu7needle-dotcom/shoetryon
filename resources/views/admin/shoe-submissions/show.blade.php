@php
    $status = $submission->status;
@endphp

<x-admin-layout :title="$submission->name">
    <x-slot:header>
        <a href="{{ route('admin.shoe-submissions.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 text-sm font-medium transition hover:bg-white/25">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            Add new shoe
        </a>
        <h1 class="rise mt-5 text-3xl font-bold sm:text-4xl">{{ $submission->name }}</h1>
        <p class="rise mt-2 text-white/85" style="animation-delay:.1s">
            {{ $submission->slug }} · {{ $submission->shoe_type === 'boot' ? 'Boot' : 'Low shoe' }} · {{ rtrim(rtrim($submission->length_cm, '0'), '.') }} cm
            · sent {{ $submission->created_at->diffForHumans() }}@if ($submission->user) by {{ $submission->user->name }}@endif
        </p>
    </x-slot:header>

    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
        <aside class="flex flex-col gap-4 self-start rounded-2xl border border-slate-200 bg-white p-4">
            @if ($submission->photo_path)
                <img src="{{ asset($submission->photo_path) }}" alt="Front photo of {{ $submission->name }}" class="aspect-square w-full rounded-xl bg-slate-100 object-cover">
            @endif
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-semibold">Status</span>
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $status->badgeClasses() }}">{{ $status->label() }}</span>
            </div>
            @if ($submission->message)
                <p class="text-sm {{ $status === \App\ShoeSubmissionStatus::Failed ? 'text-rose-600' : 'text-slate-600' }}">{{ $submission->message }}</p>
            @endif
            @if ($submission->tripo_task_id)
                <p class="break-all text-xs text-slate-500">Tripo task {{ $submission->tripo_task_id }}</p>
            @endif
        </aside>

        <div class="flex flex-col gap-6">
            <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-xl font-bold">3D model</h2>

                @if ($submission->glb_path)
                    <div class="relative aspect-4/3 w-full overflow-hidden rounded-xl bg-slate-100">
                        <canvas data-model-preview="{{ asset($submission->glb_path) }}" class="size-full touch-none" aria-label="3D model of {{ $submission->name }}. Drag to turn it."></canvas>
                        <p data-model-status class="absolute inset-0 grid place-items-center text-sm text-slate-500">Loading the 3D model…</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-slate-500">Drag to turn it. Check the shape, the sole and the colours against the photo.</p>
                        <a href="{{ asset($submission->glb_path) }}" download="{{ $submission->slug }}.glb" class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium transition hover:bg-slate-100">Download GLB</a>
                    </div>
                    @vite('resources/js/model-preview.js')
                @elseif ($status === \App\ShoeSubmissionStatus::Processing)
                    <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Tripo is making the 3D model. It usually takes 1 to 3 minutes; reload the page to check.</p>
                @else
                    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">There is no 3D model for this shoe. Send it again from the Add new shoe page.</p>
                @endif
            </section>

            @if ($status === \App\ShoeSubmissionStatus::ModelReady)
                <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-bold">Add to the shoe library</h2>
                        <p class="mt-1 text-sm text-slate-500">The try-on needs a DeepAR effect. Download the GLB, import it into DeepAR Studio's shoe try-on template, export the <code>.deepar</code> file and upload it here. The shoe joins the library hidden, so you can test it on your phone and re-optimize its fit first.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.shoe-submissions.library.store', $submission) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                        @csrf
                        <div class="flex flex-col gap-1.5">
                            <label for="effect" class="text-sm font-semibold">DeepAR effect (.deepar)</label>
                            <input id="effect" name="effect" type="file" accept=".deepar" required class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-medium">
                            @error('effect')
                                <p class="text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn-flame">Add to library</button>
                        </div>
                    </form>
                </section>
            @elseif ($libraryShoe)
                <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <p class="text-sm text-slate-600">This shoe is in the library{{ $libraryShoe->is_visible ? '' : ', hidden' }}.</p>
                    <a href="{{ route('admin.shoes.edit', $libraryShoe) }}" class="btn-flame">Edit and re-optimize</a>
                </section>
            @endif
        </div>
    </div>
</x-admin-layout>
