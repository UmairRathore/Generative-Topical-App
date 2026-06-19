@extends('v2.layouts.teacher')
@section('page_title', 'Classes')

@section('content')
<div style="margin-bottom: 22px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">Classes</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Class analytics - per-topic performance and how each student is doing.</p>
</div>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                @foreach (['Class', 'Grade', 'Subject', 'Students', 'Exams', ''] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($classes as $class)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: var(--pad-cell); font-size: 14px; font-weight: 600;">{{ $class->name }}</td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $class->grade?->name }}</td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $class->subject?->name }}</td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $class->student_count }}</td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $examCounts[$class->id] ?? 0 }}</td>
                    <td style="padding: var(--pad-cell); text-align: right;">
                        <a href="{{ route('v2.teacher.classes.show', $class) }}" class="btn btn-ghost btn-sm">View analytics <x-icon name="chev-r" size="13"/></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">You are not assigned to any class yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
