@php
    $status = $submission->status;
    $photoUrl = route('admin.shoe-submissions.files.show', [$submission, 'photo']);
    $modelUrl = route('admin.shoe-submissions.files.show', [$submission, 'model']);
    $missingFileHelp = 'Uploaded files are lost when the server restarts or is redeployed (Render\'s free disk is not kept). Send the shoe again from the Add new shoe page.';
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
            @if ($hasPhoto)
                <a href="{{ $photoUrl }}" target="_blank" rel="noopener" title="Open the photo full size">
                    <img src="{{ $photoUrl }}" alt="Front photo of {{ $submission->name }}" class="aspect-square w-full rounded-xl bg-slate-100 object-contain">
                </a>
            @elseif ($submission->photo_path)
                <p class="grid aspect-square w-full place-items-center rounded-xl bg-slate-100 p-4 text-center text-sm text-slate-500">The front photo is no longer on the server.</p>
            @endif
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-semibold">Status</span>
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $status->badgeClasses() }}">{{ $status->label() }}</span>
            </div>
            @if ($submission->message)
                <p class="text-sm {{ in_array($status, [\App\ShoeSubmissionStatus::Failed, \App\ShoeSubmissionStatus::Rejected], true) ? 'text-rose-600' : 'text-slate-600' }}">{{ $submission->message }}</p>
            @endif
            @if ($submission->reviewed_at)
                <div class="flex flex-col gap-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <p>Model {{ $status === \App\ShoeSubmissionStatus::Rejected ? 'rejected' : 'approved' }} {{ $submission->reviewed_at->diffForHumans() }}@if ($submission->reviewer) by {{ $submission->reviewer->name }}@endif.</p>
                    @if ($submission->review_note)
                        <p class="whitespace-pre-line text-slate-600">“{{ $submission->review_note }}”</p>
                    @endif
                </div>
            @endif
            @if ($submission->tripo_task_id)
                <p class="break-all text-xs text-slate-500">Tripo task {{ $submission->tripo_task_id }}</p>
            @endif
        </aside>

        <div class="flex flex-col gap-6">
            <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-xl font-bold">3D model</h2>

                @if ($hasModel)
                    <div class="relative aspect-4/3 w-full overflow-hidden rounded-xl bg-slate-100">
                        <canvas data-model-preview="{{ $modelUrl }}" class="size-full touch-none" aria-label="3D model of {{ $submission->name }}. Drag to turn it."></canvas>
                        <p data-model-status class="pointer-events-none absolute inset-0 grid place-items-center p-6 text-center text-sm text-slate-500">Loading the 3D model…</p>
                        <button type="button" data-model-reset class="absolute right-3 bottom-3 rounded-lg bg-white/90 px-3 py-1.5 text-xs font-medium shadow-sm transition hover:bg-white">Reset view</button>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-slate-500">Drag to turn it, scroll or pinch to zoom. Check the shape, the sole and the colours against the photo.</p>
                        <a href="{{ $modelUrl }}?download=1" class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium transition hover:bg-slate-100">Download GLB</a>
                    </div>
                    @vite('resources/js/model-preview.js')
                @elseif ($submission->glb_path)
                    <p class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">The 3D model file is no longer on the server. {{ $missingFileHelp }}</p>
                @elseif ($status === \App\ShoeSubmissionStatus::Processing)
                    <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Tripo is making the 3D model. It usually takes 1 to 3 minutes; reload the page to check.</p>
                @else
                    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">There is no 3D model for this shoe. Send it again from the Add new shoe page.</p>
                @endif
            </section>

            @if ($status === \App\ShoeSubmissionStatus::ModelReady)
                <section class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-bold">Review the 3D model</h2>
                        <p class="mt-1 text-sm text-slate-500">Step 1 of 2. Turn the model around and compare it with the photo. Approve it only if every check passes; otherwise reject it and say what is wrong.</p>
                    </div>

                    @if (! $hasModel)
                        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">The model can't be checked because its file is missing, so it can only be rejected.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.shoe-submissions.review.store', $submission) }}" class="flex flex-col gap-5">
                        @csrf
                        <fieldset class="flex flex-col gap-2.5" @disabled(! $hasModel)>
                            <legend class="mb-2 text-sm font-semibold">Checklist</legend>
                            @foreach ($reviewChecks as $check => $label)
                                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition has-checked:border-emerald-300 has-checked:bg-emerald-50">
                                    <input type="checkbox" name="checks[]" value="{{ $check }}" @checked(in_array($check, (array) old('checks', []), true)) class="mt-0.5 size-4 rounded accent-brand-900">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                            @error('checks')
                                <p class="text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <div class="flex flex-col gap-1.5">
                            <label for="note" class="text-sm font-semibold">Notes <span class="font-normal text-slate-500">(required to reject)</span></label>
                            <textarea id="note" name="note" rows="3" maxlength="2000" placeholder="e.g. The heel strap is missing and the sole is hollow." class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">{{ old('note') }}</textarea>
                            @error('note')
                                <p class="text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="submit" name="decision" value="reject" class="inline-flex h-11 items-center rounded-lg border border-rose-300 px-5 text-sm font-medium text-rose-700 transition hover:bg-rose-50">Reject model</button>
                            <button type="submit" name="decision" value="approve" @disabled(! $hasModel) class="btn-flame disabled:cursor-not-allowed disabled:opacity-50">Approve model</button>
                        </div>
                    </form>
                </section>
            @elseif ($status === \App\ShoeSubmissionStatus::ModelApproved)
                <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-bold">Add to the shoe library</h2>
                        <p class="mt-1 text-sm text-slate-500">Step 2 of 2. The try-on needs a DeepAR effect. Download the GLB, import it into DeepAR Studio's shoe try-on template, export the <code>.deepar</code> file and upload it here. The shoe joins the library hidden, so you can test it on your phone and re-optimize its fit first.</p>
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
            @elseif ($status === \App\ShoeSubmissionStatus::Rejected)
                <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <p class="text-sm text-slate-600">This model was rejected. Retake the photos with the review notes in mind and send the shoe again.</p>
                    <a href="{{ route('admin.shoe-submissions.create') }}" class="btn-flame">Send it again</a>
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
