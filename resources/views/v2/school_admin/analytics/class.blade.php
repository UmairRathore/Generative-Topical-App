@extends('v2.layouts.school_admin')
@section('page_title', 'Class Analytics')

@php
    $totC = array_sum(array_column($topicStats, 'correct'));
    $totT = array_sum(array_column($topicStats, 'total'));
    $classAvg = $totT ? (int) round($totC / $totT * 100) : null;
@endphp

@section('content')
<a href="{{ route('v2.school.analytics.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Analytics
</a>

<div style="margin-bottom: 20px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $class->name }}</h2>
    <div class="flex items-center gap-2" style="margin-top: 6px;">
        <span class="badge badge-soft">{{ $class->grade?->name }}</span>
        <span class="badge badge-emerald">{{ $class->subject?->name }}</span>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
    @foreach ([
        ['Students', $studentCount, 'users'],
        ['Exams', $exams->count(), 'clipboard'],
        ['Class average', $classAvg !== null ? $classAvg.'%' : '—', 'chart'],
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

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
    <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Class performance by topic</div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats])
</div>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Per-student, per-topic</div>
    @include('v2.partials.student_matrix', ['matrix' => $matrix, 'studentRoute' => 'v2.school.analytics.student'])
</div>
@endsection
