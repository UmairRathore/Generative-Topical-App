@extends('v2.layouts.super_admin')
@section('page_title', $school->name)

@section('content')
<a href="{{ route('v2.super_admin.schools.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> All Schools
</a>

<div class="flex items-start justify-between" style="margin-bottom: 22px;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $school->name }}</h2>
        <div class="flex items-center gap-2" style="margin-top: 6px;">
            <span class="badge {{ $school->status === 'active' ? 'badge-pass' : ($school->status === 'suspended' ? 'badge-blocker' : 'badge-soft') }}">{{ $school->status }}</span>
            <span class="badge badge-soft">{{ str_replace('_', ' ', $school->license_tier) }}</span>
            <span class="badge badge-soft">Rs {{ number_format((int) $school->monthly_fee) }}/mo</span>
            @if ($school->address)<span style="font-size: 12.5px; color: var(--text-faint);">{{ $school->address }}</span>@endif
        </div>
    </div>
</div>

{{-- Break down by dimension --}}
<div class="flex items-center gap-2" style="margin-bottom: 20px; flex-wrap: wrap;">
    <span style="font-size: 12px; color: var(--text-faint); font-weight: 600;">Break down by:</span>
    @foreach (['Grades' => 'schools.grades.index', 'Subjects' => 'schools.subjects.index', 'Topics' => 'schools.topics.index'] as $label => $r)
        <a href="{{ route('v2.super_admin.'.$r, $school) }}" style="padding: 6px 14px; border: 1px solid var(--border); border-radius: 99px; font-size: 12.5px; font-weight: 600; color: var(--gold-700); text-decoration: none; background: var(--surface);">{{ $label }}</a>
    @endforeach
</div>

{{-- Overview --}}
<div class="grid" style="grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 24px;">
    @foreach ([
        ['Students', $overview['students']],
        ['Teachers', $overview['teachers']],
        ['Classes', $overview['classes']],
        ['Exams', $overview['exams']],
        ['Submissions', $overview['submissions']],
        ['Avg score', $overview['avg'] !== null ? $overview['avg'].'%' : '—'],
    ] as [$label, $value])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
            <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 5px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">

    {{-- Teachers --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Teachers</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['Teacher', 'Classes', 'Exams', 'Subs', 'Avg'] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($teachers as $t)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.teachers.show', [$school, $t['id']]) }}'">
                        <td data-label="Teacher" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $t['name'] }}</td>
                        <td data-label="Classes" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $t['classes'] }}</td>
                        <td data-label="Exams" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $t['exams'] }}</td>
                        <td data-label="Subs" style="padding: var(--pad-cell); text-align: center; font-size: 13px; color: var(--text-soft);">{{ $t['submissions'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px;">{{ $t['avg'] !== null ? $t['avg'].'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No teachers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Classes --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Classes</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['Class', 'Teacher', 'Students', 'Avg'] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $c)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.classes.show', [$school, $c['id']]) }}'">
                        <td data-label="Class" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $c['name'] }}<div style="font-size: 11px; color: var(--text-faint);">{{ $c['grade'] }}</div></td>
                        <td data-label="Teacher" style="padding: var(--pad-cell); text-align: center; font-size: 12.5px; color: var(--text-soft);">{{ $c['teacher'] }}</td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $c['students'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px;">{{ $c['avg'] !== null ? $c['avg'].'%' : '—' }}</td>
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
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Performance by topic (school-wide)</div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions yet — topic stats appear once students complete tests.'])
</div>
@endsection
