@extends('v2.layouts.school_admin')
@section('page_title', 'Teacher Analytics')

@php $tone = fn ($p) => $p >= 60 ? 'var(--emerald-700)' : ($p >= 40 ? 'var(--accent)' : '#ef4444'); @endphp

@section('content')
<a href="{{ route('v2.school.analytics.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Analytics
</a>

<div style="margin-bottom: 20px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $teacher->name }}</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">{{ $teacher->email }} · Teacher</p>
</div>

<div class="grid" style="grid-template-columns: 1fr 1.3fr; gap: 20px; align-items: start;">
    {{-- Teacher teaching-by-topic --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Teaching performance by topic</div>
        <p style="font-size: 11.5px; color: var(--text-faint); margin-top: -8px; margin-bottom: 14px;">How this teacher's students perform across topics.</p>
        @include('v2.partials.topic_bars', ['stats' => $topicStats])
    </div>

    {{-- Teacher's classes --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Classes taught</div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead><tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Class', 'Grade', 'Students', 'Exams', ''] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($classes as $c)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $c->name }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px;">{{ $c->grade?->name }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px;">{{ $c->student_count }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px;">{{ $examCounts[$c->id] ?? 0 }}</td>
                        <td style="padding: var(--pad-cell); text-align: right;"><a href="{{ route('v2.school.analytics.class', $c->id) }}" class="btn btn-ghost btn-sm">View class</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No classes assigned.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
