@extends('v2.layouts.super_admin')
@section('page_title', $branch->name)

@php $tone = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp

@section('content')
<a href="{{ route('v2.super_admin.schools.show', $school) }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> {{ $school->name }}
</a>

<div style="margin-bottom: 22px;">
    <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: var(--text-faint);">Branch</div>
    <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-top: 2px;">{{ $branch->name }}</h2>
</div>

{{-- Overview --}}
<div class="grid" style="grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 24px;">
    @foreach ([
        ['Students', $overview['students']],
        ['Teachers', $overview['teachers']],
        ['Classes', $overview['classes']],
        ['Exams', $overview['exams']],
        ['Submissions', $overview['submissions']],
        ['Avg score', $overview['avg'] !== null ? $overview['avg'].'%' : '-'],
    ] as [$label, $value])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
            <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 5px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- Grades + Subjects --}}
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; margin-bottom: 20px;">
    {{-- Grades --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Grades</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead><tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Grade', 'Students', 'Exams', 'Avg'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($grades as $g)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.branches.grades.show', [$school, $branch, hid($g['id'])]) }}'">
                        <td data-label="Grade" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $g['grade'] }}</td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $g['students'] }}</td>
                        <td data-label="Exams" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $g['exams'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px; color: {{ $g['avg'] !== null ? $tone($g['avg']) : 'var(--text-faint)' }};">{{ $g['avg'] !== null ? $g['avg'].'%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No grades.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Subjects --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Subjects</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead><tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Subject', 'Students', 'Exams', 'Avg'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($subjects as $s)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.branches.subjects.show', [$school, $branch, hid($s['id'])]) }}'">
                        <td data-label="Subject" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $s['subject'] }}</td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $s['students'] }}</td>
                        <td data-label="Exams" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $s['exams'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px; color: {{ $s['avg'] !== null ? $tone($s['avg']) : 'var(--text-faint)' }};">{{ $s['avg'] !== null ? $s['avg'].'%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No subjects.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Teachers + Classes --}}
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
    {{-- Teachers --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Teachers</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead><tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Teacher', 'Classes', 'Exams', 'Avg'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($teachers as $t)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.branches.teachers.show', [$school, $branch, hid($t['id'])]) }}'">
                        <td data-label="Teacher" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $t['name'] }}</td>
                        <td data-label="Classes" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $t['classes'] }}</td>
                        <td data-label="Exams" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $t['exams'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px; color: {{ $t['avg'] !== null ? $tone($t['avg']) : 'var(--text-faint)' }};">{{ $t['avg'] !== null ? $t['avg'].'%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No teachers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Classes --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Classes</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead><tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Class', 'Teacher', 'Students', 'Avg'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($classes as $c)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.branches.classes.show', [$school, $branch, hid($c['id'])]) }}'">
                        <td data-label="Class" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $c['name'] }}<div style="font-size: 11px; color: var(--text-faint);">{{ $c['grade'] }}</div></td>
                        <td data-label="Teacher" style="padding: var(--pad-cell); text-align: center; font-size: 12.5px; color: var(--text-soft);">{{ $c['teacher'] }}</td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $c['students'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px; color: {{ $c['avg'] !== null ? $tone($c['avg']) : 'var(--text-faint)' }};">{{ $c['avg'] !== null ? $c['avg'].'%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No classes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Performance by topic --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-top: 20px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Performance by topic <span style="font-weight:400; color:var(--text-faint);">- this branch</span></div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions yet for this branch.'])
</div>
@endsection
