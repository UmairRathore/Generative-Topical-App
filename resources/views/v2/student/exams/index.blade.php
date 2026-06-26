@extends('v2.layouts.student')
@section('page_title', 'My Exams')

@php $barColor = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp

@section('content')
<style>
    [x-cloak]{display:none!important}
    .tx-modal{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px}
    .ex-table{width:100%;border-collapse:collapse;}
    .ex-table th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);}
    .ex-table td{padding:13px 16px;font-size:13px;vertical-align:middle;}
    .ex-table tbody tr{border-top:1px solid var(--border);}
    .ex-chip{display:inline-flex;align-items:center;gap:5px;border:0;background:none;cursor:pointer;font:inherit;}
</style>

<div style="margin-bottom: 22px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">My Exams</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Tests assigned to your class, grouped by subject. Complete them online and see your score instantly.</p>
</div>

@if (session('success'))
    <div style="margin-bottom: 16px; padding: 12px 16px; background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 8px; font-size: 13px; color: #065f46;">{{ session('success') }}</div>
@endif

<div x-data="{ tOpen: false, tTitle: '', tTopics: [], tScored: false, perfOpen: false, perfTitle: '', perfTopics: [] }">

@forelse ($examsBySubject as $subjectName => $subjectExams)
    @php
        $st = $subjectStats[$subjectName] ?? null;
        $total = $subjectExams->count();
        $attempted = $st['tests_count'] ?? 0;
        $missed = $st['missed_count'] ?? 0;
        $avg = $st['avg'] ?? 0;
        $perfTopics = $st['topics'] ?? [];
        $avgColor = $barColor($avg);
    @endphp
    <div x-data="{ open: false }" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); margin-bottom: 16px; overflow: hidden;">

        {{-- Container header (always visible) --}}
        <div class="flex items-center justify-between" style="gap: 12px; padding: 16px 20px; cursor: pointer;" @click="open = !open">
            <div class="flex items-center gap-3" style="min-width: 0; flex-wrap: wrap;">
                <span style="flex: none; width: 34px; height: 34px; border-radius: 9px; background: var(--soft-surface); display: flex; align-items: center; justify-content: center; color: var(--accent);"><x-icon name="book" size="17"/></span>
                <h3 class="serif" style="font-size: 18px; font-weight: 600;">{{ $subjectName }}</h3>
            </div>
            <div class="flex items-center" style="gap: 8px; flex: none; flex-wrap: wrap; justify-content: flex-end;">
                <span class="badge badge-soft" style="font-size: 11.5px;">{{ $total }} {{ Str::plural('test', $total) }}</span>
                <span class="badge badge-soft" style="font-size: 11.5px;"><x-icon name="check" size="11"/> {{ $attempted }} attempted</span>
                <span class="badge badge-soft" style="font-size: 11.5px; color: {{ $missed ? 'var(--bad)' : 'var(--text-soft)' }};"><x-icon name="flag" size="11"/> {{ $missed }} missed</span>
                @if ($attempted > 0)
                    <span class="badge" style="font-size: 12.5px; font-weight: 700; color: {{ $avgColor }}; border: 1px solid {{ $avgColor }};">{{ $avg }}% avg</span>
                @else
                    <span class="badge badge-soft" style="font-size: 11.5px; color: var(--text-faint);">no score yet</span>
                @endif
                @if (count($perfTopics))
                    <button type="button" class="btn btn-ghost btn-sm" style="flex: none;"
                            @click.stop="perfTitle = @js($subjectName); perfTopics = @js($perfTopics); perfOpen = true">
                        <x-icon name="chart" size="13"/> Topics
                    </button>
                @endif
                <span class="flex" style="color: var(--text-soft); transition: transform .2s;" :style="open ? 'transform: rotate(180deg);' : ''"><x-icon name="chev-d" size="16"/></span>
            </div>
        </div>

        {{-- Exams table (expanded) --}}
        <div x-show="open" x-cloak x-transition.opacity style="border-top: 1px solid var(--border); overflow-x: auto;">
            <table class="ex-table">
                <thead>
                    <tr style="background: var(--soft-surface);">
                        <th style="width: 40px;">#</th>
                        <th>Test</th>
                        <th>Topics</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th style="text-align: right;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjectExams as $exam)
                        @php
                            $a = $attempts[$exam->id] ?? null;
                            $done = $a && $a->status === 'submitted';
                            $topics = $topicMap[$exam->id] ?? [];
                            $topicLabel = count($topics) === 1 ? $topics[0] : (count($topics) > 1 ? 'Mixed' : ($exam->topic?->title ?? 'Mixed'));
                        @endphp
                        <tr>
                            <td style="color: var(--text-faint); font-weight: 600;">{{ $loop->iteration }}</td>
                            <td>
                                <div style="font-weight: 600; font-size: 13.5px;">{{ $exam->title }}</div>
                                <div style="font-size: 11.5px; color: var(--text-faint); margin-top: 2px;">{{ $exam->created_at->diffForHumans() }}</div>
                            </td>
                            <td>
                                <button type="button" class="ex-chip badge badge-emerald"
                                        @click="tTitle = @js($exam->title); tTopics = @js($examTopics[$exam->id] ?? []); tScored = @js($done && $exam->resultsReleased()); tOpen = true"
                                        title="View topics">
                                    {{ $topicLabel }}@if (count($topics) > 1) <span style="opacity:.8;">· {{ count($topics) }}</span>@endif
                                    <x-icon name="list" size="11"/>
                                </button>
                            </td>
                            <td>{{ $exam->question_count }}@if ($exam->duration_minutes)<span style="color: var(--text-faint);"> · {{ $exam->duration_minutes }} min</span>@endif</td>
                            <td>
                                @if ($done && $exam->resultsReleased())
                                    <span class="badge badge-pass">Completed</span>
                                @elseif ($done)
                                    <span class="badge badge-soft" title="Your teacher hasn't released results yet">Awaiting results</span>
                                @elseif ($exam->isScheduled())
                                    <span class="badge badge-review">Scheduled</span>
                                    <div style="font-size: 11px; color: var(--text-faint); margin-top: 3px;">opens {{ $exam->available_from->diffForHumans() }}</div>
                                @elseif ($exam->isExpired())
                                    <span class="badge badge-blocker">Missed</span>
                                @elseif ($a)
                                    <span class="badge badge-review">In progress</span>
                                @else
                                    <span class="badge badge-pass">Open</span>
                                    @if ($exam->available_until)<div style="font-size: 11px; color: var(--text-faint); margin-top: 3px;">closes {{ $exam->available_until->diffForHumans() }}</div>@endif
                                @endif
                            </td>
                            <td>
                                @if ($done && $exam->resultsReleased())
                                    <span style="font-weight: 700; color: {{ $barColor($a->percentage) }};">{{ $a->percentage }}%</span>
                                    <span style="font-size: 11.5px; color: var(--text-faint);"> {{ $a->score }}/{{ $a->total_questions }}</span>
                                @else
                                    <span style="color: var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                @if ($done && $exam->resultsReleased())
                                    <a href="{{ route('v2.student.exams.result', $exam) }}" class="btn btn-ghost btn-sm">View result</a>
                                @elseif ($a && ! $done && $exam->isLive())
                                    <a href="{{ route('v2.student.exams.take', $exam) }}" class="btn btn-primary btn-sm">Continue <x-icon name="chev-r" size="13"/></a>
                                @elseif (! $done && ! $exam->isScheduled() && ! $exam->isExpired())
                                    <a href="{{ route('v2.student.exams.take', $exam) }}" class="btn btn-primary btn-sm"><x-icon name="play" size="12"/> Start</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
        No exams assigned yet. Your teacher's tests will show up here.
    </div>
@endforelse

    {{-- Topics modal (per-exam list) - teleported so the content column's transform can't trap it --}}
    <template x-teleport="body">
    <div x-show="tOpen" x-cloak class="tx-modal" @keydown.escape.window="tOpen = false" style="display:none;">
        <div @click="tOpen = false" style="position: absolute; inset: 0; background: rgba(0,0,0,.5);"></div>
        <div x-show="tOpen" x-transition
             style="position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 24px; width: 100%; max-width: 480px; box-shadow: 0 24px 64px rgba(0,0,0,.35);">
            <div class="flex items-center justify-between" style="margin-bottom: 4px;">
                <h3 class="serif" style="font-size: 17px; font-weight: 600;" x-text="tScored ? 'Your performance by topic' : 'Topics covered'"></h3>
                <button type="button" @click="tOpen = false" style="border: 0; background: none; cursor: pointer; color: var(--text-soft); padding: 4px;"><x-icon name="x" size="16"/></button>
            </div>
            <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 16px;" x-text="tTitle"></p>
            <div style="max-height: 60vh; overflow-y: auto; padding-right: 4px;">
                <template x-for="(t, i) in tTopics" :key="i">
                    <div style="margin-bottom: 14px;">
                        <div class="flex items-center justify-between" style="font-size: 13px; margin-bottom: 6px; gap: 10px;">
                            <span style="font-weight: 500;" x-text="t.topic"></span>
                            <span style="flex: none; color: var(--text-soft);" x-show="t.attempted" x-text="t.correct + '/' + t.total + ' · ' + t.percent + '%'"></span>
                        </div>
                        <div style="height: 8px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;"
                             :style="t.attempted ? {} : { border: '1px dashed var(--border)' }">
                            <div x-show="t.attempted" style="height: 100%; border-radius: 99px;"
                                 :style="{ width: t.percent + '%', background: t.percent >= 60 ? 'var(--ok)' : (t.percent >= 40 ? 'var(--warn)' : 'var(--bad)') }"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </template>

    {{-- Subject performance-by-topic modal (bars) - teleported --}}
    <template x-teleport="body">
    <div x-show="perfOpen" x-cloak class="tx-modal" @keydown.escape.window="perfOpen = false" style="display:none;">
        <div @click="perfOpen = false" style="position: absolute; inset: 0; background: rgba(0,0,0,.5);"></div>
        <div x-show="perfOpen" x-transition
             style="position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 24px; width: 100%; max-width: 560px; box-shadow: 0 24px 64px rgba(0,0,0,.35);">
            <div class="flex items-center justify-between" style="margin-bottom: 4px;">
                <h3 class="serif" style="font-size: 18px; font-weight: 600;">Your performance by topic</h3>
                <button type="button" @click="perfOpen = false" style="border: 0; background: none; cursor: pointer; color: var(--text-soft); padding: 4px;"><x-icon name="x" size="16"/></button>
            </div>
            <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 16px;" x-text="perfTitle"></p>
            <div style="max-height: 60vh; overflow-y: auto; padding-right: 4px;">
                <template x-for="(t, i) in perfTopics" :key="i">
                    <div style="margin-bottom: 14px;">
                        <div class="flex items-center justify-between" style="font-size: 13px; margin-bottom: 6px; gap: 10px;">
                            <span style="font-weight: 500;" :style="t.attempted ? '' : 'color: var(--text-faint);'" x-text="t.topic"></span>
                            <span style="flex: none; color: var(--text-soft);" x-show="t.attempted" x-text="t.correct + '/' + t.total + ' · ' + t.percent + '%'"></span>
                            <span style="flex: none; color: var(--text-faint); font-size: 11px;" x-show="!t.attempted" x-text="t.examined ? 'examined · not attempted' : 'not yet examined'"></span>
                        </div>
                        <div style="height: 8px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;"
                             :style="t.attempted ? {} : { border: '1px dashed var(--border)', opacity: t.examined ? 1 : 0.5 }">
                            <div x-show="t.attempted" style="height: 100%; border-radius: 99px;"
                                 :style="{ width: t.percent + '%', background: t.percent >= 60 ? 'var(--ok)' : (t.percent >= 40 ? 'var(--warn)' : 'var(--bad)') }"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </template>
</div>
@endsection
