@php
    $shoeCount = count($shoes);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0a0a">
    <title>CARNAGE – Virtual Fitting Room</title>
    <meta name="description" content="Spin every CARNAGE pair in 3D, then wear it on your own feet with your phone camera. No app to install.">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/landing.js'])
</head>
<body class="on-dark overflow-x-hidden bg-ink text-bone">
    <header id="site-nav" class="site-nav fixed inset-x-0 top-0 z-40">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-5">
            <a href="{{ route('home') }}" class="font-display text-3xl leading-none tracking-wide uppercase">Carnage</a>
            <div class="hidden items-center gap-10 sm:flex">
                <a href="#drop" class="nav-link">The drop</a>
                <a href="#how" class="nav-link">How it works</a>
            </div>
            <a href="{{ route('tryon') }}" data-tryon-link class="group inline-flex h-10 items-center gap-2 border border-bone/30 px-4 text-xs font-bold tracking-[0.14em] uppercase transition hover:border-bone hover:bg-bone hover:text-ink">
                <span class="live-dot"></span>
                Try on live
            </a>
        </nav>
    </header>

    {{-- ================= HERO ================= --}}
    <section class="grain relative flex min-h-dvh flex-col overflow-hidden pt-20 lg:h-dvh lg:min-h-[600px]" aria-label="Hero">
        <div class="hairline-grid pointer-events-none absolute inset-0"></div>

        {{-- Oversized wordmark behind the model --}}
        <p aria-hidden="true" class="font-display text-outline pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-[27vw] leading-none text-white/[0.06] uppercase select-none">
            Carnage
        </p>

        {{-- Phones stack headline, model, then the selected pair. Large screens put the model on the right,
             level with the headline, so it is in view without scrolling. --}}
        <div class="relative mx-auto grid w-full max-w-7xl flex-1 grid-cols-1 items-center gap-x-10 px-5 pt-6 lg:min-h-0 lg:grid-cols-[1fr_1.1fr] lg:grid-rows-[1fr_auto_0.35fr] lg:pt-2">
            <div class="lg:self-end">
                <h1 class="text-[13vw] sm:text-7xl lg:text-[clamp(3rem,10.5vh,6rem)]">
                    <span class="reveal-line"><span>Wear it</span></span>
                    <span class="reveal-line"><span style="animation-delay:.08s">before you</span></span>
                    <span class="reveal-line"><span class="text-blood" style="animation-delay:.16s">own it.</span></span>
                </h1>
                <div class="rise mt-6 max-w-md lg:mt-[2.5vh]" style="animation-delay:.35s">
                    <p class="text-base leading-relaxed text-bone/70 lg:text-[15px]">
                        Spin every pair in 3D. Then point your phone at your feet and see the real fit in augmented reality. No app, no queue, no guessing.
                    </p>
                </div>
            </div>

            <div class="relative mt-4 h-[44vh] min-h-[320px] lg:col-start-2 lg:row-span-3 lg:row-start-1 lg:mt-0 lg:h-full lg:min-h-0">
                <canvas id="hero-canvas" class="h-full w-full cursor-grab touch-pan-y active:cursor-grabbing"></canvas>

                <div id="hero-loading" class="absolute inset-0 grid place-items-center transition-opacity duration-700">
                    <div class="flex flex-col items-center gap-3">
                        <div class="size-10 animate-spin rounded-full border-2 border-white/15 border-t-blood"></div>
                        <span class="eyebrow text-bone/50">Loading the drop</span>
                    </div>
                </div>

                <p class="eyebrow pointer-events-none absolute right-2 bottom-2 hidden items-center gap-2 text-bone/40 sm:flex">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 0 1 15.5-6.2M21 12a9 9 0 0 1-15.5 6.2M18 2v4h-4M6 22v-4h4"/></svg>
                    Drag to spin
                </p>
            </div>

            {{-- Selected pair --}}
            <div class="mt-4 flex flex-col items-stretch gap-4 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between lg:mt-[3vh] lg:flex-col lg:items-start lg:gap-[2vh] lg:self-start lg:pt-[2.5vh]">
                <div class="flex min-w-0 items-baseline gap-4">
                    <span class="shrink-0 font-mono text-xs text-bone/50"><span id="shoe-index" class="text-blood">01</span> / {{ str_pad((string) $shoeCount, 2, '0', STR_PAD_LEFT) }}</span>
                    <p id="shoe-name" class="font-display truncate text-3xl uppercase sm:text-4xl">{{ $shoes[0]['name'] ?? '' }}</p>
                </div>
                <a href="{{ route('tryon') }}" data-tryon-link class="btn-blood">
                    Try it on
                    <svg class="btn-arrow size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>

        {{-- Every pair in a row along the bottom; it scrolls sideways when the collection is too long to fit. --}}
        <div class="relative mx-auto w-full max-w-7xl shrink-0 px-5 pt-6 pb-8 lg:pt-3 lg:pb-5">
            <ul class="no-scrollbar mx-auto flex w-fit max-w-full gap-3 overflow-x-auto p-1.5">
                @foreach ($shoes as $i => $shoe)
                    <li class="shrink-0">
                        <button type="button" data-shoe="{{ $shoe['id'] }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" aria-label="View {{ $shoe['name'] }}"
                                class="shoe-dot block size-14 shrink-0 sm:size-16">
                            <img src="{{ $shoe['thumb'] }}" alt="" class="size-full object-cover" loading="lazy">
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ================= THE DROP ================= --}}
    <section id="drop" class="scroll-mt-16 bg-bone py-20 text-ink sm:py-28">
        <div class="mx-auto max-w-7xl px-5">
            <div class="flex flex-col gap-5 border-b-2 border-ink pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-6xl sm:text-8xl">The drop</h2>
                </div>
                <p class="max-w-sm text-ink/65">Every pair is a full 3D model. View it from any angle, or tap through and wear it in AR.</p>
            </div>

            <div class="mt-10 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4">
                @foreach ($shoes as $shoe)
                    <a href="{{ route('tryon', ['shoe' => $shoe['id']]) }}" class="drop-card group block">
                        <div class="relative aspect-[4/5] overflow-hidden bg-white">
                            <img src="{{ $shoe['thumb'] }}" alt="{{ $shoe['name'] }}" class="drop-img size-full object-cover" loading="lazy">
                            <span class="absolute top-2.5 left-2.5 font-mono text-[10px] font-bold tracking-widest text-ink/60">NO.{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="absolute top-2 right-2">
                                @if ($shoe['ready'])
                                    <span class="tag tag-live">AR ready</span>
                                @else
                                    <span class="tag bg-ink/80 text-bone">3D only</span>
                                @endif
                            </span>
                            <span class="drop-cta absolute inset-x-0 bottom-0 flex items-center justify-between bg-ink px-3 py-2.5 text-[11px] font-bold tracking-[0.14em] text-bone uppercase">
                                {{ $shoe['ready'] ? 'Try it on' : 'View in 3D' }}
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </span>
                        </div>
                        <p class="font-display mt-3 text-xl leading-tight uppercase sm:text-2xl">{{ $shoe['name'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= HOW ================= --}}
    <section id="how" class="grain scroll-mt-16 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5">
            <h2 class="max-w-3xl text-6xl sm:text-8xl">Three steps. <span class="text-outline">Zero guesswork.</span></h2>

            <div class="mt-14 grid border-t border-white/15 md:grid-cols-3">
                @foreach ([
                    ['01', 'Open the camera', 'Tracking runs on your device, in the browser. Nothing is uploaded.'],
                    ['02', 'Show your feet', 'Stand one to two metres back so both feet are in frame.'],
                    ['03', 'Pick a pair', 'Tap any shoe and it lands on your feet, following every step.'],
                ] as [$number, $title, $text])
                    <article class="group border-b border-white/15 py-8 md:border-b-0 md:px-8 md:first:pl-0 md:[&:not(:first-child)]:border-l">
                        <span class="font-display text-outline block text-8xl leading-none text-bone/40 transition group-hover:text-blood">{{ $number }}</span>
                        <h3 class="mt-6 text-xl font-bold tracking-wide uppercase">{{ $title }}</h3>
                        <p class="mt-2 max-w-xs text-bone/60">{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= CTA ================= --}}
    <section class="relative overflow-hidden bg-blood py-20 text-white sm:py-28">
        <p aria-hidden="true" class="font-display text-outline pointer-events-none absolute -bottom-10 left-0 text-[22vw] leading-none whitespace-nowrap text-white/15 uppercase select-none">No fitting room</p>
        <div class="relative mx-auto flex max-w-7xl flex-col items-start gap-8 px-5 lg:flex-row lg:items-end lg:justify-between">
            <h2 class="max-w-4xl text-6xl sm:text-8xl">Your next pair is one tap away.</h2>
            <div class="flex flex-col items-start gap-4">
                <p class="max-w-xs text-white/85">Open the try-on on your phone for the best fit with the back camera.</p>
                <a href="{{ route('tryon') }}" data-tryon-link class="btn-ink">
                    Start trying on
                    <svg class="btn-arrow size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- ================= FOOTER ================= --}}
    <footer class="grain overflow-hidden pt-16">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="max-w-xs text-sm text-bone/55">Your camera feed stays on your device. Nothing is recorded or uploaded.</p>
            </div>
            <div class="flex gap-8">
                <a href="#drop" class="nav-link">The drop</a>
                <a href="#how" class="nav-link">How it works</a>
                <a href="{{ route('tryon') }}" class="nav-link">Try on</a>
            </div>
        </div>
        <p aria-hidden="true" class="font-display mt-10 text-center text-[24vw] leading-[0.78] tracking-tight text-white/[0.06] uppercase select-none">Carnage</p>
        <div class="border-t border-white/10">
            <p class="mx-auto max-w-7xl px-5 py-5 font-mono text-[11px] tracking-widest text-bone/40 uppercase">© {{ now()->year }} Carnage · AR try-on</p>
        </div>
    </footer>

    <script>
        window.SHOES = @json($shoesJs);
    </script>
</body>
</html>
