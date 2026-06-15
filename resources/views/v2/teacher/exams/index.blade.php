@extends('v2.layouts.teacher')
@section('page_title', 'Exams')

@section('content')
<div class="flex items-center justify-between" style="margin-bottom: 24px;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">Exams</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Generate topic tests for your classes and review results.</p>
    </div>
    <a href="{{ route('v2.teacher.exams.create') }}" class="btn btn-primary"><x-icon name="plus" size="14"/> Create Exam</a>
</div>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                @foreach (['Test', 'Class', 'Topic', 'Questions', 'Submitted', 'Avg', ''] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($exams as $exam)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: var(--pad-cell);">
                        <div style="font-size: 14px; font-weight: 600;">{{ $exam->title }}</div>
                        <div style="font-size: 11.5px; color: var(--text-faint);">{{ $exam->created_at->diffForHumans() }}</div>
                    </td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $exam->schoolClass?->name }}</td>
                    <td style="padding: var(--pad-cell);">
                        <span class="badge badge-emerald">{{ $exam->topic?->title ?? 'Mixed' }}</span>
                    </td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $exam->question_count }}</td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">
                        <span style="font-weight: 600;">{{ $exam->submitted_count }}</span>
                        <span style="color: var(--text-faint);"> / {{ $exam->schoolClass?->student_count ?? 0 }} students</span>
                    </td>
                    @php $avg = ($exam->avg_score !== null && $exam->question_count) ? round($exam->avg_score / $exam->question_count * 100) : null; @endphp
                    <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 600;">{{ $avg !== null ? $avg.'%' : '—' }}</td>
                    <td style="padding: var(--pad-cell); text-align: right;">
                        <a href="{{ route('v2.teacher.exams.show', $exam) }}" class="btn btn-ghost btn-sm">View results <x-icon name="chev-r" size="13"/></a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">
                        No exams yet. <a href="{{ route('v2.teacher.exams.create') }}" style="color: var(--gold-700); font-weight: 600; text-decoration: none;">Create your first test →</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
