<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ShopAR – Try shoes on in AR</title>
    <meta name="description" content="Spin every shoe in 3D, then try it on your own feet with your phone camera.">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/landing.js'])
</head>
<body class="overflow-x-hidden">
    <header id="site-nav" class="site-nav fixed inset-x-0 top-0 z-40">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5">
            <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight">ShopAR</a>
            <div class="hidden items-center gap-10 text-[15px] font-medium sm:flex">
                <a href="#collection" class="opacity-90 transition hover:opacity-100">Collection</a>
                <a href="#how" class="opacity-90 transition hover:opacity-100">How it works</a>
            </div>
            <a href="{{ route('tryon') }}" data-tryon-link class="nav-cta rounded-lg px-5 py-2.5 text-[15px] font-medium transition hover:brightness-95">Try on live</a>
        </nav>
    </header>

    <section class="hero-bg relative min-h-dvh overflow-hidden text-white" aria-label="Hero">
        <div class="mx-auto max-w-5xl px-5 pt-28 text-center sm:pt-32">
            <h1 class="rise text-4xl font-bold leading-tight sm:text-5xl md:text-6xl lg:text-7xl lg:leading-[1.2]">
                See it on your feet.<br>Before you buy.
            </h1>
            <p class="rise mx-auto mt-5 max-w-2xl text-base text-white/85 sm:text-lg" style="animation-delay:.15s">
                Spin every pair in 3D, then point your phone at your feet and see the real fit in augmented reality. No app to install.
            </p>
        </div>

        <div class="relative mx-auto mt-4 h-[52vh] min-h-[340px] max-w-6xl px-5 sm:h-[58vh]">
            <canvas id="hero-canvas" class="h-full w-full cursor-grab touch-pan-y active:cursor-grabbing"></canvas>

            <div id="hero-loading" class="absolute inset-0 grid place-items-center transition-opacity duration-700">
                <div class="size-10 animate-spin rounded-full border-2 border-white/30 border-t-white"></div>
            </div>

            {{-- A swipeable row above the model on phones, so it never covers the shoe; a column beside it
                 on larger screens. Either scrolls when the collection has more shoes than fit. --}}
            <ul class="no-scrollbar absolute top-0 left-1/2 flex max-w-[calc(100%-2.5rem)] -translate-x-1/2 flex-row gap-2 overflow-x-auto rounded-full bg-white/20 p-2 backdrop-blur-md sm:top-1/2 sm:right-8 sm:left-auto sm:max-h-full sm:max-w-none sm:translate-x-0 sm:-translate-y-1/2 sm:flex-col sm:gap-3 sm:overflow-x-visible sm:overflow-y-auto sm:rounded-[3rem] sm:p-2.5">
                @foreach ($shoes as $i => $shoe)
                    <li class="shrink-0">
                        <button type="button" data-shoe="{{ $shoe['id'] }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" aria-label="View {{ $shoe['name'] }}"
                                class="shoe-dot block size-12 shrink-0 sm:size-20">
                            <img src="{{ $shoe['thumb'] }}" alt="" class="size-full object-cover" loading="lazy">
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="absolute bottom-2 left-1/2 flex -translate-x-1/2 flex-col items-center gap-3">
                <p id="shoe-name" class="text-sm font-medium text-white/90">{{ $shoes[0]['name'] ?? '' }}</p>
                <a href="{{ route('tryon') }}" data-tryon-link class="btn-white">Try On</a>
            </div>
        </div>
        <div class="h-16"></div>
    </section>

    <section id="collection" class="relative overflow-hidden border-y border-slate-200 bg-slate-50 py-20 sm:py-28">
        <div class="flame-dots pointer-events-none absolute inset-0"></div>
        <div class="relative mx-auto max-w-6xl px-5">
            <h2 class="text-center text-4xl font-bold tracking-tight sm:text-5xl">The collection</h2>
            <p class="mx-auto mt-4 max-w-xl text-center text-lg text-slate-600">Every pair is a full 3D model, ready to view from any angle or wear in AR.</p>
            <div class="mt-12 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                @foreach ($shoes as $shoe)
                    <a href="{{ route('tryon', ['shoe' => $shoe['id']]) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-xl">
                        <div class="aspect-square overflow-hidden bg-slate-100">
                            <img src="{{ $shoe['thumb'] }}" alt="{{ $shoe['name'] }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                        </div>
                        {{-- Name and badge stack in the narrow two-column grid on phones. --}}
                        <div class="flex flex-col items-start gap-1.5 p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-2 sm:p-4">
                            <span class="text-sm leading-snug font-semibold sm:text-base">{{ $shoe['name'] }}</span>
                            @if ($shoe['ready'])
                                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-emerald-700">AR ready</span>
                            @else
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-slate-500">3D only</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="how" class="mx-auto max-w-6xl px-5 py-20 sm:py-28">
        <h2 class="text-center text-4xl font-bold tracking-tight sm:text-5xl">Three steps to the right fit</h2>
        <div class="mt-12 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['01', 'Open the camera', 'Tracking runs on your device in the browser. Nothing is uploaded.'],
                ['02', 'Show your feet', 'Stand one to two metres back so both feet are in frame.'],
                ['03', 'Pick a pair', 'Tap any shoe and it appears on your feet, following every step.'],
            ] as [$n, $title, $text])
                <article class="rounded-2xl border border-slate-200 bg-white p-7 transition hover:-translate-y-1 hover:shadow-lg">
                    <span class="text-4xl font-extrabold text-flame">{{ $n }}</span>
                    <h3 class="mt-4 text-xl font-bold">{{ $title }}</h3>
                    <p class="mt-2 text-slate-600">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="bg-slate-900 px-5 py-20 text-center text-slate-50 sm:py-28">
        <h2 class="mx-auto max-w-3xl text-4xl font-bold leading-tight sm:text-5xl lg:text-[56px]">Your next pair is one tap away.</h2>
        <p class="mx-auto mt-5 max-w-xl text-lg text-slate-300">Open the try-on on your phone for the best experience with the back camera.</p>
        <a href="{{ route('tryon') }}" data-tryon-link class="btn-flame mt-9 min-w-56">Start trying on</a>
    </section>

    <footer class="bg-slate-900 py-8 text-center text-sm text-slate-400">
        ShopAR · AR shoe try-on
    </footer>

    <script>
        window.SHOES = @json($shoesJs);
    </script>
</body>
</html>
