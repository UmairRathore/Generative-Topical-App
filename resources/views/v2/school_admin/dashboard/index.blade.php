@extends('v2.layouts.school_admin')
@section('page_title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Teachers',  'value' => $stats['teachers'],  'icon' => 'user-check', 'href' => route('v2.school.teachers.index'),  'color' => 'emerald'],
            ['label' => 'Students',  'value' => $stats['students'],  'icon' => 'users',      'href' => route('v2.school.students.index'),  'color' => 'gold'],
            ['label' => 'Classes',   'value' => $stats['classes'],   'icon' => 'calendar',   'href' => route('v2.school.classes.index'),   'color' => 'emerald'],
            ['label' => 'Grades',    'value' => $stats['grades'],    'icon' => 'layers',     'href' => route('v2.school.grades.index'),    'color' => 'gold'],
        ] as $card)
        <a href="{{ $card['href'] }}" class="block" style="text-decoration: none;">
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; transition: box-shadow .15s;">
                <div class="flex items-center justify-between">
                    <div style="font-size: 28px; font-weight: 700; color: var(--text);">{{ number_format($card['value']) }}</div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: {{ $card['color'] === 'emerald' ? 'rgba(6,95,70,.1)' : 'rgba(212,164,55,.1)' }}; display: flex; align-items: center; justify-content: center; color: {{ $card['color'] === 'emerald' ? 'var(--emerald-700)' : 'var(--accent)' }};">
                        <x-icon :name="$card['icon']" size="20" />
                    </div>
                </div>
                <div style="font-size: 13px; color: var(--muted); margin-top: 6px;">{{ $card['label'] }}</div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- License usage --}}
    @php
        $teacherPct = $school->max_teachers > 0 ? round($stats['teachers'] / $school->max_teachers * 100) : 0;
        $studentPct = $school->max_students > 0 ? round($stats['students'] / $school->max_students * 100) : 0;
    @endphp
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
        <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">License Usage — {{ $school->license_tier }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach([
                ['label' => 'Teachers', 'used' => $stats['teachers'], 'max' => $school->max_teachers, 'pct' => $teacherPct],
                ['label' => 'Students', 'used' => $stats['students'], 'max' => $school->max_students, 'pct' => $studentPct],
            ] as $bar)
            <div>
                <div class="flex justify-between" style="font-size: 13px; margin-bottom: 6px; color: var(--text);">
                    <span>{{ $bar['label'] }}</span>
                    <span style="color: var(--muted);">{{ $bar['used'] }} / {{ $bar['max'] }}</span>
                </div>
                <div style="height: 8px; border-radius: 4px; background: var(--border);">
                    <div style="height: 8px; border-radius: 4px; width: {{ min($bar['pct'], 100) }}%; background: {{ $bar['pct'] >= 90 ? '#ef4444' : ($bar['pct'] >= 70 ? '#f59e0b' : 'var(--emerald-600)') }}; transition: width .3s;"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Recent activity --}}
    @if($recentLogs->count())
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
        <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Recent Activity</h2>
        <div class="space-y-2">
            @foreach($recentLogs as $log)
            <div class="flex items-start gap-3" style="padding: 10px; border-radius: 8px; background: var(--bg);">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--emerald-500); margin-top: 5px; flex: none;"></div>
                <div>
                    <div style="font-size: 13px; color: var(--text);">{{ $log->action }}</div>
                    <div style="font-size: 11.5px; color: var(--muted);">{{ $log->created_at?->diffForHumans() ?? '—' }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
