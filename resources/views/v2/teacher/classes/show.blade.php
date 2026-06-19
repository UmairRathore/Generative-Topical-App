@extends('v2.layouts.teacher')
@section('page_title', 'Class Analytics')

@php
    $barColor = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)');
    $cellBg = fn ($p) => $p >= 60 ? 'var(--ok-soft)' : ($p >= 40 ? 'var(--warn-soft)' : 'var(--bad-soft)');
    $totC = array_sum(array_column($topicStats, 'correct'));
    $totT = array_sum(array_column($topicStats, 'total'));
    $classAvg = $totT ? (int) round($totC / $totT * 100) : null;
@endphp

@section('content')
<a href="{{ route('v2.teacher.classes.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Classes
</a>

<div style="margin-bottom: 20px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $class->name }}</h2>
    <div class="flex items-center gap-2" style="margin-top: 6px;">
        <span class="badge badge-soft">{{ $class->grade?->name }}</span>
        <span class="badge badge-emerald">{{ $class->subject?->name }}</span>
    </div>
</div>

{{-- Stat cards --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
    @foreach ([
        ['Students', $studentCount, 'users'],
        ['Exams', $exams->count(), 'clipboard'],
        ['Class average', $classAvg !== null ? $classAvg.'%' : '-', 'chart'],
        ['Topics covered', count($topicStats), 'target'],
    ] as [$label, $value, $icon])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                <x-icon :name="$icon" size="13"/> {{ $label }}
            </div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- Per-topic (whole class) --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
    <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Class performance by topic</div>
    @forelse ($topicStats as $t)
        <div style="margin-bottom: 13px;">
            <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                <span style="font-weight: 500;">{{ $t['topic'] }}</span>
                <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
            </div>
            <div style="height: 8px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $barColor($t['percent']) }};"></div>
            </div>
        </div>
    @empty
        <p style="font-size: 13px; color: var(--text-faint);">No completed exams yet - analytics appear once students submit tests.</p>
    @endforelse
</div>

{{-- Per-student × per-topic matrix --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Per-student, per-topic</div>
    @if (empty($matrix['topics']))
        <div style="padding: 32px; text-align: center; color: var(--text-faint); font-size: 13px;">No data yet.</div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 520px;">
                <thead>
                    <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                        <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint); position: sticky; left: 0; background: var(--soft-surface);">Student</th>
                        @foreach ($matrix['topics'] as $topic)
                            <th style="padding: var(--pad-cell); text-align: center; font-size: 11px; font-weight: 600; color: var(--text-faint);">{{ $topic }}</th>
                        @endforeach
                        <th style="padding: var(--pad-cell); text-align: center; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">Overall</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrix['rows'] as $row)
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: var(--pad-cell); position: sticky; left: 0; background: var(--surface);">
                                <a href="{{ route('v2.teacher.students.show', hid($row['id'])) }}" style="font-size: 13px; font-weight: 500; color: var(--gold-700); text-decoration: none;">{{ $row['student'] }}</a>
                                <div style="font-size: 11px; color: var(--text-faint);">{{ $row['roll'] }}</div>
                            </td>
                            @foreach ($matrix['topics'] as $topic)
                                @php $cell = $row['cells'][$topic] ?? null; @endphp
                                <td style="padding: 8px; text-align: center;">
                                    @if ($cell)
                                        <span style="display: inline-block; min-width: 46px; padding: 4px 8px; border-radius: 6px; font-size: 12.5px; font-weight: 600; background: {{ $cellBg($cell['percent']) }}; color: {{ $barColor($cell['percent']) }};"
                                              title="{{ $cell['correct'] }}/{{ $cell['total'] }}">{{ $cell['percent'] }}%</span>
                                    @else
                                        <span style="color: var(--text-faint); font-size: 12px;">-</span>
                                    @endif
                                </td>
                            @endforeach
                            <td style="padding: var(--pad-cell); text-align: center; font-weight: 700; font-size: 13px;">
                                {{ $row['overall'] !== null ? $row['overall'].'%' : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
