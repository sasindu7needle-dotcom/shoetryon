@php
    $input = 'h-12 w-full border border-white/15 bg-white/[0.04] px-4 text-bone outline-none transition placeholder:text-bone/30 focus:border-blood focus:bg-white/[0.07]';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0a0a0a">
    <title>Sign in – CARNAGE Studio</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="on-dark min-h-dvh bg-ink text-bone lg:grid lg:grid-cols-[1.1fr_1fr]">
    {{-- Brand panel --}}
    <section class="relative hidden overflow-hidden bg-blood lg:flex lg:flex-col lg:justify-between lg:p-12" aria-hidden="true">
        <p class="font-display text-outline pointer-events-none absolute -right-8 -bottom-16 text-[20rem] leading-none text-white/20 uppercase select-none">C</p>
        <span class="font-display relative text-4xl tracking-wide text-white uppercase">Carnage</span>
        <div class="relative text-white">
            <p class="font-display text-8xl leading-[0.9] uppercase xl:text-9xl">Run<br>the<br>drop.</p>
            <p class="mt-6 max-w-sm text-white/80">Manage the shoes, the fits and the live AR try-on behind the virtual fitting room.</p>
        </div>
    </section>

    <div class="grain flex min-h-dvh flex-col">
        <header class="flex h-16 items-center justify-between px-5 sm:px-10">
            <a href="{{ route('home') }}" class="font-display text-3xl leading-none tracking-wide uppercase lg:invisible">Carnage</a>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 font-mono text-[11px] tracking-[0.16em] text-bone/60 uppercase transition hover:text-bone">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="square"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                Back to site
            </a>
        </header>

        <main class="flex flex-1 items-center justify-center px-5 py-10 sm:px-10">
            <div class="rise w-full max-w-md">
                <h1 class="text-6xl sm:text-7xl">Admin sign in</h1>
                <p class="mt-3 text-bone/55">Manage the shoe library behind the try-on.</p>

                <form method="POST" action="{{ route('admin.login.store') }}" class="mt-10 flex flex-col gap-6">
                    @csrf

                    <div class="flex flex-col gap-2">
                        <label for="login" class="eyebrow text-bone/70">Username or email</label>
                        <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" autocapitalize="none" class="{{ $input }}">
                        @error('login')
                            <p class="text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="eyebrow text-bone/70">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="{{ $input }}">
                        @error('password')
                            <p class="text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2.5 text-sm text-bone/60">
                        <input type="checkbox" name="remember" value="1" class="size-4 accent-blood">
                        Keep me signed in
                    </label>

                    <button type="submit" class="btn-blood mt-2 w-full">
                        Sign in
                        <svg class="btn-arrow size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
