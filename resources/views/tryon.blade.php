<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Try On – ShopAR</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/tryon.js'])
</head>
<body class="min-h-screen">
    <header class="hero-bg text-white">
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 text-sm font-medium transition hover:bg-white/25">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                Back
            </a>
            <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight">ShopAR</a>
            <span class="w-[76px]"></span>
        </nav>
    </header>

    <main class="mx-auto flex max-w-6xl flex-col gap-4 px-5 py-4 lg:gap-6 lg:py-8">
        @if (! $licenseKey)
            <p class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                Set <code>DEEPAR_LICENSE_KEY</code> in your environment (Web app key from developer.deepar.ai), then reload.
            </p>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px] lg:gap-6">
            <section class="ar-frame relative overflow-hidden rounded-2xl bg-slate-900 shadow-xl">
                {{-- On phones the camera view gives up some height so the shoe picker stays on screen. --}}
                <div id="ar-stage" class="relative mx-auto aspect-[3/4] max-h-[calc(100svh-20.5rem)] min-h-56 w-full overflow-hidden lg:aspect-auto lg:h-[640px] lg:max-h-none">
                    {{-- DeepAR's canvas cancels touch drags, which stops the page scrolling on phones. Nothing in the view is touch-interactive, so catch touches above it. --}}
                    <div class="absolute inset-0 z-1"></div>
                    <div id="ar-status" role="status" aria-live="polite" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-gradient-to-t from-brand-500 to-brand-900 px-6 text-center text-sm text-white">
                        <div class="scan" data-busy></div>
                        <div class="size-9 animate-spin rounded-full border-2 border-white/30 border-t-white" data-busy></div>
                        <p data-status-text class="max-w-xs">Starting camera…</p>
                        <button type="button" data-status-action hidden class="relative rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-brand-900 shadow-lg"></button>
                    </div>
                </div>
                <div class="pointer-events-none absolute inset-x-0 bottom-4 z-10 flex justify-center px-4">
                    <p id="ar-hint" aria-live="polite" class="max-w-full rounded-full bg-white px-4 py-2 text-center text-xs font-medium text-brand-900 shadow-lg">
                        Stand 1–2 m back so both feet are in frame
                    </p>
                </div>
            </section>

            <aside class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:gap-4 lg:p-5">
                <div>
                    <h1 class="text-lg font-bold lg:text-2xl">Pick a pair</h1>
                    <p class="mt-1 hidden text-sm text-slate-600 lg:block">Tap a shoe to see it on your feet.</p>
                </div>

                {{-- A swipeable row on phones, a grid next to the camera on large screens. The padding leaves room
                     for the selected card's outline; scroll-px keeps snapping from scrolling it out of view. --}}
                <ul id="shoe-list" class="-m-1 flex snap-x scroll-px-1 gap-3 overflow-x-auto p-1 lg:grid lg:grid-cols-2 lg:overflow-visible">
                    @foreach ($shoes as $shoe)
                        {{-- 28% leaves part of the next card showing, so the row reads as swipeable. --}}
                        <li class="w-[28%] shrink-0 snap-start lg:w-auto">
                            <button type="button"
                                    data-id="{{ $shoe['id'] }}"
                                    data-name="{{ $shoe['name'] }}"
                                    data-effect="{{ $shoe['ready'] ? $shoe['effect'] : '' }}"
                                    @disabled(! $shoe['ready'])
                                    class="shoe-card shoe-btn w-full touch-manipulation rounded-xl p-2 text-left disabled:cursor-not-allowed disabled:opacity-50">
                                <img src="{{ $shoe['thumb'] }}" alt="{{ $shoe['name'] }}" class="aspect-square w-full rounded-lg bg-slate-100 object-cover">
                                <span class="mt-2 block px-1 text-xs leading-tight font-semibold lg:text-sm">{{ $shoe['name'] }}</span>
                                <span class="block px-1 pb-1 text-[11px] {{ $shoe['ready'] ? 'text-emerald-600' : 'text-slate-400' }}">
                                    {{ $shoe['ready'] ? 'AR ready' : 'Coming soon' }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-auto text-xs text-slate-500">Your camera feed stays on your device. Nothing is uploaded.</p>
            </aside>
        </div>
    </main>

    <script>
        window.TRYON = @json(['licenseKey' => $licenseKey, 'rootPath' => $rootPath, 'fits' => $fits]);
    </script>
</body>
</html>
