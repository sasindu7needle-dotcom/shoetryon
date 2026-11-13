@props(['title'])

@php
    $navigation = [
        ['Dashboard', route('admin.dashboard'), request()->routeIs('admin.dashboard'), 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ['Shoe library', route('admin.shoes.index'), request()->routeIs('admin.shoes.*'), 'M3 16c0-1 .6-2 2-2.4l4-1.1 2.5-4.5h3l1 3.5 4.3 1.5c1.3.5 2.2 1.7 2.2 3.1V18H3zM3 18h18'],
        ['Add new shoe', route('admin.shoe-submissions.create'), request()->routeIs('admin.shoe-submissions.*'), 'M12 5v14M5 12h14'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} – ShopAR Admin</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen lg:flex">
    <aside class="hero-bg text-white lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-64 lg:shrink-0 lg:flex-col">
        <div class="flex h-16 items-center justify-between gap-4 px-5 lg:h-20">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                ShopAR
                <span class="rounded-md bg-white/15 px-2 py-0.5 text-xs font-semibold tracking-wide uppercase">Admin</span>
            </a>
            <div class="flex items-center gap-2 lg:hidden">
                <a href="{{ route('home') }}" target="_blank" aria-label="View site" title="View site" class="grid size-10 place-items-center rounded-lg bg-white/15 transition hover:bg-white/25 sm:hidden">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/></svg>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="h-10 rounded-lg bg-white px-4 text-sm font-medium text-slate-900 transition hover:brightness-95">Log out</button>
                </form>
            </div>
        </div>

        {{-- Equal tabs on phones (all three always visible), a row on tablets, a column on large screens. --}}
        <nav aria-label="Admin" class="grid grid-cols-3 gap-1 px-3 pb-3 sm:flex sm:overflow-x-auto lg:flex-1 lg:flex-col lg:overflow-visible lg:pt-2">
            @foreach ($navigation as [$label, $url, $isActive, $iconPath])
                <a href="{{ $url }}" @if ($isActive) aria-current="page" @endif
                   class="flex min-w-0 shrink-0 flex-col items-center justify-center gap-1 rounded-lg px-1 py-2 text-center text-xs leading-tight font-medium transition sm:flex-row sm:justify-start sm:gap-3 sm:px-3 sm:py-2.5 sm:text-left sm:text-[15px] {{ $isActive ? 'bg-white text-brand-900 shadow-lg' : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $iconPath }}"/></svg>
                    {{ $label }}
                </a>
            @endforeach

            <a href="{{ route('home') }}" target="_blank" class="hidden shrink-0 items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium text-white/85 transition hover:bg-white/15 hover:text-white sm:flex lg:mt-auto">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/></svg>
                View site
            </a>
        </nav>

        <div class="hidden border-t border-white/15 p-4 lg:block">
            <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-white/70">{{ auth()->user()->username ?? auth()->user()->email }}</p>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-900 transition hover:brightness-95">Log out</button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-6 sm:py-8">
            @isset($header)
                <div class="hero-bg mb-6 overflow-hidden rounded-2xl px-5 py-6 text-white sm:mb-8 sm:px-8 sm:py-8">
                    {{ $header }}
                </div>
            @endisset

            @if (session('error'))
                <p role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                    {{ session('error') }}
                </p>
            @endif

            @if (session('status'))
                <p role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('status') }}
                </p>
            @endif

            {{ $slot }}
        </main>

        <footer class="py-6 text-center text-sm text-slate-400">
            ShopAR · Admin
        </footer>
    </div>
</body>
</html>
