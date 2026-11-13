<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Preview {{ $submission->name }} on your feet – ShopAR Admin</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/feet-preview.js'])
</head>
<body class="h-dvh overflow-hidden bg-slate-950 text-white">
    <main class="relative flex h-dvh flex-col">
        <header class="flex items-center justify-between gap-3 px-4 py-3">
            <a href="{{ $reviewUrl }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium transition hover:bg-white/25">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                Review
            </a>
            <p class="min-w-0 truncate text-sm font-semibold">{{ $submission->name }} <span class="font-normal text-white/60">· on your feet</span></p>
        </header>

        <div id="feet-stage" class="relative min-h-0 flex-1 overflow-hidden bg-slate-900">
            <canvas id="feet-shoes" class="pointer-events-none absolute inset-0 z-1 size-full"></canvas>
            <div id="feet-status" role="status" aria-live="polite" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-gradient-to-t from-brand-500 to-brand-900 px-6 text-center text-sm">
                <div class="size-9 animate-spin rounded-full border-2 border-white/30 border-t-white" data-busy></div>
                <p data-status-text class="max-w-xs">Starting camera…</p>
                <button type="button" data-status-action hidden class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-brand-900 shadow-lg"></button>
            </div>
            <p id="feet-hint" aria-live="polite" class="pointer-events-none absolute inset-x-4 top-3 z-10 mx-auto w-fit max-w-full rounded-full bg-white px-4 py-2 text-center text-xs font-medium text-brand-900 shadow-lg">
                Point the back camera at your feet, 1–2 m away
            </p>
        </div>

        {{-- Tripo's models come in their own size and facing, so the admin lines the shoe up once. The fit is saved and becomes the shoe's starting fit. --}}
        <div class="flex flex-col gap-2 px-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="grid grid-cols-4 gap-2 text-xs font-medium">
                <button type="button" data-adjust="smaller" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Smaller</button>
                <button type="button" data-adjust="bigger" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Bigger</button>
                <button type="button" data-adjust="turn" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Turn 90°</button>
                <button type="button" data-adjust="flip" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Flip</button>
                <button type="button" data-adjust="up" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Up</button>
                <button type="button" data-adjust="down" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Down</button>
                <button type="button" data-adjust="forward" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Forward</button>
                <button type="button" data-adjust="back" class="rounded-lg bg-white/15 py-2.5 transition active:bg-white/30 disabled:opacity-40">Back</button>
            </div>
            <div class="flex items-center justify-between gap-2 text-xs">
                <button type="button" data-adjust="leg" aria-pressed="false" class="rounded-lg bg-white/15 px-3 py-2 font-medium transition active:bg-white/30 disabled:opacity-40 aria-pressed:bg-emerald-500/40">Leg mask: off</button>
                <p id="feet-saved" aria-live="polite" class="text-white/70">{{ $isAwaitingReview ? 'Changes are saved as the shoe’s starting fit.' : 'This shoe was already reviewed; changes are not saved.' }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" data-adjust="reset" class="rounded-lg border border-white/25 px-4 py-2.5 text-xs font-medium transition active:bg-white/15">Reset</button>
                <a href="{{ $reviewUrl }}#review" class="btn-flame flex-1 justify-center">{{ $isAwaitingReview ? 'Done, review it' : 'Back to the shoe' }}</a>
            </div>
        </div>
    </main>

    <script>
        window.FEET_PREVIEW = {{ Js::from($preview) }};
    </script>
</body>
</html>
