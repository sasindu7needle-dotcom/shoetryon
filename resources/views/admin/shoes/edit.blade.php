@php
    $fit = $shoe->fit ?? [];
    $vectors = [
        'rotation' => ['Rotation', 'Euler degrees', $fit['rotation'] ?? [0, 0, 0]],
        'position' => ['Position', 'DeepAR foot units', $fit['position'] ?? [0, 0, 0]],
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
                @foreach (['Slug' => $shoe->slug, '3D model' => $shoe->glb, 'AR effect' => $shoe->effect] as $label => $value)
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
                <div>
                    <h2 class="text-xl font-bold">AR fit</h2>
                    <p class="mt-1 text-sm text-slate-500">How the shoe sits on the tracked foot. Small changes go a long way; test on a phone after saving.</p>
                </div>

                <div class="flex flex-col gap-1.5 sm:max-w-48">
                    <label for="fit-scale" class="text-sm font-semibold">Scale</label>
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
</x-admin-layout>
