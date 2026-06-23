@extends('v2.layouts.super_admin')
@section('page_title', $class->name)

@php
    $tc = array_sum(array_column($topicStats, 'total'));
    $cc = array_sum(array_column($topicStats, 'correct'));
    $classAvg = $tc ? (int) round($cc / $tc * 100) : null;
    $studentCount = count($matrix['rows']);
    $examCount = $split['single']['exams'] + $split['mixed']['exams'];
@endphp

@section('content')
<a href="{{ route('v2.super_admin.schools.branches.show', [$school, $branch]) }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> {{ $school->name }} · {{ $branch->name }}
</a>

<div class="flex items-center gap-2" style="margin-bottom: 4px; flex-wrap: wrap;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $class->name }}</h2>
    @if ($class->grade)<span class="badge badge-soft">{{ $class->grade->name }}</span>@endif
    @if ($class->subject)<span class="badge badge-soft">{{ $class->subject->name }}</span>@endif
</div>
<p style="color: var(--text-soft); font-size: 13px; margin-bottom: 22px;">{{ $school->name }}</p>

{{-- KPIs --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 22px;">
    @foreach ([
        ['Students', $studentCount],
        ['Exams', $examCount],
        ['Class average', $classAvg !== null ? $classAvg.'%' : '-'],
        ['Topics covered', count($topicStats)],
    ] as [$label, $value])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
            <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 5px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- Single vs mixed split --}}
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 22px;">
    @foreach ([['Single-topic exams', $split['single']], ['Mixed exams', $split['mixed']]] as [$label, $s])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
            <div class="flex items-baseline gap-2" style="margin-top: 6px;">
                <span class="serif" style="font-size: 22px; font-weight: 600;">{{ $s['avg'] !== null ? $s['avg'].'%' : '-' }}</span>
                <span style="font-size: 12.5px; color: var(--text-soft);">{{ $s['exams'] }} {{ \Illuminate\Support\Str::plural('exam', $s['exams']) }} · {{ $s['submissions'] }} subs</span>
            </div>
        </div>
    @endforeach
</div>

{{-- Topic performance --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Class performance by topic</div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions in this class yet.'])
</div>

{{-- Per-student × per-topic matrix --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Per-student, per-topic</div>
    @include('v2.partials.student_matrix', ['matrix' => $matrix, 'studentRoute' => 'v2.super_admin.schools.branches.students.show', 'studentRouteParams' => [$school, $branch]])
</div>
@endsection
