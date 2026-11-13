@php
    $input = 'h-11 w-full rounded-lg border border-slate-300 px-3 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30';
    // Short titles fit the small two-column tiles on phones; the hint says which way the shoe faces.
    $photoTitles = [
        'front_photo' => 'Front',
        'right_photo' => 'Right side',
        'back_photo' => 'Back',
        'left_photo' => 'Left side',
    ];
    $photoHints = [
        'front_photo' => 'Toe towards the camera',
        'right_photo' => 'Outer side of the right shoe',
        'back_photo' => 'Heel towards the camera',
        'left_photo' => 'Inner side of the right shoe',
    ];
@endphp

<x-admin-layout title="Add new shoe">
    <x-slot:header>
        <h1 class="rise text-3xl font-bold sm:text-4xl">Add new shoe</h1>
        <p class="rise mt-2 max-w-2xl text-white/85" style="animation-delay:.1s">Upload a photo of the shoe. n8n has Tripo turn it into a 3D model and sends it back here, usually in 1 to 3 minutes. You preview it on your feet and approve it, and it joins the library hidden until it passes the live try-on.</p>
    </x-slot:header>

    @unless ($isWorkflowConfigured)
        <div role="alert" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">The n8n workflow is not connected yet.</p>
            <p class="mt-1">Set <code class="rounded bg-amber-100 px-1">N8N_NEW_SHOE_FORM_URL</code> (the workflow's production form URL), <code class="rounded bg-amber-100 px-1">N8N_FORM_USERNAME</code> and <code class="rounded bg-amber-100 px-1">N8N_FORM_PASSWORD</code> in the environment, then reload this page.</p>
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.shoe-submissions.store') }}" enctype="multipart/form-data" id="new-shoe-form"
          data-max-photo-bytes="{{ $maxPhotoBytes }}" data-max-total-bytes="{{ $maxTotalBytes }}"
          class="grid gap-6 xl:grid-cols-[1fr_320px]">
        @csrf

        <div class="flex flex-col gap-6">
            <section class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-xl font-bold">Shoe details</h2>

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-sm font-semibold">Shoe name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="80" placeholder="e.g. Leather Loafer" class="{{ $input }}">
                    <p class="text-xs text-slate-500">Shoe id: <span data-slug-preview class="font-mono text-slate-700">{{ old('name') ? \App\Models\ShoeSubmission::slugFor(old('name')) : '…' }}</span>. The files are named after it, so it must be new.</p>
                    @error('name')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <fieldset class="flex flex-col gap-1.5">
                    <legend class="text-sm font-semibold">Type</legend>
                    <div class="mt-1.5 grid gap-3 sm:grid-cols-2">
                        @foreach ($shoeTypes as $value => $label)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-300 p-3.5 transition has-checked:border-brand-500 has-checked:bg-brand-500/5 has-checked:ring-2 has-checked:ring-brand-500/30">
                                <input type="radio" name="shoe_type" value="{{ $value }}" required @checked(old('shoe_type', 'low') === $value) class="mt-0.5 size-4 accent-brand-900">
                                <span class="text-sm">
                                    <span class="block font-semibold">{{ $value === 'boot' ? 'Boot' : 'Low shoe' }}</span>
                                    <span class="block text-slate-500">{{ $value === 'boot' ? 'Covers the ankle' : 'Sneaker, flat, loafer, sandal' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('shoe_type')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="flex flex-col gap-1.5 sm:max-w-56">
                    <label for="length_cm" class="text-sm font-semibold">Shoe length (cm)</label>
                    <input id="length_cm" name="length_cm" type="number" min="15" max="40" step="0.1" value="{{ old('length_cm') }}" required placeholder="e.g. 27" class="{{ $input }}">
                    <p class="text-xs text-slate-500">Outside length, heel to toe.</p>
                    @error('length_cm')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div>
                    <h2 class="text-xl font-bold">Photos of the right shoe</h2>
                    <p class="mt-1 text-sm text-slate-500">The front photo is required, and it is the one Tripo makes the 3D model from. The other views are optional; they are sent along for a multi-view model later but not used yet.</p>
                    <p class="mt-1 text-sm text-slate-500">JPEG or PNG, up to {{ number_format($maxPhotoBytes / 1024 / 1024, 0) }} MB each. The try-on mirrors it onto the left foot.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($photos as $field => $label)
                        <div class="flex flex-col gap-1.5">
                            <label data-photo-drop class="group relative flex min-h-40 cursor-pointer flex-col sm:aspect-square sm:min-h-0 items-center justify-center gap-1.5 overflow-hidden rounded-xl border-2 border-dashed px-2 pt-7 pb-3 text-center sm:gap-2 sm:px-3 transition hover:border-brand-500 hover:bg-brand-500/5 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30 {{ $errors->has($field) ? 'border-rose-400 bg-rose-50' : 'border-slate-300 bg-slate-50' }}">
                                <input type="file" name="{{ $field }}" accept=".jpg,.jpeg,.png,image/jpeg,image/png" @required($field === $requiredPhoto) class="sr-only">
                                <span class="absolute top-2 left-2 z-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $field === $requiredPhoto ? 'bg-flame text-white' : 'bg-white/90 text-slate-500 shadow-sm' }}">{{ $field === $requiredPhoto ? 'Required' : 'Optional' }}</span>
                                <img data-photo-preview alt="" class="absolute inset-0 hidden size-full object-cover">
                                <svg class="size-7 shrink-0 text-slate-400 transition group-hover:text-brand-500 sm:size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h3l2-3h6l2 3h3v13H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                                <span class="text-sm font-semibold" title="{{ $label }}">{{ $photoTitles[$field] }}</span>
                                <span class="text-xs text-slate-500">{{ $photoHints[$field] }}</span>
                            </label>
                            <p data-photo-note class="min-h-4 truncate text-xs text-slate-500"></p>
                            @error($field)
                                <p class="text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <p data-size-warning hidden role="alert" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>
            </section>

            <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <p class="text-center text-sm text-slate-500 sm:text-left">Uploading can take a minute on a slow connection.</p>
                <button type="submit" data-submit @disabled(! $isWorkflowConfigured) class="btn-flame w-full disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Send to n8n</button>
            </div>
        </div>

        <aside class="flex flex-col gap-4 self-start rounded-2xl border border-slate-200 bg-white p-5 xl:sticky xl:top-6">
            <h2 class="text-lg font-bold">Getting good photos</h2>
            <ul class="flex flex-col gap-2.5 text-sm text-slate-600">
                @foreach ([
                    'Plain matte background and soft, even light.',
                    'Same camera height and distance for every photo.',
                    'Turn the shoe 90° between shots.',
                    'The shoe fills about 70% of the frame.',
                    'iPhone: Settings › Camera › Formats › Most Compatible, so photos save as JPEG.',
                ] as $tip)
                    <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-flame"></span>{{ $tip }}</li>
                @endforeach
            </ul>

            <h2 class="mt-2 text-lg font-bold">What happens next</h2>
            <ol class="flex flex-col gap-2.5 text-sm text-slate-600">
                @foreach ([
                    'n8n has Tripo make a 3D model (GLB) from the photos. It shows up in the list below, usually in 1 to 3 minutes.',
                    'Open it and preview it on your feet with your phone. Line it up, then approve it; it joins the library, hidden. No DeepAR Studio needed.',
                    'Try it on live. Re-optimize with Claude until it sits right, then publish it.',
                ] as $step)
                    <li class="flex gap-3"><span class="grid size-5 shrink-0 place-items-center rounded-full bg-brand-500/10 text-xs font-bold text-brand-900">{{ $loop->iteration }}</span>{{ $step }}</li>
                @endforeach
            </ol>
        </aside>
    </form>

    <section aria-labelledby="submissions-title" class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-5 sm:p-6">
            <h2 id="submissions-title" class="text-xl font-bold">Sent to n8n</h2>
            <p class="mt-1 text-sm text-slate-500">Open a shoe to see its 3D model and add it to the shoe library.</p>
        </div>

        @if ($submissions->isEmpty())
            <p class="p-8 text-center text-slate-500">No shoes sent yet.</p>
        @else
            <ul class="divide-y divide-slate-200">
                @foreach ($submissions as $submission)
                    <li class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <a href="{{ route('admin.shoe-submissions.show', $submission) }}" class="block truncate font-semibold text-brand-900 hover:underline">{{ $submission->name }}</a>
                            <p class="truncate text-sm text-slate-500">
                                {{ $submission->slug }} · {{ $submission->shoe_type === 'boot' ? 'Boot' : 'Low shoe' }} · {{ rtrim(rtrim($submission->length_cm, '0'), '.') }} cm
                                · <time datetime="{{ $submission->created_at->toIso8601String() }}" title="{{ $submission->created_at->toDayDateTimeString() }}">{{ $submission->created_at->diffForHumans() }}</time>
                                @if ($submission->user) · {{ $submission->user->name }} @endif
                            </p>
                            @if ($submission->status === \App\ShoeSubmissionStatus::Failed && $submission->message)
                                <p class="mt-1 text-sm text-rose-600">{{ $submission->message }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 self-start rounded-full px-2.5 py-1 text-xs font-medium sm:self-center {{ $submission->status->badgeClasses() }}">{{ $submission->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-200 p-4 sm:px-6">
                {{ $submissions->links('admin.partials.pagination') }}
            </div>
        @endif
    </section>

    <script>
        (() => {
            const form = document.getElementById('new-shoe-form');
            const maxPhotoBytes = Number(form.dataset.maxPhotoBytes);
            const maxTotalBytes = Number(form.dataset.maxTotalBytes);
            const warning = form.querySelector('[data-size-warning]');
            const submit = form.querySelector('[data-submit]');
            const inputs = [...form.querySelectorAll('input[type=file]')];
            const megabytes = (bytes) => (bytes / 1024 / 1024).toFixed(1) + ' MB';

            // Mirrors ShoeSubmission::slugFor so the admin sees the id before sending.
            const slugFor = (name) => name.trim().toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40).replace(/-+$/, '');
            const nameInput = form.querySelector('#name');
            const slugPreview = form.querySelector('[data-slug-preview]');
            nameInput.addEventListener('input', () => { slugPreview.textContent = slugFor(nameInput.value) || '…'; });

            const checkSizes = () => {
                const files = inputs.map((input) => input.files[0]).filter(Boolean);
                const tooBig = files.filter((file) => file.size > maxPhotoBytes);
                const total = files.reduce((sum, file) => sum + file.size, 0);
                let message = '';
                if (tooBig.length) {
                    message = `${tooBig.map((file) => file.name).join(', ')} ${tooBig.length > 1 ? 'are' : 'is'} over ${megabytes(maxPhotoBytes)}. Use a smaller photo.`;
                } else if (maxTotalBytes && total > maxTotalBytes) {
                    message = `The photos add up to ${megabytes(total)}, more than the server accepts (${megabytes(maxTotalBytes)}). Use smaller photos.`;
                }
                warning.textContent = message;
                warning.hidden = !message;
                return !message;
            };

            inputs.forEach((input) => {
                const drop = input.closest('[data-photo-drop]');
                const preview = drop.querySelector('[data-photo-preview]');
                const note = drop.parentElement.querySelector('[data-photo-note]');
                input.addEventListener('change', () => {
                    const file = input.files[0];
                    if (preview.src) URL.revokeObjectURL(preview.src);
                    preview.hidden = !file;
                    preview.classList.toggle('hidden', !file);
                    if (file) preview.src = URL.createObjectURL(file);
                    note.textContent = file ? `${file.name} · ${megabytes(file.size)}` : '';
                    checkSizes();
                });
            });

            form.addEventListener('submit', (event) => {
                if (!checkSizes()) {
                    event.preventDefault();
                    return;
                }
                submit.disabled = true;
                submit.textContent = 'Uploading…';
            });
        })();
    </script>
</x-admin-layout>
