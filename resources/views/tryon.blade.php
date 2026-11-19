<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0a0a">
    <title>Try On – CARNAGE</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/tryon.js'])
</head>
<body class="on-dark min-h-screen bg-ink text-bone">
    <header class="border-b border-white/10">
        <nav class="mx-auto grid h-16 max-w-7xl grid-cols-[1fr_auto_1fr] items-center px-5">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 justify-self-start font-mono text-[11px] tracking-[0.16em] text-bone/70 uppercase transition hover:text-bone">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="square" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                Back
            </a>
            <a href="{{ route('home') }}" class="font-display text-3xl leading-none tracking-wide uppercase">Carnage</a>
            <span class="tag tag-live justify-self-end">
                <span class="size-1.5 animate-pulse rounded-full bg-white"></span>
                Live AR
            </span>
        </nav>
    </header>

    <main class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-4 lg:gap-6 lg:py-8">
        @if (! $licenseKey)
            <p class="border border-blood/50 bg-blood/10 p-4 text-sm text-bone">
                The try-on is not available right now. Please try again later.
            </p>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_380px] lg:gap-6">
            <section class="ar-frame relative overflow-hidden border border-white/10 bg-coal">
                {{-- On phones the camera view gives up some height so the shoe picker stays on screen. --}}
                <div id="ar-stage" class="relative mx-auto aspect-[3/4] max-h-[calc(100svh-20.5rem)] min-h-56 w-full overflow-hidden lg:aspect-auto lg:h-[680px] lg:max-h-none">
                    {{-- DeepAR's canvas cancels touch drags, which stops the page scrolling on phones. Nothing in the view is touch-interactive, so catch touches above it. --}}
                    {{-- Shoes made from photos have no DeepAR effect; their GLB is drawn here, on the feet DeepAR tracks. --}}
                    <canvas id="ar-shoes" hidden class="pointer-events-none absolute inset-0 z-1 size-full"></canvas>
                    <div class="absolute inset-0 z-1"></div>
                    <div id="ar-status" role="status" aria-live="polite" class="grain absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 overflow-hidden bg-ink px-6 text-center text-sm text-bone">
                        <div class="hairline-grid pointer-events-none absolute inset-0"></div>
                        <div class="scan" data-busy></div>
                        <div class="relative size-10 animate-spin rounded-full border-2 border-white/15 border-t-blood" data-busy></div>
                        <p data-status-text class="relative max-w-xs font-mono text-xs tracking-[0.12em] uppercase">Starting camera…</p>
                        <button type="button" data-status-action hidden class="btn-blood relative"></button>
                    </div>
                </div>
                <div class="pointer-events-none absolute inset-x-0 bottom-5 z-10 flex justify-center px-4">
                    <p id="ar-hint" aria-live="polite" class="max-w-full bg-bone px-4 py-2 text-center font-mono text-[11px] font-medium tracking-wider text-ink uppercase shadow-[4px_4px_0_var(--color-blood)]">
                        Stand 1–2 m back so both feet are in frame
                    </p>
                </div>
            </section>

            <aside class="flex flex-col gap-3 border border-white/10 bg-coal p-4 lg:gap-5 lg:p-6">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h1 class="text-2xl lg:text-5xl">Pick a pair</h1>
                    </div>
                    <span class="font-mono text-[11px] tracking-widest text-bone/40">{{ str_pad((string) count($shoes), 2, '0', STR_PAD_LEFT) }} PAIRS</span>
                </div>
                <p class="-mt-2 hidden text-sm text-bone/55 lg:block">Tap a shoe to see it on your feet.</p>

                {{-- A swipeable row on phones, a grid next to the camera on large screens. The padding leaves room
                     for the selected card's outline; scroll-px keeps snapping from scrolling it out of view. --}}
                {{-- Large collections scroll inside the picker on large screens instead of stretching it past the camera view. --}}
                <ul id="shoe-list" class="no-scrollbar lg:dark-scroll -m-1 flex snap-x scroll-px-1 gap-3 overflow-x-auto p-1 lg:grid lg:max-h-[31rem] lg:grid-cols-2 lg:content-start lg:overflow-x-visible lg:overflow-y-auto">
                    @foreach ($shoes as $shoe)
                        {{-- 28% leaves part of the next card showing, so the row reads as swipeable. --}}
                        <li class="w-[28%] shrink-0 snap-start lg:w-auto">
                            <button type="button"
                                    data-id="{{ $shoe['id'] }}"
                                    data-name="{{ $shoe['name'] }}"
                                    data-effect="{{ $shoe['ready'] ? $shoe['effect'] : '' }}"
                                    data-glb="{{ $shoe['ready'] && ! $shoe['effect'] ? $shoe['glb'] : '' }}"
                                    data-leg-mask="{{ $shoe['legMask'] ? '1' : '0' }}"
                                    @disabled(! $shoe['ready'])
                                    class="pair-card shoe-btn w-full touch-manipulation p-1.5 text-left disabled:cursor-not-allowed disabled:opacity-40">
                                <span class="relative block">
                                    <img src="{{ $shoe['thumb'] }}" alt="{{ $shoe['name'] }}" class="aspect-square w-full bg-bone object-cover">
                                    <span class="pair-check absolute top-1.5 right-1.5 grid size-5 place-items-center bg-blood text-white" aria-hidden="true">
                                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="square"><path d="M5 12l5 5 9-10"/></svg>
                                    </span>
                                </span>
                                <span class="mt-2 block truncate px-1 text-[11px] leading-tight font-bold tracking-wide uppercase lg:text-xs">{{ $shoe['name'] }}</span>
                                <span class="block px-1 pt-0.5 pb-1 font-mono text-[9px] tracking-widest uppercase lg:text-[10px] {{ $shoe['ready'] ? 'text-blood' : 'text-bone/35' }}">
                                    {{ $shoe['ready'] ? '● AR ready' : 'Coming soon' }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-auto flex items-center gap-2 border-t border-white/10 pt-3 text-xs text-bone/45">
                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="1"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    Your camera feed stays on your device. Nothing is uploaded.
                </p>
            </aside>
        </div>
    </main>

    <script>
        window.TRYON = {{ Js::from(['licenseKey' => $licenseKey, 'rootPath' => $rootPath, 'trackerEffect' => $trackerEffect, 'fits' => $fits]) }};
    </script>
</body>
</html>
