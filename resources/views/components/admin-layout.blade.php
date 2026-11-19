@props(['title'])

@php
    $navigation = [
        ['Dashboard', route('admin.dashboard'), request()->routeIs('admin.dashboard'), 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ['Shoe library', route('admin.shoes.index'), request()->routeIs('admin.shoes.*'), 'M3 16c0-1 .6-2 2-2.4l4-1.1 2.5-4.5h3l1 3.5 4.3 1.5c1.3.5 2.2 1.7 2.2 3.1V18H3zM3 18h18'],
        ['Add new shoe', route('admin.shoe-submissions.create'), request()->routeIs('admin.shoe-submissions.*'), 'M12 5v14M5 12h14'],
    ];
    $userName = auth()->user()->name;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0a0a0a">
    <title>{{ $title }} – CARNAGE Studio</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 lg:flex">
    <aside class="on-dark grain bg-ink text-bone lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-68 lg:shrink-0 lg:flex-col lg:border-r lg:border-white/10">
        <div class="flex h-16 items-center justify-between gap-4 px-5 lg:h-24">
            <a href="{{ route('admin.dashboard') }}" class="flex items-baseline gap-2.5">
                <span class="font-display text-3xl leading-none tracking-wide uppercase lg:text-4xl">Carnage</span>
                <span class="tag tag-live">Studio</span>
            </a>
            <div class="flex items-center gap-2 lg:hidden">
                <a href="{{ route('home') }}" target="_blank" aria-label="View site" title="View site" class="grid size-10 place-items-center border border-white/15 transition hover:bg-white/10 sm:hidden">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/></svg>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="h-10 bg-bone px-4 text-xs font-bold tracking-[0.12em] text-ink uppercase transition hover:bg-white">Log out</button>
                </form>
            </div>
        </div>


        {{-- Equal tabs on phones (all three always visible), a row on tablets, a column on large screens. --}}
        <nav aria-label="Admin" class="grid grid-cols-3 gap-1 px-3 pb-3 sm:flex sm:overflow-x-auto lg:flex-1 lg:flex-col lg:overflow-visible">
            @foreach ($navigation as [$label, $url, $isActive, $iconPath])
                <a href="{{ $url }}" @if ($isActive) aria-current="page" @endif
                   class="relative flex min-w-0 shrink-0 flex-col items-center justify-center gap-1 px-1 py-2 text-center text-xs leading-tight font-semibold tracking-wide uppercase transition sm:flex-row sm:justify-start sm:gap-3 sm:px-3 sm:py-3 sm:text-left sm:text-[13px] {{ $isActive ? 'bg-white/[0.08] text-bone' : 'text-bone/55 hover:bg-white/5 hover:text-bone' }}">
                    @if ($isActive)
                        <span class="absolute inset-x-2 bottom-0 h-0.5 bg-blood sm:inset-x-0 lg:inset-y-0 lg:right-auto lg:left-0 lg:h-auto lg:w-[3px]"></span>
                    @endif
                    <svg class="size-5 shrink-0 {{ $isActive ? 'text-blood' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $iconPath }}"/></svg>
                    {{ $label }}
                </a>
            @endforeach

            <a href="{{ route('home') }}" target="_blank" class="hidden shrink-0 items-center gap-3 px-3 py-3 text-[13px] font-semibold tracking-wide text-bone/55 uppercase transition hover:bg-white/5 hover:text-bone sm:flex lg:mt-auto">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/></svg>
                View site
            </a>
        </nav>

        <div class="hidden border-t border-white/10 p-5 lg:block">
            <div class="flex items-center gap-3">
                <span class="font-display grid size-10 shrink-0 place-items-center bg-blood text-lg text-white uppercase">{{ Str::substr($userName, 0, 1) }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $userName }}</p>
                    <p class="truncate font-mono text-[11px] text-bone/45">{{ auth()->user()->username ?? auth()->user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-ghost w-full !min-h-10 !text-xs">Log out</button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-6 sm:py-8">
            @isset($header)
                <div class="hero-bg on-dark mb-6 overflow-hidden px-5 py-7 sm:mb-8 sm:px-8 sm:py-9">
                    {{ $header }}
                </div>
            @endisset

            @if (session('error'))
                <p role="alert" class="mb-6 border-l-4 border-blood bg-white px-4 py-3 text-sm font-medium text-rose-800 shadow-sm">
                    {{ session('error') }}
                </p>
            @endif

            @if (session('status'))
                <p role="status" class="mb-6 border-l-4 border-emerald-600 bg-white px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
                    {{ session('status') }}
                </p>
            @endif

            {{ $slot }}
        </main>

        <footer class="px-5 py-6 text-center font-mono text-[11px] tracking-widest text-slate-400 uppercase">
            Carnage · Studio
        </footer>
    </div>
</body>
</html>
