@php
    $usesEffect = $shoe->usesEffect();
    $fit = $shoe->fit ?? ($usesEffect ? [] : \App\Models\Shoe::FEET_DEFAULT_FIT);
    $vectors = [
        'rotation' => ['Rotation', 'Euler degrees', $fit['rotation'] ?? [0, 0, 0]],
        'position' => ['Position', $usesEffect ? 'DeepAR foot units' : 'Foot units (a foot is about 24): sideways, up, towards the toes', $fit['position'] ?? [0, 0, 0]],
        'stretch' => ['Stretch', 'Width, height, length multipliers', $fit['stretch'] ?? [1, 1, 1]],
    ];
    $input = 'h-11 w-full rounded-lg border border-slate-300 px-3 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30';
@endphp

<x-admin-layout :title="'Edit '.$shoe->name">
    <x-slot:header>
        <a href="{{ $libraryUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 text-sm font-medium transition hover:bg-white/25">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            Shoe library
        </a>
        <h1 class="rise mt-5 text-3xl font-bold sm:text-4xl">Edit {{ $shoe->name }}</h1>
    </x-slot:header>

    <form method="POST" action="{{ route('admin.shoes.update', $shoe) }}" class="grid gap-6 lg:grid-cols-[300px_1fr]">
        @csrf
        @method('PUT')
        <input type="hidden" name="return_url" value="{{ old('return_url', $libraryUrl) }}">

        {{-- A compact strip on phones and tablets so the form starts on the first screen; a card beside it on large screens. --}}
        <aside class="flex items-start gap-4 self-start rounded-2xl border border-slate-200 bg-white p-4 lg:flex-col">
            <img src="{{ asset($shoe->thumb) }}" alt="{{ $shoe->name }}" class="aspect-square w-24 shrink-0 rounded-xl bg-slate-100 object-cover sm:w-32 lg:w-full">
            <dl class="flex min-w-0 flex-col gap-2 text-sm">
                @foreach (['Slug' => $shoe->slug, '3D model' => $shoe->glb, 'AR effect' => $usesEffect ? $shoe->effect : 'None, worn as its 3D model on the tracked feet'] as $label => $value)
                    <div>
                        <dt class="font-semibold">{{ $label }}</dt>
                        <dd class="break-all text-slate-500">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </aside>

        <div class="flex flex-col gap-6">
            <section class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-xl font-bold">Details</h2>

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-sm font-semibold">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $shoe->name) }}" required maxlength="80" class="{{ $input }}">
                    @error('name')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1.5 sm:max-w-48">
                    <label for="position" class="text-sm font-semibold">Position in library</label>
                    <input id="position" name="position" type="number" min="1" max="{{ $shoeCount }}" step="1" value="{{ old('position', $shoe->position) }}" required class="{{ $input }}">
                    <p class="text-xs text-slate-500">1 is first. There are {{ $shoeCount }} {{ Str::plural('shoe', $shoeCount) }}.</p>
                    @error('position')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_visible" value="0">
                    <input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $shoe->is_visible)) class="size-5 rounded accent-brand-900">
                    <span>
                        <span class="block text-sm font-semibold">Visible on the site</span>
                        <span class="block text-sm text-slate-500">Untick to hide it from the site but keep it in the library.</span>
                    </span>
                </label>
            </section>

            <section class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold">AR fit</h2>
                        <p class="mt-1 text-sm text-slate-500">How the shoe sits on the tracked foot. Small changes go a long way; test on a phone after saving.</p>
                    </div>
                    <a href="{{ route('tryon', ['preview' => $shoe->slug]) }}" target="_blank" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-medium transition hover:bg-slate-100">Test on phone</a>
                </div>

                @if ($usesEffect)
                    <label class="flex items-center gap-3">
                        <input type="hidden" name="use_studio_fit" value="0">
                        <input type="checkbox" name="use_studio_fit" value="1" @checked(old('use_studio_fit', $shoe->fit === null)) class="size-5 rounded accent-brand-900">
                        <span>
                            <span class="block text-sm font-semibold">Use the placement exported from DeepAR Studio</span>
                            <span class="block text-sm text-slate-500">Ignores the values below. Untick to place the shoe with them.</span>
                        </span>
                    </label>
                @else
                    <input type="hidden" name="use_studio_fit" value="0">
                    <label class="flex items-center gap-3">
                        <input type="hidden" name="leg_mask" value="0">
                        <input type="checkbox" name="leg_mask" value="1" @checked(old('leg_mask', $shoe->leg_mask)) class="size-5 rounded accent-brand-900">
                        <span>
                            <span class="block text-sm font-semibold">Leg mask</span>
                            <span class="block text-sm text-slate-500">Hides the back of the shoe's collar behind the wearer's leg. Turn it off if it cuts into the shoe.</span>
                        </span>
                    </label>
                @endif

                <div class="flex flex-col gap-1.5 sm:max-w-48">
                    <label for="fit-scale" class="text-sm font-semibold">Scale @unless ($usesEffect)<span class="font-normal text-slate-500">· 1 = one foot long</span>@endunless</label>
                    <input id="fit-scale" name="fit[scale]" type="number" step="any" value="{{ old('fit.scale', $fit['scale'] ?? 1) }}" required class="{{ $input }}">
                    @error('fit.scale')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                @foreach ($vectors as $key => [$label, $hint, $values])
                    <fieldset class="flex flex-col gap-1.5">
                        <legend class="text-sm font-semibold">{{ $label }} <span class="font-normal text-slate-500">· {{ $hint }}</span></legend>
                        <div class="mt-1.5 grid grid-cols-3 gap-3">
                            @foreach (['X', 'Y', 'Z'] as $axis => $axisLabel)
                                <label class="flex items-center overflow-hidden rounded-lg border border-slate-300 transition focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30">
                                    <span class="grid h-11 w-9 shrink-0 place-items-center bg-slate-50 text-xs font-bold text-slate-500">{{ $axisLabel }}</span>
                                    <input name="fit[{{ $key }}][{{ $axis }}]" type="number" step="any" value="{{ old("fit.{$key}.{$axis}", $values[$axis] ?? 0) }}" required
                                           aria-label="{{ $label }} {{ $axisLabel }}" class="h-11 w-full min-w-0 px-2 outline-none">
                                </label>
                            @endforeach
                        </div>
                        @if ($errors->has("fit.{$key}") || $errors->has("fit.{$key}.*"))
                            <p class="text-sm text-rose-600">{{ $errors->first("fit.{$key}") ?: $errors->first("fit.{$key}.*") }}</p>
                        @endif
                    </fieldset>
                @endforeach
            </section>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ $libraryUrl }}" class="inline-flex h-11 items-center rounded-lg border border-slate-300 px-5 font-medium transition hover:bg-slate-100">Cancel</a>
                <button type="submit" class="btn-flame">Save changes</button>
            </div>
        </div>
    </form>

    <section aria-labelledby="reoptimize-title" class="mt-8 grid gap-6 lg:grid-cols-[300px_1fr]">
        <div class="hidden lg:block"></div>

        <div class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div>
                <h2 id="reoptimize-title" class="text-xl font-bold">Re-optimize with Claude</h2>
                <p class="mt-1 text-sm text-slate-500">Try the shoe on with your phone, then say what looks wrong, for example "the heel sticks out behind my foot" or "the shoe floats above the floor". Claude changes the AR fit and saves it here; no new AR files are built.</p>
            </div>

            @unless ($isReoptimizeConfigured)
                <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">The n8n re-optimize webhook is not connected yet.</p>
                    <p class="mt-1">Set <code class="rounded bg-amber-100 px-1">N8N_REOPTIMIZE_URL</code> and <code class="rounded bg-amber-100 px-1">N8N_SHOPAR_KEY</code> in the environment, then reload this page.</p>
                </div>
            @endunless

            <form method="POST" action="{{ route('admin.shoes.fit-rounds.store', $shoe) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf

                <div class="flex flex-col gap-1.5">
                    <label for="comment" class="text-sm font-semibold">What looks wrong on the phone?</label>
                    <textarea id="comment" name="comment" rows="3" required minlength="5" maxlength="1000" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="screenshot" class="text-sm font-semibold">Phone screenshot <span class="font-normal text-slate-500">· optional, JPEG, PNG or WebP up to 5 MB</span></label>
                    <input id="screenshot" name="screenshot" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-medium">
                    @error('screenshot')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" @disabled(! $isReoptimizeConfigured) class="btn-flame disabled:cursor-not-allowed disabled:opacity-50">Re-optimize</button>
                </div>
            </form>

            @if ($fitRounds->isNotEmpty())
                <ul class="divide-y divide-slate-200 border-t border-slate-200">
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
            @endif
        </div>
    </section>
</x-admin-layout>
