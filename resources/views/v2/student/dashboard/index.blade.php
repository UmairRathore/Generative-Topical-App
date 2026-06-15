@extends('v2.layouts.student')
@section('page_title', 'Dashboard')

@php
    $user = auth('v2_student')->user();
    $barColor = fn ($p) => $p >= 60 ? 'var(--emerald-700)' : ($p >= 40 ? 'var(--accent)' : '#ef4444');
@endphp

@section('content')
<div style="margin-bottom: 24px;">
    <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Student Dashboard</div>
    <h2 class="serif" style="font-size: 30px; font-weight: 600; margin-top: 4px;">Welcome, {{ $user?->name }}</h2>
    <p style="color: var(--text-soft); margin-top: 2px; font-size: 14px;">{{ $user?->school?->name ?? 'Your School' }}</p>
</div>

@if ($stats['overall']['tests'] === 0)
    <div style="padding: 44px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
        <p style="color: var(--text-soft); font-size: 14px;">You haven't completed any tests yet.</p>
        <a href="{{ route('v2.student.exams.index') }}" class="btn btn-primary btn-sm" style="margin-top: 14px;"><x-icon name="play" size="12"/> Go to My Exams</a>
    </div>
@else
    {{-- Overall --}}
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

    {{-- Per subject --}}
    @foreach ($stats['subjects'] as $subject)
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px; margin-bottom: 18px;">
            <div class="flex items-center justify-between" style="margin-bottom: 18px;">
                <div class="flex items-center gap-3">
                    <h3 class="serif" style="font-size: 19px; font-weight: 600;">{{ $subject['subject'] }}</h3>
                    <span class="badge badge-soft">{{ $subject['tests_count'] }} {{ Str::plural('test', $subject['tests_count']) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span style="font-size: 12px; color: var(--text-faint);">average</span>
                    <span class="badge badge-emerald" style="font-size: 13px; font-weight: 700;">{{ $subject['avg'] }}%</span>
                </div>
            </div>

            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
                {{-- By topic --}}
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">By topic</div>
                    @forelse ($subject['topics'] as $t)
                        <div style="margin-bottom: 12px;">
                            <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                                <span style="font-weight: 500;">{{ $t['topic'] }}</span>
                                <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
                            </div>
                            <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                                <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $barColor($t['percent']) }};"></div>
                            </div>
                        </div>
                    @empty
                        <p style="font-size: 12.5px; color: var(--text-faint);">No topic data.</p>
                    @endforelse
                </div>

                {{-- Tests --}}
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">Tests</div>
                    <div class="space-y-2">
                        @foreach ($subject['tests'] as $test)
                            <a href="{{ route('v2.student.exams.result', $test['exam_id']) }}"
                               class="flex items-center justify-between" style="text-decoration: none; color: inherit; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;">
                                <div style="min-width: 0;">
                                    <div style="font-size: 13px; font-weight: 500;">{{ $test['title'] }}</div>
                                    <div style="font-size: 11.5px; color: var(--text-faint);">{{ $test['topic'] }} · {{ $test['date']?->diffForHumans() }}</div>
                                </div>
                                <div style="text-align: right; flex: none;">
                                    <span style="font-weight: 700; font-size: 14px; color: {{ $barColor($test['percent']) }};">{{ $test['percent'] }}%</span>
                                    <div style="font-size: 11px; color: var(--text-faint);">{{ $test['score'] }}/{{ $test['total'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
