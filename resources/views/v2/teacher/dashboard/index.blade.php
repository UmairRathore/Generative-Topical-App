@extends('v2.layouts.teacher')
@section('page_title', 'Dashboard')

@php $tone = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp

@section('content')
<div style="margin-bottom: 24px;">
    <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Teacher Dashboard</div>
    <h2 class="serif" style="font-size: 30px; font-weight: 600; margin-top: 4px;">Welcome, {{ $teacher?->name }}</h2>
    <p style="color: var(--text-soft); margin-top: 2px; font-size: 14px;">{{ $teacher?->school?->name ?? 'Your School' }}</p>
</div>

{{-- Totals --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px;">
    @foreach ([
        ['Classes', $overview['classes'], 'users'],
        ['Students', $overview['students'], 'user'],
        ['Exams', $overview['exams'], 'clipboard'],
        ['Average', $overview['avg'] !== null ? $overview['avg'].'%' : '-', 'chart'],
    ] as [$label, $value, $icon])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">
                <x-icon :name="$icon" size="13"/> {{ $label }}
            </div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
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

<div class="grid" style="grid-template-columns: 1fr 1.2fr; gap: 20px; align-items: start;">
    {{-- Topic performance --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px;">Performance by topic</div>
        <div style="font-size: 12px; color: var(--text-faint); margin-bottom: 14px;">Across your exams.</div>
        @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions yet - analytics appear once your students complete tests.'])
    </div>

    {{-- Classes --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Your classes</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['Class', 'Grade', 'Students', 'Avg', ''] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : ($loop->last ? 'right' : 'center') }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $c)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.teacher.classes.show', hid($c['id'])) }}'">
                        <td data-label="Class" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $c['name'] }}<div style="font-size: 11px; color: var(--text-faint);">{{ $c['subject'] }}</div></td>
                        <td data-label="Grade" style="padding: var(--pad-cell); text-align: center; font-size: 12.5px; color: var(--text-soft);">{{ $c['grade'] ?? '-' }}</td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $c['students'] }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px; color: {{ $c['avg'] !== null ? $tone($c['avg']) : 'var(--text-faint)' }};">{{ $c['avg'] !== null ? $c['avg'].'%' : '-' }}</td>
                        <td data-label="" style="padding: var(--pad-cell); text-align: right;"><a href="{{ route('v2.teacher.classes.show', hid($c['id'])) }}" class="btn btn-ghost btn-sm">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No classes assigned yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
