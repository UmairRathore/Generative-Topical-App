@php
    $user = auth('v2_super_admin')->user();
    $currentRouteName = request()->route()?->getName() ?? '';

    // Open quality reviews awaiting a Support decision — shown as a nav badge.
    $openFlagCount = \App\Models\V2\QualityReview::where('status', 'open')->count();

    $nav = [
        ['section' => 'Platform'],
        ['id' => 'v2.super_admin.dashboard', 'icon' => 'home',   'label' => 'Dashboard', 'href' => route('v2.super_admin.dashboard')],
        ['id' => 'v2.super_admin.schools',   'icon' => 'school', 'label' => 'Schools',   'href' => route('v2.super_admin.schools.index')],
        ['section' => 'Analytics'],
        ['id' => 'v2.super_admin.stats',     'icon' => 'chart',  'label' => 'Platform Analytics', 'href' => route('v2.super_admin.stats')],
        ['id' => 'v2.super_admin.grades',    'icon' => 'layers', 'label' => 'Grades',    'href' => route('v2.super_admin.grades.index')],
        ['id' => 'v2.super_admin.subjects',  'icon' => 'grid',   'label' => 'Subjects',  'href' => route('v2.super_admin.subjects.index')],
        ['id' => 'v2.super_admin.topics',    'icon' => 'target', 'label' => 'Topics',    'href' => route('v2.super_admin.topics.index')],
        ['section' => 'Content'],
        ['id' => 'v2.super_admin.question_bank',  'icon' => 'book', 'label' => 'Question Bank', 'href' => route('v2.super_admin.question_bank.index')],
        ['id' => 'v2.super_admin.question_flags', 'icon' => 'flag', 'label' => 'Quality Reviews', 'href' => route('v2.super_admin.question_flags.index'), 'badge' => $openFlagCount],
        ['section' => 'System'],
        ['id' => 'v2.super_admin.audit.index',  'icon' => 'eye',    'label' => 'Audit Log',    'href' => route('v2.super_admin.audit.index')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-accent="gold" data-density="comfortable" data-theme="light" data-grain="on">
<head>
    @include('partials.head')
    @livewireStyles
    @include('v2.partials.responsive')
    @include('v2.partials.theme')
</head>
<body class="antialiased" x-data="{ sidebarOpen: false }">
<div class="flex min-h-screen" style="background: var(--bg);">

    {{-- Sidebar --}}
    <aside class="flex flex-col"
           style="width: 244px; flex: none; background: linear-gradient(180deg, var(--sidebar-deep) 0%, var(--sidebar) 100%); color: var(--ivory); border-right: 1px solid rgba(var(--accent-rgb),0.18); position: sticky; top: 0; height: 100vh;"
           :class="sidebarOpen ? 'fixed inset-y-0 left-0 z-40' : '-translate-x-full lg:translate-x-0 lg:static'">

        <div style="padding: 20px 18px 16px; border-bottom: 1px solid rgba(var(--accent-rgb),0.15);">
            <a href="{{ route('v2.super_admin.dashboard') }}">
                <x-crest size="32" variant="mono-light" :showWordmark="true" />
            </a>
        </div>

        <div style="padding: 12px 16px 4px;">
            <div class="inline-flex items-center gap-1.5"
                 style="padding: 4px 10px; border-radius: 999px; background: rgba(var(--accent-rgb),0.12); border: 1px solid rgba(var(--accent-rgb),0.3); font-size: 10.5px; font-weight: 600; color: var(--accent); letter-spacing: 0.1em; text-transform: uppercase;">
                <x-icon name="shield" size="11" stroke="2" />
                Super Admin
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto" style="padding: 8px 0 16px;">
            @foreach($nav as $item)
                @if(isset($item['section']))
                    <div style="padding: 8px 14px; font-size: 11px; font-weight: 600; color: rgba(var(--accent-rgb),0.65); text-transform: uppercase; letter-spacing: 0.14em; margin-top: 16px;">
                        {{ $item['section'] }}
                    </div>
                @else
                    @php $active = str_starts_with($currentRouteName, $item['id']); @endphp
                    <a href="{{ $item['href'] }}"
                       class="flex items-center gap-3"
                       style="width: calc(100% - 16px); padding: 9px 14px; margin: 1px 8px; border-radius: 6px; font-size: 13.5px; text-decoration: none; transition: all .15s;
                              {{ $active ? 'background: rgba(var(--accent-rgb),0.13); color: var(--accent); font-weight: 600; border-left: 2px solid var(--accent);' : 'background: transparent; color: rgba(250,247,239,0.78); font-weight: 500; border-left: 2px solid transparent;' }}">
                        <x-icon :name="$item['icon']" size="17" />
                        <span style="flex:1;">{{ $item['label'] }}</span>
                        @if (!empty($item['badge']))
                            <span style="flex:none;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--accent);color:var(--emerald-900,#062a1f);font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>

        <div style="padding: 14px; border-top: 1px solid rgba(var(--accent-rgb),0.15);">
            <div class="flex items-center gap-2.5" style="padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.04);">
                <div class="flex items-center justify-center" style="width: 34px; height: 34px; border-radius: 8px; background: var(--accent); color: var(--emerald-900); font-weight: 700; font-size: 12px; flex: none;">
                    {{ strtoupper(substr($user?->name ?? 'SA', 0, 2)) }}
                </div>
                <div style="min-width: 0; flex: 1;">
                    <div class="truncate" style="font-size: 13px; font-weight: 600;">{{ $user?->name ?? 'Super Admin' }}</div>
                    <div class="truncate" style="font-size: 11px; opacity: 0.65;">GT Platform · Super Admin</div>
                </div>
                <form method="POST" action="{{ route('v2.super_admin.logout') }}">
                    @csrf
                    <button type="submit" title="Sign out" style="background: transparent; border: 0; color: rgba(250,247,239,0.6); padding: 4px;">
                        <x-icon name="logout" size="14"/>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak class="fixed inset-0 z-30 lg:hidden" style="background: rgba(11,61,46,0.4);"></div>

    <main class="flex-1 flex flex-col" style="min-width: 0;">
        <header class="flex items-center gap-4 sticky top-0 z-10"
                style="height: 64px; padding: 0 28px; background: var(--surface); border-bottom: 1px solid var(--border);">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden" style="background: transparent; border: 0; padding: 6px;">
                <x-icon name="menu" size="20"/>
            </button>
            @hasSection('page_title')
                <h1 class="serif" style="font-size: 20px; font-weight: 600; color: var(--text);">@yield('page_title')</h1>
            @endif
            <div class="flex-1"></div>
            <x-theme-toggle />
        </header>

        @if(session('success'))
            <div style="margin: 16px 28px 0; padding: 12px 16px; background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 8px; font-size: 13px; color: #065f46;">
                {{ session('success') }}
            </div>
        @endif

        <div class="fade-in" style="padding: 28px; flex: 1;">
            @yield('content')
        </div>
    </main>
</div>
@livewireScripts
@fluxScripts
</body>
</html>
