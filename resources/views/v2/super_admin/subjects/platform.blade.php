@extends('v2.layouts.super_admin')
@section('page_title', 'Subjects')

@section('content')
<style>
    .subj-toggle{display:inline-flex;align-items:center;gap:7px;padding:5px 11px 5px 8px;border:1px solid var(--border);border-radius:99px;font-size:12px;font-weight:600;cursor:pointer;background:var(--bg);transition:all .12s;}
    .subj-toggle .knob{width:26px;height:15px;border-radius:99px;position:relative;background:var(--border);transition:background .12s;}
    .subj-toggle .knob::after{content:'';position:absolute;top:2px;left:2px;width:11px;height:11px;border-radius:50%;background:#fff;transition:left .12s;box-shadow:0 1px 2px rgba(0,0,0,.3);}
    .subj-toggle.on{color:var(--ok);border-color:var(--ok);}
    .subj-toggle.on .knob{background:var(--ok);}
    .subj-toggle.on .knob::after{left:13px;}
    .subj-toggle.off{color:var(--text-faint);}
    tr.is-off td{opacity:.55;}
    tr.is-off td.keep{opacity:1;}
</style>

<h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 3px;">Subjects</h2>
<p style="color: var(--text-soft); font-size: 13px; margin-bottom: 18px;">Turn a subject on or off platform-wide. Off subjects keep all their questions and history but are no longer offered to schools, teachers or students. <strong>{{ $activeCount }}</strong> of {{ $subjects->count() }} active.</p>

@if (session('success'))
    <div style="margin-bottom: 16px; padding: 11px 16px; background: var(--ok-soft); border: 1px solid var(--ok); border-radius: 8px; font-size: 13px; color: var(--ok); font-weight: 600;">{{ session('success') }}</div>
@endif

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table class="tbl" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                @foreach (['Subject', 'Level', 'Questions', 'Schools', 'Avg', 'Status'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ in_array($h, ['Subject','Level']) ? 'left' : 'center' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $s)
                <tr class="{{ $s['is_active'] ? '' : 'is-off' }}" style="border-bottom: 1px solid var(--border); cursor: pointer;"
                    onclick="window.location='{{ route('v2.super_admin.subjects.show', hid($s['id'])) }}'">
                    <td data-label="Subject" style="padding: var(--pad-cell);">
                        <span style="font-size: 13px; font-weight: 600;">{{ $s['name'] }}</span>
                        <span class="badge badge-soft" style="margin-left: 6px; font-size: 10px;">{{ $s['code'] }}</span>
                    </td>
                    <td data-label="Level" style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ $s['level'] ?? '-' }}</td>
                    <td data-label="Questions" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ number_format($s['questions']) }}</td>
                    <td data-label="Schools" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $s['schools'] }}</td>
                    <td data-label="Avg" style="padding: var(--pad-cell); text-align: center; font-size: 13px; font-weight: 600;">{{ $s['avg'] !== null ? $s['avg'].'%' : '-' }}</td>
                    <td data-label="Status" class="keep" style="padding: var(--pad-cell); text-align: center;" onclick="event.stopPropagation()">
                        <form method="POST" action="{{ route('v2.super_admin.subjects.toggle', hid($s['id'])) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <button type="submit" class="subj-toggle {{ $s['is_active'] ? 'on' : 'off' }}" title="{{ $s['is_active'] ? 'Turn off' : 'Turn on' }}">
                                <span class="knob"></span>{{ $s['is_active'] ? 'On' : 'Off' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding: 40px; text-align: center; color: var(--text-faint); font-size: 14px;">No subjects yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
