<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-neutral-50 dark:bg-neutral-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'CambPast') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen text-neutral-900 dark:text-neutral-100">
    <header class="border-b border-neutral-200 dark:border-neutral-800">
        <div class="mx-auto flex max-w-5xl items-center justify-between p-4">
            <a href="{{ route('home') }}" class="text-lg font-bold">CambPast</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('subjects.show', 'a-level-physics') }}" class="hover:underline">A-Level Physics</a>
                <a href="{{ route('practice.random', 'a-level-physics') }}" class="hover:underline">Random practice</a>
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.imports') }}" class="hover:underline">Admin</a>
                    @endif
                    <span class="text-neutral-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="hover:underline">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">Log in</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
