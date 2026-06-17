@extends('v2.layouts.super_admin')
@section('page_title', $teacher->name)

@section('content')
<a href="{{ route('v2.super_admin.schools.show', $school) }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> {{ $school->name }}
</a>

<h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $teacher->name }}</h2>
<p style="color: var(--text-soft); font-size: 13px; margin-top: 2px; margin-bottom: 22px;">{{ $teacher->email }} · Teacher</p>

<div class="grid" style="grid-template-columns: 1fr 1.3fr; gap: 20px; align-items: start;">
    {{-- Topic performance --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 4px;">Teaching performance by topic</div>
        <div style="font-size: 12px; color: var(--text-faint); margin-bottom: 14px;">Across this teacher's exams.</div>
        @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions yet.'])
    </div>

    {{-- Classes taught --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Classes taught</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['Class', 'Grade', 'Students', 'Exams'] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $c)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.classes.show', [$school, $c->id]) }}'">
                        <td data-label="Class" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $c->name }}<div style="font-size: 11px; color: var(--text-faint);">{{ $c->subject?->name }}</div></td>
                        <td data-label="Grade" style="padding: var(--pad-cell); text-align: center; font-size: 12.5px; color: var(--text-soft);">{{ $c->grade?->name ?? '—' }}</td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $c->student_count }}</td>
                        <td data-label="Exams" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $examCounts[$c->id] ?? 0 }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No classes assigned.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
