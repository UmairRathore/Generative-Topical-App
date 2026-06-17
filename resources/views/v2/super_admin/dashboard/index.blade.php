@extends('v2.layouts.super_admin')
@section('page_title', 'Dashboard')

@section('content')
@php $user = auth('v2_super_admin')->user(); @endphp

<div style="margin-bottom: 24px;">
    <div class="uppercase-eyebrow" style="color: var(--text-faint); font-size: 11px;">Platform Overview</div>
    <h2 class="serif" style="font-size: 32px; font-weight: 600; margin-top: 4px;">Good day, {{ $user?->name }}</h2>
    <p style="color: var(--text-soft); margin-top: 4px; font-size: 14px;">Here's a summary of the TopicalEd platform.</p>
</div>

{{-- Stat cards --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px;">
    @foreach([
        ['Schools', number_format($stats['schools']), 'school', $stats['active'].' active · '.$stats['suspended'].' suspended'],
        ['Students', number_format($stats['students']), 'users', 'Across all schools'],
        ['Teachers', number_format($stats['teachers']), 'user', $stats['exams'].' exams · '.$stats['submissions'].' submissions'],
        ['MRR', 'Rs '.number_format($stats['mrr']), 'trending', 'Monthly recurring revenue'],
    ] as [$label, $value, $icon, $sub])
        <div style="padding: var(--pad-card); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
            <div class="flex items-start justify-between">
                <div style="min-width: 0;">
                    <div style="font-size: 12px; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600;">{{ $label }}</div>
                    <div class="serif" style="font-size: 30px; font-weight: 600; margin-top: 4px; color: var(--text);">{{ $value }}</div>
                    <div style="font-size: 12px; color: var(--text-soft); margin-top: 2px;">{{ $sub }}</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 10px; flex: none; background: var(--emerald-900); display: flex; align-items: center; justify-content: center;">
                    <x-icon :name="$icon" size="18" style="color: var(--accent);"/>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="grid" style="grid-template-columns: 1.6fr 1fr; gap: 20px; align-items: start;">

    {{-- Schools --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
        <div class="flex items-center justify-between" style="padding: 14px 18px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 14px; font-weight: 600;">Schools</h3>
            <a href="{{ route('v2.super_admin.schools.index') }}" style="font-size: 12.5px; color: var(--gold-700); text-decoration: none; font-weight: 600;">View all →</a>
        </div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['School', 'Status', 'Students', 'Subs', 'Avg'] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->first ? 'left' : ($loop->last ? 'right' : 'center') }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($schools as $s)
                    <tr style="border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location='{{ route('v2.super_admin.schools.show', $s['id']) }}'">
                        <td data-label="School" style="padding: var(--pad-cell);">
                            <div style="font-size: 13px; font-weight: 600;">{{ $s['name'] }}</div>
                            <div style="font-size: 11.5px; color: var(--text-faint);">{{ $s['city'] }} · {{ str_replace('_', ' ', $s['tier']) }}</div>
                        </td>
                        <td data-label="Status" style="padding: var(--pad-cell); text-align: center;">
                            <span class="badge {{ $s['status'] === 'active' ? 'badge-pass' : ($s['status'] === 'suspended' ? 'badge-blocker' : 'badge-soft') }}">{{ $s['status'] }}</span>
                        </td>
                        <td data-label="Students" style="padding: var(--pad-cell); text-align: center; font-size: 13px;">{{ $s['students'] }}</td>
                        <td data-label="Subs" style="padding: var(--pad-cell); text-align: center; font-size: 13px; color: var(--text-soft);">{{ $s['subs'] ?: '—' }}</td>
                        <td data-label="Avg" style="padding: var(--pad-cell); text-align: right; font-weight: 600; font-size: 13px;">{{ $s['avg'] !== null ? $s['avg'].'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 36px; text-align: center; color: var(--text-faint); font-size: 13px;">No schools yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent activity --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px;">
        <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Recent activity</h3>
        @forelse ($recentLogs as $log)
            <div class="flex items-start gap-3" style="padding: 9px 0; border-bottom: 1px solid var(--border);">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--emerald-500); margin-top: 5px; flex: none;"></div>
                <div style="min-width: 0;">
                    <div style="font-size: 12.5px; color: var(--text);">{{ $log->action }}</div>
                    <div style="font-size: 11px; color: var(--text-faint);">{{ $log->actor_type }} · {{ $log->created_at?->diffForHumans() ?? '—' }}</div>
                </div>
            </div>
        @empty
            <p style="font-size: 13px; color: var(--text-faint);">No activity recorded yet.</p>
        @endforelse
    </div>
</div>

{{-- Cross-school rollups --}}
@php $tone = fn ($p) => $p >= 60 ? 'var(--emerald-700)' : ($p >= 40 ? 'var(--accent)' : '#ef4444'); @endphp
<div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; align-items: start;">

    {{-- By grade --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px;">
        <div class="flex items-center justify-between" style="margin-bottom: 10px;">
            <h3 style="font-size: 14px; font-weight: 600;">By grade</h3>
            <a href="{{ route('v2.super_admin.grades.index') }}" style="font-size: 12px; color: var(--gold-700); text-decoration: none; font-weight: 600;">View all →</a>
        </div>
        @forelse ($gradeRollup as $r)
            <div class="flex items-center justify-between" style="padding: 8px 0; border-bottom: 1px solid var(--border);">
                <div style="min-width: 0;">
                    <div style="font-size: 12.5px; font-weight: 500;">{{ $r['grade'] }}</div>
                    <div style="font-size: 11px; color: var(--text-faint);">{{ $r['schools'] }} {{ \Illuminate\Support\Str::plural('school', $r['schools']) }} · {{ $r['submissions'] }} subs</div>
                </div>
                <span style="font-size: 13px; font-weight: 700; color: {{ $r['avg'] !== null ? $tone($r['avg']) : 'var(--text-faint)' }};">{{ $r['avg'] !== null ? $r['avg'].'%' : '—' }}</span>
            </div>
        @empty
            <p style="font-size: 12.5px; color: var(--text-faint);">No data yet.</p>
        @endforelse
    </div>

    {{-- By subject --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px;">
        <div class="flex items-center justify-between" style="margin-bottom: 10px;">
            <h3 style="font-size: 14px; font-weight: 600;">By subject</h3>
            <a href="{{ route('v2.super_admin.subjects.index') }}" style="font-size: 12px; color: var(--gold-700); text-decoration: none; font-weight: 600;">View all →</a>
        </div>
        @forelse ($subjectRollup as $r)
            <a href="{{ route('v2.super_admin.subjects.show', $r['id']) }}" class="flex items-center justify-between" style="padding: 8px 0; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit;">
                <div style="min-width: 0;">
                    <div style="font-size: 12.5px; font-weight: 500;">{{ $r['subject'] }}</div>
                    <div style="font-size: 11px; color: var(--text-faint);">{{ $r['schools'] }} {{ \Illuminate\Support\Str::plural('school', $r['schools']) }} · {{ $r['submissions'] }} subs</div>
                </div>
                <span style="font-size: 13px; font-weight: 700; color: {{ $r['avg'] !== null ? $tone($r['avg']) : 'var(--text-faint)' }};">{{ $r['avg'] !== null ? $r['avg'].'%' : '—' }}</span>
            </a>
        @empty
            <p style="font-size: 12.5px; color: var(--text-faint);">No data yet.</p>
        @endforelse
    </div>

    {{-- By topic --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px;">
        <div class="flex items-center justify-between" style="margin-bottom: 14px;">
            <h3 style="font-size: 14px; font-weight: 600;">By topic</h3>
            <a href="{{ route('v2.super_admin.topics.index') }}" style="font-size: 12px; color: var(--gold-700); text-decoration: none; font-weight: 600;">View all →</a>
        </div>
        @include('v2.partials.topic_bars', ['stats' => $topicRollup, 'empty' => 'No submissions yet.'])
    </div>
</div>
@endsection
