@extends('v2.layouts.teacher')
@section('page_title', 'Manage test')

@php
    $stateBadge = [
        'draft'     => ['badge-soft', 'Draft'],
        'scheduled' => ['badge-review', 'Scheduled'],
        'live'      => ['badge-pass', 'Live'],
        'expired'   => ['badge-blocker', 'Expired'],
    ];
    [$sbClass, $sbLabel] = $stateBadge[$exam->effectiveStatus()];
    $fieldStyle = 'padding: 8px 11px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);';
@endphp

@section('content')
<a href="{{ route('v2.teacher.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Exams
</a>

<div class="flex items-start justify-between" style="margin-bottom: 18px; gap: 16px; flex-wrap: wrap;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $exam->title }}</h2>
        <div class="flex items-center gap-2" style="margin-top: 6px; flex-wrap: wrap;">
            <span class="badge {{ $sbClass }}">{{ $sbLabel }}</span>
            @if ($exam->resultsReleased())
                <span class="badge badge-pass">Results released</span>
            @elseif ($exam->isReleased())
                <span class="badge badge-soft">Results hidden</span>
            @endif
            <span class="badge badge-soft">{{ $exam->schoolClass?->name }}</span>
            <span class="badge badge-emerald">{{ $exam->topic?->title ?? 'Mixed topics' }}</span>
            <span class="badge badge-soft">{{ $exam->question_count }} questions</span>
        </div>
    </div>
</div>

@if (session('success'))
    <div style="margin-bottom: 16px; padding: 11px 16px; background: rgba(var(--ok-rgb,95,160,82),.12); border: 1px solid var(--ok); border-radius: 8px; font-size: 13px; color: var(--ok); font-weight: 600;">{{ session('success') }}</div>
@endif

{{-- ============ MANAGE: availability + results ============ --}}
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px; align-items: stretch;">

    {{-- Availability --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
        <div class="flex items-center gap-2" style="margin-bottom: 14px;">
            <x-icon name="clipboard" size="15"/>
            <span style="font-size: 14px; font-weight: 700;">Test availability</span>
        </div>

        @if ($exam->isDraft())
            <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 14px; line-height: 1.5;">
                This test is a <strong>draft</strong> - students can't see it yet. Release it now or schedule it, with an optional expiry after which no student can access it.
            </p>
            <form method="POST" action="{{ route('v2.teacher.exams.release', $exam) }}" x-data="{ mode: 'now' }">
                @csrf @method('PATCH')
                <input type="hidden" name="mode" :value="mode">
                <div style="display:inline-flex; border:1px solid var(--border); border-radius:8px; overflow:hidden; margin-bottom:14px;">
                    <button type="button" style="padding:8px 16px; font-size:12.5px; font-weight:600; border:0; cursor:pointer;" :style="mode==='now' ? 'background:var(--primary,#061C30);color:#fff;' : 'background:var(--bg);color:var(--text-soft);'" @click="mode='now'">Release now</button>
                    <button type="button" style="padding:8px 16px; font-size:12.5px; font-weight:600; border:0; cursor:pointer;" :style="mode==='schedule' ? 'background:var(--primary,#061C30);color:#fff;' : 'background:var(--bg);color:var(--text-soft);'" @click="mode='schedule'">Schedule</button>
                </div>
                <div class="space-y-3">
                    <div x-show="mode==='schedule'">
                        <label style="display:block; font-size:11px; color:var(--text-faint); margin-bottom:4px;">Opens at</label>
                        <input type="datetime-local" name="release_at" :required="mode==='schedule'" style="{{ $fieldStyle }} width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; color:var(--text-faint); margin-bottom:4px;">Expires at <span style="color:var(--text-faint);">- optional</span></label>
                        <input type="datetime-local" name="expires_at" style="{{ $fieldStyle }} width:100%;">
                    </div>
                    <label class="flex items-center gap-2" style="font-size:12.5px; color:var(--text-soft); cursor:pointer;">
                        <input type="checkbox" name="release_results" value="1" style="width:15px;height:15px;">
                        Release results immediately - students see their score as they submit
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:14px;"><x-icon name="play" size="13"/> Release test</button>
            </form>
        @else
            <div style="font-size: 13px; color: var(--text-soft); line-height: 1.7;">
                <div>
                    @if ($exam->isScheduled())
                        <strong style="color:var(--text);">Opens</strong> {{ $exam->available_from->format('D j M, g:i A') }} ({{ $exam->available_from->diffForHumans() }})
                    @else
                        <strong style="color:var(--text);">Released</strong> {{ $exam->released_at?->diffForHumans() }}, open since {{ $exam->available_from?->format('D j M, g:i A') }}
                    @endif
                </div>
                <div>
                    @if ($exam->available_until)
                        <strong style="color:var(--text);">{{ $exam->isExpired() ? 'Closed' : 'Closes' }}</strong> {{ $exam->available_until->format('D j M, g:i A') }} ({{ $exam->available_until->diffForHumans() }})
                    @else
                        <strong style="color:var(--text);">No expiry</strong> - stays open until you close it.
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Results --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
        <div class="flex items-center gap-2" style="margin-bottom: 14px;">
            <x-icon name="chart" size="15"/>
            <span style="font-size: 14px; font-weight: 700;">Results to students</span>
        </div>

        <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 14px; line-height: 1.5;">
            <strong>{{ $submittedCount }}</strong> of {{ $students->count() }} {{ \Illuminate\Support\Str::plural('student', $students->count()) }} submitted.
            Students don't see their score or answers until you release results.
        </p>

        @if ($exam->resultsReleased())
            <div class="flex items-center gap-2" style="font-size: 13px; color: var(--ok); font-weight: 600; margin-bottom: 14px;">
                <x-icon name="check" size="15"/> Released {{ $exam->results_released_at->diffForHumans() }} - students can see their results.
            </div>
            <form method="POST" action="{{ route('v2.teacher.exams.release_results', $exam) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="release" value="0">
                <button type="submit" class="btn btn-ghost btn-sm">Hide results again</button>
            </form>
        @else
            <div style="font-size: 13px; color: var(--text-soft); margin-bottom: 14px;">Results are currently <strong>hidden</strong> - submitted students see only “awaiting results”.</div>
            <form method="POST" action="{{ route('v2.teacher.exams.release_results', $exam) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="release" value="1">
                <button type="submit" class="btn btn-primary"><x-icon name="check" size="13"/> Release results to students</button>
            </form>
        @endif
    </div>
</div>

{{-- Stat cards --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
    @foreach ([
        ['Class average', $avg !== null ? $avg.'%' : '-', 'chart'],
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
                    @php $a = $attempts[$student->id] ?? null; $done = $a && $a->status === 'submitted'; @endphp
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">
                            @if ($done)
                                <a href="{{ route('v2.teacher.exams.student_paper', [$exam, $student]) }}"
                                   class="flex items-center gap-1" style="color: var(--emerald-700); text-decoration: none;">
                                    {{ $student->name }} <x-icon name="chev-r" size="13"/>
                                </a>
                            @else
                                {{ $student->name }}
                            @endif
                        </td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ $student->roll_number }}</td>
                        <td style="padding: var(--pad-cell);">
                            @if ($done)
                                <span class="badge badge-pass">Completed</span>
                            @elseif ($a)
                                <span class="badge badge-review">In progress</span>
                            @elseif ($exam->isExpired())
                                <span class="badge badge-blocker">Missed</span>
                            @elseif ($exam->isLive())
                                <span class="badge badge-soft">Not started</span>
                            @else
                                <span class="badge badge-soft">-</span>
                            @endif
                        </td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">
                            {{ $done ? ($a->time_taken ?? '-') : '-' }}
                        </td>
                        <td style="padding: var(--pad-cell); text-align: right; font-weight: 600; font-size: 13px;">
                            @if ($done)
                                {{ $a->score }}/{{ $a->total_questions }} <span style="color: var(--text-faint); font-weight: 500;">({{ $a->percentage }}%)</span>
                            @else
                                <span style="color: var(--text-faint);">-</span>
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
                    <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $t['percent'] >= 60 ? 'var(--ok)' : ($t['percent'] >= 40 ? 'var(--warn)' : 'var(--bad)') }};"></div>
                </div>
            </div>
        @empty
            <p style="font-size: 13px; color: var(--text-faint);">No submissions yet - stats appear once students complete the test.</p>
        @endforelse
    </div>
</div>
@endsection
