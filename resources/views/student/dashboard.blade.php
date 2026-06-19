@php
    $assignedTests = [
        ['n' => 'Mechanics - Mock Set A',     't' => 'Dr. Saima Iqbal', 's' => 'Physics',   'q' => 40, 'due' => 'May 4', 'st' => 'in-progress', 'prog' => 22],
        ['n' => 'Organic Chemistry Topical',  't' => 'Ms. Fariha Aziz', 's' => 'Chemistry', 'q' => 25, 'due' => 'May 6', 'st' => 'not-started', 'prog' => 0],
        ['n' => 'Trigonometry Practice',      't' => 'Mr. A. Mahmood',  's' => 'Math',      'q' => 30, 'due' => 'May 9', 'st' => 'not-started', 'prog' => 0],
    ];
    $mastery = [['Mechanics',92], ['Electricity',81], ['Waves',73], ['Thermal',64], ['Atomic',58]];
@endphp
<x-layouts.dashboard role="student" :breadcrumb="['Dashboard']" pageTitle="Welcome back, {{ auth()->user()->name ?? 'Ayesha' }}.">
    <x-slot:actions>
        <a href="{{ route('student.tests.show', 1) }}" wire:navigate class="btn btn-primary btn-sm"><x-icon name="play" size="13"/>Continue practice</a>
    </x-slot:actions>

    {{-- Welcome banner --}}
    <div class="grid relative" style="background: linear-gradient(120deg, var(--emerald-900), var(--emerald-800) 70%); color: var(--ivory); border-radius: 14px; padding: 28px; margin-bottom: 24px; overflow: hidden; grid-template-columns: 1.4fr 1fr; gap: 24px;">
        <div class="grain absolute" style="inset: 0;"></div>
        <div class="absolute" style="right: -60px; top: -40px; opacity: 0.07;"><x-crest size="300" variant="mono-light"/></div>
        <div class="relative">
            <div class="uppercase-eyebrow" style="color: var(--accent);">Y12 · A Level Physics · 9702</div>
            <h2 class="serif" style="font-size: 28px; font-weight: 600; margin-top: 8px; letter-spacing: -0.01em; max-width: 460px; line-height: 1.2;">
                You're <em style="color: var(--accent);">3 days</em> from your Mechanics target. Keep your 12-day streak alive.
            </h2>
            <div class="flex" style="gap: 12px; margin-top: 20px;">
                <a href="{{ route('student.tests.show', 1) }}" wire:navigate class="btn btn-gold btn-sm">Resume Mechanics - Mock Set A</a>
                <a href="{{ route('student.practice') }}" wire:navigate class="btn btn-outline-light btn-sm">Practice a topic</a>
            </div>
        </div>
        <div class="relative flex items-center justify-end">
            <div style="background: rgba(255,255,255,0.06); border-radius: 12px; padding: 18px; border: 1px solid rgba(212,164,55,0.25); width: 220px;">
                <div style="font-size: 11px; color: rgba(250,247,239,0.7); text-transform: uppercase; letter-spacing: 0.06em;">Current streak</div>
                <div class="serif" style="font-size: 44px; font-weight: 600; color: var(--accent); margin-top: 4px;">12<span style="font-size: 14px; color: rgba(250,247,239,0.7); margin-left: 6px;">days</span></div>
                <div class="flex" style="gap: 3px; margin-top: 12px;">
                    @for($i = 0; $i < 14; $i++)
                        <div style="flex: 1; height: 24px; border-radius: 3px; background: {{ $i < 12 ? 'var(--accent)' : 'rgba(255,255,255,0.08)' }};"></div>
                    @endfor
                </div>
                <div style="font-size: 10px; color: rgba(250,247,239,0.5); margin-top: 6px;">Last 14 days</div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;">
        <x-stat-card label="Tests Attempted" value="23" delta="+4 this week" icon="clipboard"/>
        <x-stat-card label="Average Score" value="84%" delta="↑ 6%" icon="target" accent="gold"/>
        <x-stat-card label="Best Topic" value="Mechanics" delta="92% · 18 attempts" icon="trophy" accent="gold"/>
        <x-stat-card label="Weakest" value="Atomic" delta="58% · focus area" :positive="false" icon="flag"/>
        <x-stat-card label="Hours practised" value="46h" delta="This term" icon="clock"/>
    </div>

    <div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 16px;">
        {{-- Assigned tests --}}
        <div class="card-elev">
            <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Assigned tests</h3>
            @foreach($assignedTests as $t)
                <div class="flex items-center" style="gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--border-soft);">
                    <div class="flex items-center justify-center" style="width: 44px; height: 44px; border-radius: 8px; background: var(--emerald-50); color: var(--emerald-800); flex: none;">
                        <x-icon name="clipboard" size="18"/>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 14px;">{{ $t['n'] }}</div>
                        <div style="font-size: 11px; color: var(--text-faint);">{{ $t['s'] }} · {{ $t['q'] }} questions · by {{ $t['t'] }}</div>
                        @if($t['prog'] > 0)
                            <div class="flex items-center" style="gap: 8px; margin-top: 6px; font-size: 11px;">
                                <div style="flex: 1; height: 4px; background: var(--slate-100); border-radius: 2px; overflow: hidden;">
                                    <div style="width: {{ ($t['prog'] / $t['q']) * 100 }}%; height: 100%; background: var(--gold-500);"></div>
                                </div>
                                <span style="color: var(--gold-700); font-weight: 600;">{{ $t['prog'] }}/{{ $t['q'] }}</span>
                            </div>
                        @endif
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 11px; color: var(--text-faint); margin-bottom: 4px;">Due {{ $t['due'] }}</div>
                        <a href="{{ route('student.tests.show', 1) }}" wire:navigate class="btn btn-primary btn-sm">{{ $t['st'] === 'in-progress' ? 'Resume' : 'Start' }}</a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Topic mastery --}}
        <div class="card-elev">
            <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Topic mastery</h3>
            @foreach($mastery as [$t, $p])
                @php $c = $p >= 75 ? 'var(--success)' : ($p >= 60 ? 'var(--gold-500)' : 'var(--warning)'); @endphp
                <div style="margin-bottom: 14px;">
                    <div class="flex justify-between" style="margin-bottom: 5px; font-size: 13px;">
                        <span style="font-weight: 500;">{{ $t }}</span>
                        <span style="font-weight: 600; color: {{ $c }};">{{ $p }}%</span>
                    </div>
                    <div style="height: 6px; background: var(--slate-100); border-radius: 3px; overflow: hidden;">
                        <div style="width: {{ $p }}%; height: 100%; background: {{ $c }};"></div>
                    </div>
                </div>
            @endforeach
            <a href="{{ route('student.analytics') }}" wire:navigate class="btn btn-ghost btn-sm" style="width: 100%; margin-top: 6px;">View full analytics <x-icon name="chev-r" size="12"/></a>
        </div>
    </div>
</x-layouts.dashboard>
