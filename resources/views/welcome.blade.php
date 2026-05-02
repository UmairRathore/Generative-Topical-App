<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-neutral-50 dark:bg-neutral-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'CambPast') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-neutral-900 dark:text-neutral-100">
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-8 p-6 text-center">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">CambPast</h1>
        <p class="max-w-xl text-lg text-neutral-600 dark:text-neutral-300">
            Cambridge-style MCQ practice. Phase 1 demo: A-Level Physics 9702 Paper 1 objective questions.
        </p>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('subjects.show', 'a-level-physics') }}"
               class="rounded-md bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                Browse A-Level Physics
            </a>
            <a href="{{ route('practice.random', 'a-level-physics') }}"
               class="rounded-md border border-neutral-300 px-5 py-2.5 text-sm font-semibold hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-800">
                Random practice
            </a>
        </div>

        @auth
            <div class="text-sm text-neutral-500">
                Signed in as {{ auth()->user()->name }} &middot;
                <a href="{{ route('dashboard') }}" class="underline">Dashboard</a>
                @if (auth()->user()->isAdmin())
                    &middot; <a href="{{ route('admin.imports') }}" class="underline">Admin imports</a>
                @endif
            </div>
        @else
            <div class="text-sm text-neutral-500">
                <a href="{{ route('login') }}" class="underline">Log in</a>
                &middot;
                <a href="{{ route('register') }}" class="underline">Register</a>
            </div>
        @endauth
    </main>
</body>
</html>
