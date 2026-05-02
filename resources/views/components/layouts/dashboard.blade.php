@php
    $user = auth()->user();
    $nav = [
        ['label' => 'Dashboard',          'route' => 'dashboard',       'pattern' => 'dashboard',          'show' => true],
        ['label' => 'Imports',            'route' => 'admin.imports',   'pattern' => 'admin.imports',      'show' => $user?->isAdmin()],
        ['label' => 'Question Browser',   'route' => 'admin.questions', 'pattern' => 'admin.questions',    'show' => $user?->isAdmin()],
        ['label' => 'Papers',             'route' => 'admin.papers',    'pattern' => 'admin.papers*',      'show' => $user?->isAdmin()],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    @livewireStyles
</head>
<body class="min-h-screen bg-brand-ivory text-brand-charcoal antialiased" x-data="{ sidebarOpen: false }">

    {{-- Mobile top bar --}}
    <div class="flex items-center justify-between bg-brand-emerald px-4 py-3 text-white lg:hidden">
        <a href="{{ route('dashboard') }}" class="font-display text-lg font-semibold">Generative Topical</a>
        <button type="button" @click="sidebarOpen = !sidebarOpen" class="rounded-md border border-brand-gold/40 p-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-brand-emerald text-white shadow-xl transition-transform duration-200 lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="border-b border-brand-gold/20 px-6 py-6">
                <div class="font-display text-xl font-semibold tracking-tight">Generative Topical</div>
                <div class="mt-1 text-[11px] uppercase tracking-[0.2em] text-brand-gold-soft">Admin Console</div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-6 text-sm">
                @foreach ($nav as $item)
                    @continue(! $item['show'])
                    @php
                        $active = request()->routeIs($item['pattern']);
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="relative flex items-center rounded-md px-4 py-2.5 transition
                              {{ $active ? 'bg-brand-forest text-white' : 'text-white/75 hover:bg-brand-forest/60 hover:text-white' }}">
                        @if ($active)
                            <span class="absolute inset-y-1 left-0 w-1 rounded-r bg-brand-gold"></span>
                        @endif
                        <span class="ml-1">{{ $item['label'] }}</span>
                    </a>
                @endforeach

                <div class="mt-6 border-t border-brand-gold/20 pt-4 text-[11px] uppercase tracking-[0.2em] text-brand-gold-soft/70">External</div>
                <a href="{{ route('subjects.show', 'a-level-physics') }}" target="_blank"
                   class="flex items-center justify-between rounded-md px-4 py-2.5 text-white/75 transition hover:bg-brand-forest/60 hover:text-white">
                    <span>Public site</span>
                    <span class="text-brand-gold-soft">↗</span>
                </a>
                <a href="{{ route('settings.profile') }}"
                   class="flex items-center rounded-md px-4 py-2.5 text-white/75 transition hover:bg-brand-forest/60 hover:text-white">
                    Settings
                </a>
            </nav>

            <div class="border-t border-brand-gold/20 px-6 py-4 text-xs">
                @auth
                    <div class="font-medium text-white">{{ $user->name }}</div>
                    <div class="text-brand-gold-soft/80">{{ $user->isAdmin() ? 'Administrator' : 'Student' }}</div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-white/70 transition hover:text-brand-gold-soft">Log out</button>
                    </form>
                @endauth
            </div>
        </aside>

        {{-- Backdrop on mobile --}}
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-brand-charcoal/40 lg:hidden" x-cloak></div>

        {{-- Main content --}}
        <div class="flex-1 lg:pl-0">
            <main class="min-h-screen">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
