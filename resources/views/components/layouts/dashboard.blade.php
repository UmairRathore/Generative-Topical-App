@props([
    'role' => null,                  // 'admin' | 'teacher' | 'student' (overrides auth user role for previews)
    'breadcrumb' => [],              // ['Dashboard'] or ['Admin','Question Bank','Review Queue']
    'pageTitle' => null,
])
@php
    $user = auth()->user();
    $isSuper = (bool) $user?->isSuperAdmin();
    $role = $role ?? ($user?->isAdmin() ? 'admin' : ($user?->isTeacher() ? 'teacher' : 'student'));

    $navByRole = [
        'admin' => [
            ['section' => 'Overview'],
            ['id' => 'admin.dashboard',         'icon' => 'home',     'label' => 'Dashboard',         'href' => route('admin.dashboard')         ?? '#'],
            ['id' => 'admin.analytics',         'icon' => 'chart',    'label' => 'Analytics',         'href' => route('admin.analytics')         ?? '#'],
            ['section' => 'Question Bank'],
            ['id' => 'admin.questions',         'icon' => 'layers',   'label' => 'All Questions',     'href' => route('admin.questions')          ?? '#', 'badge' => '4,287'],
            ['id' => 'admin.questions.review',  'icon' => 'eye',      'label' => 'Question Review',   'href' => '#'],
            ['id' => 'admin.papers',            'icon' => 'book',     'label' => 'Past Papers',       'href' => route('admin.papers')             ?? '#'],
            ['id' => 'admin.topics',            'icon' => 'list',     'label' => 'Topic Management',  'href' => route('admin.topics')             ?? '#'],
            ['section' => 'Platform'],
            ['id' => 'admin.users',             'icon' => 'users',    'label' => 'User Management',   'href' => route('admin.users')              ?? '#'],
            ['id' => 'settings.profile',        'icon' => 'settings', 'label' => 'Settings',          'href' => route('settings.profile')         ?? '#'],
        ],
        'teacher' => [
            ['section' => 'Teach'],
            ['id' => 'teacher.dashboard',       'icon' => 'home',     'label' => 'Dashboard',         'href' => route('teacher.dashboard')        ?? '#'],
            ['id' => 'teacher.test-generator',  'icon' => 'sparkle',  'label' => 'Generate Paper',    'href' => route('teacher.test-generator')   ?? '#'],
            ['id' => 'teacher.question-picker', 'icon' => 'filter',   'label' => 'Build Test',        'href' => route('teacher.question-picker')  ?? '#'],
            ['id' => 'teacher.bank',            'icon' => 'layers',   'label' => 'Question Bank',     'href' => route('teacher.bank')             ?? '#'],
            ['section' => 'Manage'],
            ['id' => 'teacher.submissions',     'icon' => 'clipboard','label' => 'Submissions',       'href' => route('teacher.submissions')      ?? '#', 'badge' => '12'],
            ['id' => 'teacher.classes',         'icon' => 'users',    'label' => 'Classes',           'href' => route('teacher.classes')          ?? '#'],
            ['id' => 'settings.profile',        'icon' => 'settings', 'label' => 'Settings',          'href' => route('settings.profile')         ?? '#'],
        ],
        'student' => [
            ['section' => 'Learn'],
            ['id' => 'student.dashboard',       'icon' => 'home',     'label' => 'Dashboard',         'href' => route('student.dashboard')        ?? '#'],
            ['id' => 'student.tests',           'icon' => 'clipboard','label' => 'My Tests',          'href' => route('student.tests')            ?? '#', 'badge' => '3'],
            ['id' => 'student.practice',        'icon' => 'target',   'label' => 'Topic Practice',    'href' => route('student.practice')         ?? '#'],
            ['section' => 'Progress'],
            ['id' => 'student.analytics',       'icon' => 'chart',    'label' => 'Performance',       'href' => route('student.analytics')        ?? '#'],
            ['id' => 'settings.profile',        'icon' => 'settings', 'label' => 'Account',           'href' => route('settings.profile')         ?? '#'],
        ],
    ];

    $roleBadge = [
        'admin'   => ['label' => $isSuper ? 'Super Admin' : 'School Admin'],
        'teacher' => ['label' => 'Teacher'],
        'student' => ['label' => 'Student'],
    ][$role];

    $userName = $user?->name ?? ['admin' => 'Aisha Rehman', 'teacher' => 'Dr. Saima Iqbal', 'student' => 'Ayesha Khan'][$role];
    $userInitials = $user?->initials() ?? ['admin' => 'AR', 'teacher' => 'SI', 'student' => 'AK'][$role];
    $userSub = [
        'admin'   => $isSuper ? 'GT Platform · Super Admin' : 'School · Admin',
        'teacher' => 'Physics · ISL Lahore',
        'student' => 'Y12 · A Level Physics',
    ][$role];

    $currentRouteName = request()->route()?->getName() ?? '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-accent="gold" data-density="comfortable" data-theme="light" data-grain="on">
<head>
    @include('partials.head')
    @livewireStyles
</head>
<body class="antialiased" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen" style="background: var(--bg);">
        {{-- Sidebar --}}
        <aside class="flex flex-col"
               style="width: 244px; flex: none; background: linear-gradient(180deg, var(--emerald-900) 0%, var(--emerald-800) 100%); color: var(--ivory); border-right: 1px solid rgba(212,164,55,0.18); position: sticky; top: 0; height: 100vh;"
               :class="sidebarOpen ? 'fixed inset-y-0 left-0 z-40 translate-x-0' : '-translate-x-full lg:translate-x-0 lg:static'"
               style="transition: transform .2s">
            {{-- Logo --}}
            <div style="padding: 20px 18px 16px; border-bottom: 1px solid rgba(212,164,55,0.15);">
                <a href="{{ route('home') }}" wire:navigate>
                    <x-crest size="32" variant="mono-light" :showWordmark="true" />
                </a>
            </div>

            {{-- Role pill --}}
            <div style="padding: 12px 16px 4px;">
                <div class="inline-flex items-center gap-1.5"
                     style="padding: 4px 10px; border-radius: 999px; background: rgba(212,164,55,0.12); border: 1px solid rgba(212,164,55,0.3); font-size: 10.5px; font-weight: 600; color: var(--accent); letter-spacing: 0.1em; text-transform: uppercase;">
                    <x-icon name="shield" size="11" stroke="2" />
                    {{ $roleBadge['label'] }}
                </div>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 overflow-y-auto" style="padding: 8px 0 16px;">
                @foreach($navByRole[$role] as $item)
                    @if(isset($item['section']))
                        <div style="padding: 8px 14px; font-size: 11px; font-weight: 600; color: rgba(212,164,55,0.65); text-transform: uppercase; letter-spacing: 0.14em; margin-top: 16px;">
                            {{ $item['section'] }}
                        </div>
                    @else
                        @php
                            $active = $currentRouteName === $item['id'] || str_starts_with($currentRouteName, $item['id'].'.');
                        @endphp
                        <a href="{{ $item['href'] }}" wire:navigate
                           class="flex items-center gap-3"
                           style="width: calc(100% - 16px); padding: 9px 14px; margin: 1px 8px; border-radius: 6px; font-size: 13.5px; text-decoration: none; transition: all .15s;
                                  {{ $active
                                      ? 'background: rgba(212,164,55,0.13); color: var(--accent); font-weight: 600; border-left: 2px solid var(--accent);'
                                      : 'background: transparent; color: rgba(250,247,239,0.78); font-weight: 500; border-left: 2px solid transparent;' }}">
                            <x-icon :name="$item['icon']" size="17" />
                            <span style="flex:1">{{ $item['label'] }}</span>
                            @if(isset($item['badge']))
                                <span style="background: var(--accent); color: var(--emerald-900); font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 999px;">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>

            {{-- User card --}}
            <div style="padding: 14px; border-top: 1px solid rgba(212,164,55,0.15);">
                <div class="flex items-center gap-2.5" style="padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.04);">
                    <div class="flex items-center justify-center" style="width: 34px; height: 34px; border-radius: 8px; background: var(--accent); color: var(--emerald-900); font-weight: 700; font-size: 12px; flex: none;">
                        {{ $userInitials }}
                    </div>
                    <div style="min-width: 0; flex: 1;">
                        <div class="truncate" style="font-size: 13px; font-weight: 600;">{{ $userName }}</div>
                        <div class="truncate" style="font-size: 11px; opacity: 0.65;">{{ $userSub }}</div>
                    </div>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button type="submit" title="Sign out" style="background: transparent; border: 0; color: rgba(250,247,239,0.6); padding: 4px;">
                                <x-icon name="logout" size="14"/>
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </aside>

        {{-- Mobile backdrop --}}
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak class="fixed inset-0 z-30 lg:hidden" style="background: rgba(11,61,46,0.4);"></div>

        {{-- Main --}}
        <main class="flex-1 flex flex-col" style="min-width: 0;">
            {{-- Top header --}}
            <header class="flex items-center gap-4 sticky top-0 z-10"
                    style="height: 64px; padding: 0 28px; background: var(--surface); border-bottom: 1px solid var(--border);">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden" style="background: transparent; border: 0; padding: 6px;">
                    <x-icon name="menu" size="20"/>
                </button>
                <div class="flex-1 relative" style="max-width: 480px;">
                    <x-icon name="search" size="16" class="absolute" style="left: 12px; top: 11px; color: var(--text-faint);"/>
                    <input class="input" placeholder="Search questions, papers, topics, students…"
                           style="padding-left: 38px; height: 38px; background: var(--soft-surface);" />
                    <kbd class="absolute" style="right: 10px; top: 9px; font-size: 10px; padding: 2px 6px; color: var(--text-faint); border: 1px solid var(--border); border-radius: 4px; background: var(--surface); font-family: var(--mono);">⌘K</kbd>
                </div>
                <div class="flex-1"></div>
                <button class="btn btn-ghost btn-sm" style="padding: 8px;"><x-icon name="bell" size="17"/></button>
                <x-theme-toggle />
                <button class="btn btn-ghost btn-sm" style="padding: 8px;"><x-icon name="settings" size="17"/></button>
            </header>

            {{-- Page header --}}
            @if(!empty($breadcrumb) || $pageTitle)
                <div style="padding: 24px 28px 12px;">
                    @if(!empty($breadcrumb))
                        <div class="flex items-center gap-1.5" style="font-size: 12px; color: var(--text-faint); margin-bottom: 6px;">
                            @foreach($breadcrumb as $i => $crumb)
                                @if($i > 0)<x-icon name="chev-r" size="11" stroke="2"/>@endif
                                <span style="color: {{ $i === count($breadcrumb) - 1 ? 'var(--text)' : 'var(--text-faint)' }}; font-weight: {{ $i === count($breadcrumb) - 1 ? 500 : 400 }};">{{ $crumb }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if($pageTitle)
                        <div class="flex items-end gap-4">
                            <h1 class="serif" style="font-size: 28px; font-weight: 600; color: var(--text); letter-spacing: -0.015em;">{{ $pageTitle }}</h1>
                            <div class="flex-1"></div>
                            @isset($actions){{ $actions }}@endisset
                        </div>
                    @endif
                </div>
            @endif

            <div class="fade-in" style="padding: 8px 28px 40px; flex: 1;">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
    @fluxScripts
</body>
</html>
