@extends('v2.layouts.super_admin')
@section('page_title', 'Subjects — platform')

@section('content')
<a href="{{ route('v2.super_admin.dashboard') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> Dashboard
</a>

<h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 3px;">Subjects across all schools</h2>
<p style="color: var(--text-soft); font-size: 13px; margin-bottom: 22px;">Aggregated by subject. Click a subject for its platform-wide topic breakdown.</p>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table class="tbl" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Subject', 'Schools', 'Exams', 'Subs', 'Avg'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.subjects.show', $r['id']) }}'">
                    <td data-label="Subject" style="padding: var(--pad-cell); font-size: 13px; font-weight: 600;">{{ $r['subject'] }}</td>
                    <td data-label="Schools" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $r['schools'] }}</td>
                    <td data-label="Exams" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $r['exams'] }}</td>
                    <td data-label="Subs" style="padding: var(--pad-cell); text-align: center; font-size: 13px; color: var(--text-soft);">{{ $r['submissions'] ?: '—' }}</td>
                    <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-weight: 600; font-size: 13px;">{{ $r['avg'] !== null ? $r['avg'].'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding: 40px; text-align: center; color: var(--text-faint); font-size: 14px;">No subjects yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
