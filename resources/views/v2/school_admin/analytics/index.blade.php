@extends('v2.layouts.school_admin')
@section('page_title', 'Analytics')

@php $tone = fn ($p) => $p >= 60 ? 'var(--emerald-700)' : ($p >= 40 ? 'var(--accent)' : '#ef4444'); @endphp

@section('content')
<div style="margin-bottom: 22px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">School Analytics</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Whole-school performance — drill into any teacher, class, or student.</p>
</div>

{{-- Totals --}}
<div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 24px;">
    @foreach ([
        ['Students', $overview['students'], 'users'],
        ['Teachers', $overview['teachers'], 'user'],
        ['Classes', $overview['classes'], 'calendar'],
        ['Exams', $overview['exams'], 'clipboard'],
        ['School average', $overview['avg'] !== null ? $overview['avg'].'%' : '—', 'chart'],
    ] as [$label, $value, $icon])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">
                <x-icon :name="$icon" size="13"/> {{ $label }}
            </div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- School per-topic --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 22px;">
    <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Performance by topic — whole school</div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats])
</div>

<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
    {{-- Per teacher --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Teachers</div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead><tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Teacher', 'Classes', 'Exams', 'Avg', ''] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($teachers as $t)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $t['name'] }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px;">{{ $t['classes'] }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px;">{{ $t['exams'] }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 600; color: {{ $t['avg'] !== null ? $tone($t['avg']) : 'var(--text-faint)' }};">{{ $t['avg'] !== null ? $t['avg'].'%' : '—' }}</td>
                        <td style="padding: var(--pad-cell); text-align: right;"><a href="{{ route('v2.school.analytics.teacher', $t['id']) }}" class="btn btn-ghost btn-sm">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No teachers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Per class --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Classes</div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead><tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Class', 'Students', 'Exams', 'Avg', ''] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($classes as $c)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell);">
                            <div style="font-size: 13px; font-weight: 500;">{{ $c['name'] }}</div>
                            <div style="font-size: 11px; color: var(--text-faint);">{{ $c['teacher'] }}</div>
                        </td>
                        <td style="padding: var(--pad-cell); font-size: 13px;">{{ $c['students'] }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px;">{{ $c['exams'] }}</td>
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 600; color: {{ $c['avg'] !== null ? $tone($c['avg']) : 'var(--text-faint)' }};">{{ $c['avg'] !== null ? $c['avg'].'%' : '—' }}</td>
                        <td style="padding: var(--pad-cell); text-align: right;"><a href="{{ route('v2.school.analytics.class', $c['id']) }}" class="btn btn-ghost btn-sm">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No classes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
