@php
    $status = $submission->status;
    $photoUrl = route('admin.shoe-submissions.files.show', [$submission, 'photo']);
    $modelUrl = route('admin.shoe-submissions.files.show', [$submission, 'model']);
    $missingFileHelp = 'Send the shoe again from the Add new shoe page.';
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
                        <p class="text-sm text-slate-500">Drag to turn, pinch to zoom.</p>
                        <a href="{{ $modelUrl }}?download=1" class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium transition hover:bg-slate-100">Download 3D model</a>
                    </div>
                    @vite('resources/js/model-preview.js')
                @elseif ($submission->glb_path)
                    <p class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">The 3D model file is no longer on the server. {{ $missingFileHelp }}</p>
                @elseif ($status === \App\ShoeSubmissionStatus::Processing)
                    <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Making the 3D model. Reload in 1 to 3 minutes.</p>
                @else
                    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">No 3D model yet. Send the shoe again.</p>
                @endif
            </section>

            @if ($status === \App\ShoeSubmissionStatus::ModelReady)
                <section id="review" class="flex scroll-mt-6 flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-bold">Review the 3D model</h2>
                        <p class="mt-1 text-sm text-slate-500">Step 1 of 2. Try it on your feet, then approve or reject it.</p>
                    </div>

                    @if ($hasModel)
                        @php
                            $feetPreviewUrl = route('admin.shoe-submissions.preview', $submission);
                        @endphp
                        <div class="flex flex-col gap-3 rounded-xl border border-brand-500/30 bg-brand-500/5 p-4">
                            <div>
                                <h3 class="font-semibold">Preview it on your feet</h3>
                                <p class="mt-1 text-sm text-slate-600">Point the back camera at your feet from 1–2 m away. Use the buttons to line it up.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-5">
                                <a href="{{ $feetPreviewUrl }}" class="btn-flame">Preview on my feet</a>
                                <figure class="flex items-center gap-3">
                                    <div data-link-qr="{{ $feetPreviewUrl }}" role="img" aria-label="QR code that opens the feet preview on your phone" class="size-32 shrink-0 rounded-lg border border-slate-300 bg-white p-1"></div>
                                    <figcaption class="max-w-40 text-sm text-slate-600">Scan to open on your phone.</figcaption>
                                </figure>
                            </div>
                            <p class="text-xs text-slate-500">Sign in to the admin on your phone first.</p>
                        </div>
                        @vite('resources/js/link-qr.js')

                        @php
                            $otherSide = $submission->shoe_side?->opposite();
                        @endphp
                        <form method="POST" action="{{ route('admin.shoe-submissions.side.update', $submission) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4">
                            @csrf
                            @method('PUT')
                            <div class="text-sm">
                                <p class="font-semibold">
                                    {{ $submission->shoe_side ? $submission->shoe_side->label() : 'Side guessed from the model’s shape' }}
                                </p>
                                <p class="mt-0.5 text-slate-600">Left foot showing the right shoe? Switch the side and the AR effect is built again, mirrored.</p>
                            </div>
                            @if ($otherSide)
                                <button type="submit" name="shoe_side" value="{{ $otherSide->value }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium transition hover:bg-slate-100">Make it a {{ $otherSide->value }} shoe</button>
                            @else
                                <div class="flex gap-2">
                                    @foreach (\App\ShoeSide::cases() as $side)
                                        <button type="submit" name="shoe_side" value="{{ $side->value }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium transition hover:bg-slate-100">{{ $side->label() }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </form>
                    @else
                        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">The model file is missing, so it can only be rejected.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.shoe-submissions.review.store', $submission) }}">
                        @csrf
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="submit" name="decision" value="reject" class="inline-flex h-11 items-center rounded-lg border border-rose-300 px-5 text-sm font-medium text-rose-700 transition hover:bg-rose-50">Reject model</button>
                            <button type="submit" name="decision" value="approve" @disabled(! $hasModel) class="btn-flame disabled:cursor-not-allowed disabled:opacity-50">Approve and add to library</button>
                        </div>
                    </form>
                </section>
            @elseif ($status === \App\ShoeSubmissionStatus::ModelApproved)
                <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-bold">Add to the shoe library</h2>
                        <p class="mt-1 text-sm text-slate-500">Upload the AR effect file for this shoe.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.shoe-submissions.library.store', $submission) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                        @csrf
                        <div class="flex flex-col gap-1.5">
                            <label for="effect" class="text-sm font-semibold">AR effect file</label>
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
                    <p class="text-sm text-slate-600">This model was rejected. Retake the photos and send it again.</p>
                    <a href="{{ route('admin.shoe-submissions.create') }}" class="btn-flame">Send it again</a>
                </section>
            @elseif ($status === \App\ShoeSubmissionStatus::ReadyForReview && $libraryShoe)
                @php
                    $tryOnUrl = route('tryon', ['preview' => $libraryShoe->slug]);
                    $latestRound = $fitRounds->first();
                @endphp
                <section aria-labelledby="tryon-review-title" class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div>
                        <h2 id="tryon-review-title" class="text-xl font-bold">Live try-on review</h2>
                        <p class="mt-1 text-sm text-slate-500">Step 2 of 2. Try it on live. Publish it, or say what to fix.</p>
                    </div>

                    <div class="flex flex-col gap-3 rounded-xl bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center gap-5">
                            <a href="{{ $tryOnUrl }}" target="_blank" rel="noopener" class="btn-flame">Open live try-on</a>
                            <figure class="flex items-center gap-3">
                                <div data-link-qr="{{ $tryOnUrl }}" role="img" aria-label="QR code that opens the live try-on on your phone" class="size-32 shrink-0 rounded-lg border border-slate-300 bg-white p-1"></div>
                                <figcaption class="max-w-40 text-sm text-slate-600">Scan to open on your phone.</figcaption>
                            </figure>
                        </div>
                        @vite('resources/js/link-qr.js')
                        <p class="text-xs text-slate-500">Shoppers can't see it until you publish it.</p>
                    </div>

                    @if ($isFitRoundProcessing)
                        <div role="status" class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            <span class="size-5 shrink-0 animate-spin rounded-full border-2 border-amber-300 border-t-amber-700"></span>
                            <p>Working on a new fit. This page reloads when it is done.</p>
                        </div>
                        <script>setTimeout(() => location.reload(), 15000);</script>
                    @elseif ($latestRound?->status === \App\ShoeFitRoundStatus::Applied)
                        <p class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">New fit saved {{ $latestRound->updated_at->diffForHumans() }}. Try it on again.</p>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <form method="POST" action="{{ route('admin.shoe-submissions.publish.store', $submission) }}" class="flex flex-col justify-between gap-4 rounded-xl border border-emerald-200 p-4">
                            @csrf
                            <div>
                                <h3 class="font-semibold">It sits right</h3>
                                <p class="mt-1 text-sm text-slate-500">Publishing shows it on the site.</p>
                            </div>
                            <button type="submit" @disabled($isFitRoundProcessing) class="btn-flame self-start disabled:cursor-not-allowed disabled:opacity-50">Approve and publish</button>
                        </form>

                        <form method="POST" action="{{ route('admin.shoes.fit-rounds.store', $libraryShoe) }}" enctype="multipart/form-data" class="flex flex-col gap-3 rounded-xl border border-rose-200 p-4">
                            @csrf
                            <div>
                                <h3 class="font-semibold">It needs fixing</h3>
                                <p class="mt-1 text-sm text-slate-500">We'll adjust the fit for you.</p>
                            </div>

                            @unless ($isReoptimizeConfigured)
                                <p role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Fit fixes are not set up yet.</p>
                            @endunless

                            <div class="flex flex-col gap-1.5">
                                <label for="comment" class="text-sm font-semibold">What looks wrong?</label>
                                <textarea id="comment" name="comment" rows="3" required minlength="5" maxlength="1000" placeholder="e.g. The heel sticks out behind my foot and the shoe floats above the floor." class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">{{ old('comment') }}</textarea>
                                @error('comment')
                                    <p class="text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="screenshot" class="text-sm font-semibold">Try-on screenshot <span class="font-normal text-slate-500">· optional, JPEG, PNG or WebP up to 5 MB</span></label>
                                <input id="screenshot" name="screenshot" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-medium">
                                @error('screenshot')
                                    <p class="text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" @disabled(! $isReoptimizeConfigured || $isFitRoundProcessing) class="inline-flex h-11 items-center self-start rounded-lg border border-rose-300 px-5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50">Fix the fit</button>
                        </form>
                    </div>

                    @if ($fitRounds->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-semibold">Fit rounds</h3>
                            <ul class="mt-2 divide-y divide-slate-200 border-y border-slate-200">
                                @foreach ($fitRounds as $round)
                                    <li class="flex flex-col gap-1.5 py-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-sm text-slate-500">
                                                <time datetime="{{ $round->created_at->toIso8601String() }}" title="{{ $round->created_at->toDayDateTimeString() }}">{{ $round->created_at->diffForHumans() }}</time>
                                                @if ($round->user) · {{ $round->user->name }} @endif
                                            </p>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $round->status->badgeClasses() }}">{{ $round->status->label() }}</span>
                                        </div>
                                        <p class="text-sm">“{{ $round->comment }}”</p>
                                        @if ($round->message)
                                            <p class="text-sm {{ $round->status === \App\ShoeFitRoundStatus::Failed ? 'text-rose-600' : 'text-slate-600' }}">{{ $round->message }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <p class="text-xs text-slate-500">Want to set the fit by hand? <a href="{{ route('admin.shoes.edit', $libraryShoe) }}" class="font-medium text-brand-900 underline">Edit the AR fit</a>.</p>
                </section>
            @elseif ($libraryShoe)
                <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <p class="text-sm text-slate-600">
                        @if ($status === \App\ShoeSubmissionStatus::Published)
                            This shoe passed its try-on review and is {{ $libraryShoe->is_visible ? 'visible on the site' : 'in the library, hidden' }}.
                        @else
                            This shoe is in the library{{ $libraryShoe->is_visible ? '' : ', hidden' }}.
                        @endif
                    </p>
                    <a href="{{ route('admin.shoes.edit', $libraryShoe) }}" class="btn-flame">Edit the fit</a>
                </section>
            @endif
        </div>
    </div>
</x-admin-layout>
