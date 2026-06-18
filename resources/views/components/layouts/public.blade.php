@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-accent="gold" data-density="comfortable" data-theme="light" data-grain="on">
<head>
    @include('partials.head')
    @livewireStyles
    @include('v2.partials.theme', ['forceLight' => true])
    <style>
        @media (max-width: 820px) {
            .pub section { padding-left: 22px !important; padding-right: 22px !important; }
            .pub .grid { grid-template-columns: 1fr !important; gap: 28px !important; }
            .pub section .flex { flex-wrap: wrap; }
            .pub h1 { font-size: 42px !important; line-height: 1.1 !important; }
            .pub h2 { font-size: 30px !important; }
            .pub-header { padding-left: 20px !important; padding-right: 20px !important; }
            .pub-decor { display: none !important; }
            .pub-cta { padding: 36px 26px !important; }
        }
        @media (max-width: 480px) {
            .pub h1 { font-size: 32px !important; }
            .pub section { padding-left: 16px !important; padding-right: 16px !important; }
            .pub-wordmark { display: none !important; }
        }
    </style>
</head>
<body class="paper-grain pub" style="background: var(--ivory-deep); min-height: 100vh;">
    {{-- Sticky blurred nav --}}
    <header class="sticky top-0 z-10 flex items-center pub-header"
            style="padding: 16px 60px; gap: 32px; background: rgba(var(--bg-rgb),0.85); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-soft);"
            x-data="{ open: false }">
        <a href="{{ route('home') }}" class="flex items-center gap-3" wire:navigate>
            <x-crest size="30"/>
            <div class="serif pub-wordmark" style="font-size: 19px; font-weight: 600;">Generative Topical</div>
        </a>
        <nav class="hidden md:flex" style="gap: 28px; margin-left: 24px; font-size: 13px;">
            <a href="{{ route('subjects.show', 'a-level-physics') }}" wire:navigate style="color: var(--text-soft);">Subjects</a>
            <a href="#" style="color: var(--text-soft);">For Schools</a>
            <a href="#" style="color: var(--text-soft);">For Teachers</a>
            <a href="{{ route('pricing') }}" wire:navigate style="color: var(--text-soft);">Pricing</a>
            <a href="{{ route('about') }}" wire:navigate style="color: var(--text-soft);">About</a>
        </nav>
        <div class="flex-1"></div>
        @auth
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-primary btn-sm">Dashboard</a>
        @else
            <a href="{{ route('login') }}" wire:navigate class="btn btn-ghost btn-sm">Sign in</a>
            <a href="{{ route('register') }}" wire:navigate class="btn btn-primary btn-sm">Get started</a>
        @endauth
        <button @click="open = !open" class="md:hidden" style="background: transparent; border: 0; padding: 6px;"><x-icon name="menu" size="20"/></button>
        <div x-show="open" x-cloak @click.away="open = false" class="md:hidden absolute top-full left-0 right-0 bg-white border-b" style="border-color: var(--border);">
            <div class="grid gap-3 p-6 text-sm">
                <a href="{{ route('subjects.show', 'a-level-physics') }}" wire:navigate>Subjects</a>
                <a href="{{ route('pricing') }}" wire:navigate>Pricing</a>
                <a href="{{ route('about') }}" wire:navigate>About</a>
                <a href="{{ route('contact') }}" wire:navigate>Contact</a>
            </div>
        </div>
    </header>

    <main>{{ $slot }}</main>

    {{-- Footer --}}
    <footer class="flex justify-between" style="padding: 40px 60px; border-top: 1px solid var(--border-soft); margin-top: 40px; font-size: 12px; color: var(--text-faint);">
        <div>© {{ date('Y') }} Generative Topical · Lahore · Karachi · Islamabad</div>
        <div class="flex" style="gap: 18px;">
            <a href="#" style="color: inherit;">Privacy</a>
            <a href="#" style="color: inherit;">Terms</a>
            <a href="{{ route('contact') }}" wire:navigate style="color: inherit;">Contact</a>
        </div>
    </footer>

    @livewireScripts
    @fluxScripts
</body>
</html>
