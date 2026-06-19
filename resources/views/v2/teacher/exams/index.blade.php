@extends('v2.layouts.teacher')
@section('page_title', 'Exams')

@php
    // effectiveStatus -> [badge class, label]
    $stateBadge = [
        'draft'     => ['badge-soft', 'Draft'],
        'scheduled' => ['badge-review', 'Scheduled'],
        'live'      => ['badge-pass', 'Live'],
        'expired'   => ['badge-blocker', 'Expired'],
    ];
@endphp

@section('content')
<style>[x-cloak]{display:none!important}.tx-modal{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px}</style>

<div class="flex items-center justify-between" style="margin-bottom: 24px;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">Exams</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Generate topic tests, then release them to students — now or at a scheduled time, with an optional expiry.</p>
    </div>
    <a href="{{ route('v2.teacher.exams.create') }}" class="btn btn-primary"><x-icon name="plus" size="14"/> Create Exam</a>
</div>

@if (session('success'))
    <div style="margin-bottom: 16px; padding: 11px 16px; background: rgba(var(--ok-rgb,95,160,82),.12); border: 1px solid var(--ok); border-radius: 8px; font-size: 13px; color: var(--ok); font-weight: 600;">{{ session('success') }}</div>
@endif

<div x-data="{ relOpen: false, relAction: '', relTitle: '', mode: 'now' }">
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                @foreach (['Test', 'Class', 'Topic', 'Status', 'Questions', 'Submitted', 'Avg', ''] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($exams as $exam)
                @php [$bClass, $bLabel] = $stateBadge[$exam->effectiveStatus()]; @endphp
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: var(--pad-cell);">
                        <div style="font-size: 14px; font-weight: 600;">{{ $exam->title }}</div>
                        <div style="font-size: 11.5px; color: var(--text-faint);">{{ $exam->created_at->diffForHumans() }}</div>
                    </td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $exam->schoolClass?->name }}</td>
                    <td style="padding: var(--pad-cell);">
                        <span class="badge badge-emerald">{{ $exam->topic?->title ?? 'Mixed' }}</span>
                    </td>
                    <td style="padding: var(--pad-cell);">
                        <span class="badge {{ $bClass }}">{{ $bLabel }}</span>
                        @if ($exam->isScheduled())
                            <div style="font-size: 11px; color: var(--text-faint); margin-top: 3px;">opens {{ $exam->available_from->diffForHumans() }}</div>
                        @elseif ($exam->isLive() && $exam->available_until)
                            <div style="font-size: 11px; color: var(--text-faint); margin-top: 3px;">closes {{ $exam->available_until->diffForHumans() }}</div>
                        @elseif ($exam->isExpired())
                            <div style="font-size: 11px; color: var(--text-faint); margin-top: 3px;">closed {{ $exam->available_until->diffForHumans() }}</div>
                        @endif
                    </td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">{{ $exam->question_count }}</td>
                    <td style="padding: var(--pad-cell); font-size: 13px;">
                        <span style="font-weight: 600;">{{ $exam->submitted_count }}</span>
                        <span style="color: var(--text-faint);"> / {{ $exam->schoolClass?->student_count ?? 0 }} students</span>
                    </td>
                    @php $avg = ($exam->avg_score !== null && $exam->question_count) ? round($exam->avg_score / $exam->question_count * 100) : null; @endphp
                    <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 600;">{{ $avg !== null ? $avg.'%' : '—' }}</td>
                    <td style="padding: var(--pad-cell); text-align: right; white-space: nowrap;">
                        @if ($exam->isDraft())
                            <button type="button" class="btn btn-primary btn-sm"
                                    @click="relAction = '{{ route('v2.teacher.exams.release', $exam) }}'; relTitle = @js($exam->title); mode = 'now'; relOpen = true">
                                <x-icon name="play" size="12"/> Release
                            </button>
                        @endif
                        <a href="{{ route('v2.teacher.exams.show', $exam) }}" class="btn btn-ghost btn-sm">Manage <x-icon name="chev-r" size="13"/></a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">
                        No exams yet. <a href="{{ route('v2.teacher.exams.create') }}" style="color: var(--gold-700); font-weight: 600; text-decoration: none;">Create your first test →</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Release modal (shared) --}}
<div x-show="relOpen" x-cloak class="tx-modal" @keydown.escape.window="relOpen = false">
    <div @click="relOpen = false" style="position: absolute; inset: 0; background: rgba(0,0,0,.55);"></div>
    <form method="POST" :action="relAction" x-show="relOpen" x-transition
          style="position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 24px; width: 100%; max-width: 460px; box-shadow: 0 24px 64px rgba(0,0,0,.35);">
        @csrf @method('PATCH')
        <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 4px;">Release test</h3>
        <p style="font-size: 13px; color: var(--text-soft); margin-bottom: 18px;" x-text="'“' + relTitle + '” — choose when students can take it.'"></p>

        <input type="hidden" name="mode" :value="mode">
        <div class="qb-seg" style="display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:16px;">
            <button type="button" style="padding:7px 14px;font-size:12.5px;font-weight:600;border:0;cursor:pointer;" :style="mode==='now' ? 'background:var(--primary,#061C30);color:#fff;' : 'background:var(--bg);color:var(--text-soft);'" @click="mode='now'">Release now</button>
            <button type="button" style="padding:7px 14px;font-size:12.5px;font-weight:600;border:0;cursor:pointer;" :style="mode==='schedule' ? 'background:var(--primary,#061C30);color:#fff;' : 'background:var(--bg);color:var(--text-soft);'" @click="mode='schedule'">Schedule</button>
        </div>

        <div x-show="mode === 'schedule'" style="margin-bottom: 14px;">
            <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);margin-bottom:6px;">Opens at</label>
            <input type="datetime-local" name="release_at" :required="mode==='schedule'" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:14px;color:var(--text);">
        </div>

        <div style="margin-bottom: 14px;">
            <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);margin-bottom:6px;">Expires at <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-faint);">— optional; no access after this</span></label>
            <input type="datetime-local" name="expires_at" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:14px;color:var(--text);">
        </div>

        <label class="flex items-center gap-2" style="font-size:12.5px;color:var(--text-soft);cursor:pointer;margin-bottom:20px;">
            <input type="checkbox" name="release_results" value="1" style="width:15px;height:15px;">
            Release results immediately — students see their score as they submit
        </label>

        <div class="flex items-center justify-end gap-2">
            <button type="button" class="btn btn-ghost" @click="relOpen = false">Cancel</button>
            <button type="submit" class="btn btn-primary"><x-icon name="check" size="13"/> Release</button>
        </div>
    </form>
</div>
</div>
@endsection
