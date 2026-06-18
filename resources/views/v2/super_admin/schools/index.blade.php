@extends('v2.layouts.super_admin')
@section('page_title', 'Schools')

@section('content')
<div class="flex items-center justify-between" style="margin-bottom: 22px;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">Schools</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">
            {{ $schools->count() }} {{ \Illuminate\Support\Str::plural('school', $schools->count()) }} · Rs {{ number_format($mrr) }}/mo recurring
        </p>
    </div>
</div>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table class="tbl" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                @foreach (['School', 'Status', 'Tier', 'Fee/mo', 'Teachers', 'Students', 'Classes', 'Subs', 'Avg'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : ($loop->index >= 3 ? 'center' : 'left') }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($schools as $s)
                <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.show', hid($s['id'])) }}'">
                    <td data-label="School" style="padding: var(--pad-cell);">
                        <div style="font-size: 13px; font-weight: 600;">{{ $s['name'] }}</div>
                        <div style="font-size: 11.5px; color: var(--text-faint);">{{ $s['city'] }} · {{ $s['email'] }}</div>
                    </td>
                    <td data-label="Status" style="padding: var(--pad-cell);">
                        <span class="badge {{ $s['status'] === 'active' ? 'badge-pass' : ($s['status'] === 'suspended' ? 'badge-blocker' : 'badge-soft') }}">{{ $s['status'] }}</span>
                    </td>
                    <td data-label="Tier" style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ str_replace('_', ' ', $s['tier']) }}</td>
                    <td data-label="Fee/mo" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">Rs {{ number_format($s['fee']) }}</td>
                    <td data-label="Teachers" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $s['teachers'] }}</td>
                    <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $s['students'] }}</td>
                    <td data-label="Classes" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $s['classes'] }}</td>
                    <td data-label="Subs" style="padding: var(--pad-cell); text-align: center; font-size: 13px; color: var(--text-soft);">{{ $s['subs'] ?: '—' }}</td>
                    <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px;">{{ $s['avg'] !== null ? $s['avg'].'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">No schools yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
