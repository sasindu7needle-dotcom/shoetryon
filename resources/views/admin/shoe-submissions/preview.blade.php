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

        {{-- The shoe is fitted automatically: centred on each tracked foot, one foot long and turned along it. --}}
        <div class="flex flex-col gap-2 px-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="flex items-center justify-between gap-2 text-xs">
                <div class="flex shrink-0 gap-2">
                    <button type="button" data-leg-mask aria-pressed="false" class="rounded-lg bg-white/15 px-3 py-2 font-medium transition active:bg-white/30 aria-pressed:bg-emerald-500/40">Feet inside: off</button>
                    <button type="button" data-take-photo disabled class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 font-medium transition active:bg-white/30 disabled:opacity-40">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                        Photo
                    </button>
                </div>
                <p id="feet-saved" aria-live="polite" class="text-right text-white/70">{{ $isAwaitingReview ? 'Fitted automatically to your feet.' : 'Fitted automatically. This shoe was already reviewed; nothing is saved.' }}</p>
            </div>
            <a href="{{ $reviewUrl }}#review" class="btn-flame justify-center">{{ $isAwaitingReview ? 'Done, review it' : 'Back to the shoe' }}</a>
        </div>
    </main>

    {{-- A photo of the try-on: saved to the phone, or sent to Claude, who answers with a better fit. --}}
    <dialog id="feet-photo" class="m-auto w-[min(26rem,calc(100%-2rem))] rounded-2xl bg-slate-900 p-0 text-white shadow-2xl backdrop:bg-black/70">
        <form method="dialog" class="flex max-h-[90dvh] flex-col gap-3 overflow-y-auto p-4" data-photo-form>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">Photo of the try-on</h2>
                <button value="close" class="rounded-lg px-2 py-1 text-sm text-white/70 hover:bg-white/10" aria-label="Close">✕</button>
            </div>
            <img data-photo-image alt="The shoes on your feet, as the camera showed them" class="max-h-[50dvh] w-full rounded-lg bg-black object-contain">
            <a data-photo-save download class="inline-flex h-10 items-center justify-center rounded-lg bg-white/15 text-sm font-medium transition hover:bg-white/25">Save photo</a>
            @if ($preview['claudeUrl'])
                <label class="flex flex-col gap-1 text-xs text-white/70">
                    What looks wrong? (optional)
                    <textarea data-photo-comment rows="2" maxlength="1000" placeholder="e.g. the heel sticks out behind my foot" class="rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/40"></textarea>
                </label>
                <button type="button" data-photo-send class="btn-flame justify-center disabled:opacity-50">Send to Claude to optimize the fit</button>
            @else
                <p class="text-xs text-white/60">{{ $isAwaitingReview ? 'Sending photos to Claude needs N8N_REOPTIMIZE_URL and N8N_SHOPAR_KEY on the server.' : 'This shoe was already reviewed. Send photos to Claude from its try-on review instead.' }}</p>
            @endif
            <p data-photo-error role="alert" class="text-xs text-rose-300" hidden></p>
        </form>
    </dialog>

    <script>
        window.FEET_PREVIEW = {{ Js::from($preview) }};
    </script>
</body>
</html>
