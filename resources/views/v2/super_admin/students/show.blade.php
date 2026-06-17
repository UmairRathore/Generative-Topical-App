@extends('v2.layouts.super_admin')
@section('page_title', $student->name)

@php $tone = fn ($p) => $p >= 60 ? 'var(--emerald-700)' : ($p >= 40 ? 'var(--accent)' : '#ef4444'); @endphp

@section('content')
<a href="{{ route('v2.super_admin.schools.show', $school) }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> {{ $school->name }}
</a>

<h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $student->name }}</h2>
<p style="color: var(--text-soft); font-size: 13px; margin-top: 2px; margin-bottom: 22px;">
    @if ($student->roll_number)Roll {{ $student->roll_number }}@endif
    @if ($student->email) · {{ $student->email }}@endif
</p>

@if ($stats['overall']['tests'] === 0)
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 40px; text-align: center; color: var(--text-faint); font-size: 14px;">
        This student hasn't completed any tests yet.
    </div>
@else
    {{-- KPIs --}}
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
        @foreach ([
            ['Tests completed', $stats['overall']['tests']],
            ['Overall average', $stats['overall']['avg'].'%'],
            ['Subjects', count($stats['subjects'])],
        ] as [$label, $value])
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
                <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
                <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 5px;">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @foreach ($stats['subjects'] as $subject)
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 18px;">
            <div class="flex items-center gap-2" style="margin-bottom: 16px;">
                <h3 style="font-size: 15px; font-weight: 600;">{{ $subject['subject'] }}</h3>
                <span class="badge badge-soft">{{ $subject['avg'] }}% avg · {{ $subject['tests_count'] }} {{ \Illuminate\Support\Str::plural('test', $subject['tests_count']) }}</span>
            </div>
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 22px; align-items: start;">
                {{-- By topic --}}
                <div>
                    <div style="font-size: 12px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 12px;">By topic</div>
                    @include('v2.partials.topic_bars', ['stats' => $subject['topics'], 'empty' => 'No topic data.'])
                </div>
                {{-- Tests --}}
                <div>
                    <div style="font-size: 12px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 12px;">Tests</div>
                    @foreach ($subject['tests'] as $t)
                        <a href="{{ route('v2.super_admin.schools.student_paper', [$school, $t['exam_id'], $student]) }}"
                           class="flex items-center justify-between"
                           style="padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; text-decoration: none; color: inherit;">
                            <div style="min-width: 0;">
                                <div style="font-size: 13px; font-weight: 500;">{{ $t['title'] }}</div>
                                <div style="font-size: 11.5px; color: var(--text-faint);">{{ $t['topic'] }} · {{ $t['date']?->diffForHumans() }}</div>
                            </div>
                            <div style="text-align: right; flex: none;">
                                <div style="font-size: 14px; font-weight: 700; color: {{ $tone($t['percent']) }};">{{ $t['percent'] }}%</div>
                                <div style="font-size: 11px; color: var(--text-faint);">{{ $t['score'] }}/{{ $t['total'] }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
