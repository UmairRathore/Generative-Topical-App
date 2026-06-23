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

{{-- Overview (school-wide) --}}
<div class="grid" style="grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 24px;">
    @foreach ([
        ['Branches', count($branches)],
        ['Students', $overview['students']],
        ['Teachers', $overview['teachers']],
        ['Classes', $overview['classes']],
        ['Exams', $overview['exams']],
        ['Avg score', $overview['avg'] !== null ? $overview['avg'].'%' : '-'],
    ] as [$label, $value])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
            <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 5px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- Branches — drill into a branch to reach its teachers, classes and students --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; margin-bottom: 20px;">
    <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600;">Branches</div>
    <table class="tbl" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Branch', 'Students', 'Teachers', 'Classes', 'Exams', 'Avg'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($branches as $b)
                <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.branches.show', [$school, hid($b['id'])]) }}'">
                    <td data-label="Branch" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $b['name'] }}</td>
                    <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $b['students'] }}</td>
                    <td data-label="Teachers" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $b['teachers'] }}</td>
                    <td data-label="Classes" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $b['classes'] }}</td>
                    <td data-label="Exams" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $b['exams'] }}</td>
                    <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px;">{{ $b['avg'] !== null ? $b['avg'].'%' : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">This school has no branches yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Performance by topic --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Performance by topic <span style="font-weight:400; color:var(--text-faint);">— school-wide</span></div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions yet - topic stats appear once students complete tests.'])
</div>
@endsection
