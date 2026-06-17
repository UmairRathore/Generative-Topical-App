@extends('v2.layouts.super_admin')
@section('page_title', $subject->name)

@section('content')
@if ($school)
    <a href="{{ route('v2.super_admin.schools.subjects.index', $school) }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
        <x-icon name="chev-l" size="12"/> Subjects
    </a>
@else
    <a href="{{ route('v2.super_admin.subjects.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
        <x-icon name="chev-l" size="12"/> All subjects
    </a>
@endif

<h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $subject->name }}</h2>
<p style="color: var(--text-soft); font-size: 13px; margin-top: 2px; margin-bottom: 20px;">{{ $school?->name ?? 'Across all schools' }}</p>

{{-- KPIs --}}
<div class="grid" style="grid-template-columns: repeat({{ $school ? 5 : 4 }}, 1fr); gap: 12px; margin-bottom: 24px;">
    @php
        $cards = $school ? [
            ['Classes', $summary['classes'] ?? 0],
            ['Students', $summary['students'] ?? 0],
            ['Exams', $summary['exams'] ?? 0],
            ['Submissions', $summary['submissions'] ?? 0],
            ['Avg score', isset($summary['avg']) && $summary['avg'] !== null ? $summary['avg'].'%' : '—'],
        ] : [
            ['Schools', $summary['schools'] ?? 0],
            ['Exams', $summary['exams'] ?? 0],
            ['Submissions', $summary['submissions'] ?? 0],
            ['Avg score', isset($summary['avg']) && $summary['avg'] !== null ? $summary['avg'].'%' : '—'],
        ];
    @endphp
    @foreach ($cards as [$label, $value])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
            <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 5px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- Topic performance --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Performance by topic{{ $school ? '' : ' (all schools)' }}</div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions in this subject yet.'])
</div>

{{-- Classes (school context only) --}}
@if ($school && count($classes))
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Classes</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['Class', 'Grade', 'Teacher', 'Students', 'Avg'] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($classes as $c)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.classes.show', [$school, $c['id']]) }}'">
                        <td data-label="Class" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $c['name'] }}</td>
                        <td data-label="Grade" style="padding: var(--pad-cell); text-align: center; font-size: 12.5px; color: var(--text-soft);">{{ $c['grade'] ?? '—' }}</td>
                        <td data-label="Teacher" style="padding: var(--pad-cell); text-align: center; font-size: 12.5px; color: var(--text-soft);">{{ $c['teacher'] }}</td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $c['students'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px;">{{ $c['avg'] !== null ? $c['avg'].'%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
