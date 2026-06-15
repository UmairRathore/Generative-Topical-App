@extends('v2.layouts.teacher')
@section('page_title', 'Exam Results')

@section('content')
<a href="{{ route('v2.teacher.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Exams
</a>

<div class="flex items-start justify-between" style="margin-bottom: 22px;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $exam->title }}</h2>
        <div class="flex items-center gap-2" style="margin-top: 6px;">
            <span class="badge badge-soft">{{ $exam->schoolClass?->name }}</span>
            <span class="badge badge-emerald">{{ $exam->topic?->title ?? 'Mixed topics' }}</span>
            <span class="badge badge-soft">{{ $exam->question_count }} questions</span>
        </div>
    </div>
</div>

{{-- Stat cards --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
    @foreach ([
        ['Class average', $avg !== null ? $avg.'%' : '—', 'chart'],
        ['Submitted', $submittedCount.' / '.$students->count(), 'check'],
        ['Questions', $exam->question_count, 'clipboard'],
        ['Topic', $exam->topic?->title ?? 'Mixed', 'target'],
    ] as [$label, $value, $icon])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                <x-icon :name="$icon" size="13"/> {{ $label }}
            </div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 20px; align-items: start;">

    {{-- Student results --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Student results</div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['Student', 'Roll', 'Status', 'Time', 'Score'] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    @php $a = $attempts[$student->id] ?? null; @endphp
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $student->name }}</td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ $student->roll_number }}</td>
                        <td style="padding: var(--pad-cell);">
                            @if ($a && $a->status === 'submitted')
                                <span class="badge badge-pass">Completed</span>
                            @elseif ($a)
                                <span class="badge badge-review">In progress</span>
                            @else
                                <span class="badge badge-soft">Not started</span>
                            @endif
                        </td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">
                            {{ $a && $a->status === 'submitted' ? ($a->time_taken ?? '—') : '—' }}
                        </td>
                        <td style="padding: var(--pad-cell); text-align: right; font-weight: 600; font-size: 13px;">
                            @if ($a && $a->status === 'submitted')
                                {{ $a->score }}/{{ $a->total_questions }} <span style="color: var(--text-faint); font-weight: 500;">({{ $a->percentage }}%)</span>
                            @else
                                <span style="color: var(--text-faint);">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 32px; text-align: center; color: var(--text-faint); font-size: 13px;">No students enrolled in this class.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Per-topic stats --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Performance by topic</div>
        @forelse ($topicStats as $t)
            <div style="margin-bottom: 14px;">
                <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                    <span style="font-weight: 500;">{{ $t['topic'] }}</span>
                    <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
                </div>
                <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                    <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $t['percent'] >= 60 ? 'var(--emerald-700)' : ($t['percent'] >= 40 ? 'var(--accent)' : '#ef4444') }};"></div>
                </div>
            </div>
        @empty
            <p style="font-size: 13px; color: var(--text-faint);">No submissions yet — stats appear once students complete the test.</p>
        @endforelse
    </div>
</div>
@endsection
