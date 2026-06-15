@extends('v2.layouts.school_admin')
@section('page_title', 'Student Analytics')

@php $tone = fn ($p) => $p >= 60 ? 'var(--emerald-700)' : ($p >= 40 ? 'var(--accent)' : '#ef4444'); @endphp

@section('content')
<a href="{{ route('v2.school.analytics.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Analytics
</a>

<div style="margin-bottom: 20px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $student->name }}</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Roll {{ $student->roll_number }}@if ($student->email) · {{ $student->email }}@endif</p>
</div>

@if ($stats['overall']['tests'] === 0)
    <div style="padding: 40px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
        This student hasn't completed any tests yet.
    </div>
@else
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px;">
        @foreach ([
            ['Tests completed', $stats['overall']['tests'], 'clipboard'],
            ['Overall average', $stats['overall']['avg'].'%', 'chart'],
            ['Subjects', count($stats['subjects']), 'book'],
        ] as [$label, $value, $icon])
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
                <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                    <x-icon :name="$icon" size="13"/> {{ $label }}
                </div>
                <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @foreach ($stats['subjects'] as $subject)
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px; margin-bottom: 18px;">
            <div class="flex items-center justify-between" style="margin-bottom: 18px;">
                <h3 class="serif" style="font-size: 19px; font-weight: 600;">{{ $subject['subject'] }}</h3>
                <span class="badge badge-emerald" style="font-size: 13px; font-weight: 700;">{{ $subject['avg'] }}%</span>
            </div>
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">By topic</div>
                    @include('v2.partials.topic_bars', ['stats' => $subject['topics'], 'empty' => 'No topic data.'])
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">Tests</div>
                    <div class="space-y-2">
                        @foreach ($subject['tests'] as $test)
                            <div class="flex items-center justify-between" style="padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;">
                                <div style="min-width: 0;">
                                    <div style="font-size: 13px; font-weight: 500;">{{ $test['title'] }}</div>
                                    <div style="font-size: 11.5px; color: var(--text-faint);">{{ $test['topic'] }} · {{ $test['date']?->diffForHumans() }}</div>
                                </div>
                                <div style="text-align: right; flex: none;">
                                    <span style="font-weight: 700; font-size: 14px; color: {{ $tone($test['percent']) }};">{{ $test['percent'] }}%</span>
                                    <div style="font-size: 11px; color: var(--text-faint);">{{ $test['score'] }}/{{ $test['total'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
