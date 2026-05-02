<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    @livewireStyles
</head>
<body class="min-h-screen bg-brand-ivory text-brand-charcoal antialiased">
    <header class="bg-brand-emerald text-white">
        <div class="border-b border-brand-gold/30">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-baseline gap-2">
                    <span class="font-display text-xl font-semibold tracking-tight text-white">Generative Topical</span>
                    <span class="hidden text-xs uppercase tracking-[0.2em] text-brand-gold-soft sm:inline">Cambridge · Practice</span>
                </a>
                <nav class="flex items-center gap-6 text-sm">
                    <a href="{{ route('subjects.show', 'a-level-physics') }}" class="hidden text-white/90 transition hover:text-brand-gold-soft sm:inline">A-Level Physics</a>
                    <a href="{{ route('practice.random', 'a-level-physics') }}" class="hidden text-white/90 transition hover:text-brand-gold-soft sm:inline">Practice</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-white/90 transition hover:text-brand-gold-soft">Dashboard</a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-white/70 transition hover:text-brand-gold-soft">Log out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md border border-brand-gold/60 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-brand-gold-soft transition hover:bg-brand-gold hover:text-brand-emerald">
                            Log in
                        </a>
                    @endauth
                </nav>
            </div>
        </div>
        <div class="h-1 bg-gradient-to-r from-brand-gold via-brand-gold-soft to-brand-gold"></div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-brand-border bg-white/60">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-6 text-xs text-brand-slate">
            <div>
                <span class="font-display text-sm text-brand-emerald">Generative Topical</span>
                <span class="ml-2">AI-Powered Assessment Platform</span>
            </div>
            <div class="text-[11px] uppercase tracking-[0.2em]">Cambridge · O Level · A Level · IGCSE</div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
