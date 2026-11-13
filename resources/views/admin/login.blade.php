<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Admin sign in – ShopAR</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="hero-bg flex min-h-dvh flex-col">
    <header class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-5 text-white">
        <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight">ShopAR</a>
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 text-sm font-medium transition hover:bg-white/25">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            Back to site
        </a>
    </header>

    <main class="flex flex-1 items-center justify-center px-5 py-10">
        <div class="rise w-full max-w-md">
            <div class="text-center text-white">
                <h1 class="text-4xl font-bold sm:text-5xl">Admin sign in</h1>
                <p class="mt-3 text-white/85">Manage the shoe library behind the try-on.</p>
            </div>

            <form method="POST" action="{{ route('admin.login.store') }}" class="mt-8 flex flex-col gap-5 rounded-2xl bg-white p-6 text-slate-900 shadow-2xl sm:p-8">
                @csrf

                <div class="flex flex-col gap-1.5">
                    <label for="login" class="text-sm font-semibold">Username or email</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" autocapitalize="none"
                           class="h-11 rounded-lg border border-slate-300 px-3 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    @error('login')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-sm font-semibold">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="h-11 rounded-lg border border-slate-300 px-3 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    @error('password')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded accent-brand-900">
                    Keep me signed in
                </label>

                <button type="submit" class="btn-flame w-full">Sign in</button>
            </form>
        </div>
    </main>
</body>
</html>
