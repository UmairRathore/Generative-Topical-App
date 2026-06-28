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
<style>[x-cloak]{display:none!important}.tx-modal{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px}
.seg{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;}
.seg button{padding:8px 18px;font-size:12.5px;font-weight:600;border:0;cursor:pointer;background:var(--bg);color:var(--text-soft);transition:background .12s,color .12s;}
.seg button.on{background:var(--primary,#061C30);color:#fff;}
.seg button + button{border-left:1px solid var(--border);}</style>

<div class="flex items-center justify-between" style="margin-bottom: 24px;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">Exams</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Generate topic tests, then release them to students - now or at a scheduled time; each test closes automatically when its duration runs out.</p>
    </div>
    <a href="{{ route('v2.teacher.exams.create') }}" class="btn btn-primary"><x-icon name="plus" size="14"/> Create Exam</a>
</div>

@if (session('success'))
    <div style="margin-bottom: 16px; padding: 11px 16px; background: rgba(var(--ok-rgb,95,160,82),.12); border: 1px solid var(--ok); border-radius: 8px; font-size: 13px; color: var(--ok); font-weight: 600;">{{ session('success') }}</div>
@endif

<div x-data="{ relOpen: false, relAction: '', relTitle: '', open: 'now', relDur: 30, relHasDur: true }">
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; min-width: 720px;">
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
                        <div class="flex items-center gap-2">
                            <span style="font-size: 14px; font-weight: 600;">{{ $exam->title }}</span>
                            @if ($exam->flagged_count > 0)
                                <a href="{{ route('v2.teacher.exams.show', $exam) }}" class="badge badge-blocker" style="text-decoration: none;" title="Students reported one or more questions">
                                    <x-icon name="flag" size="10"/> {{ $exam->flagged_count }} flagged
                                </a>
                            @endif
                        </div>
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
                    <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 600;">{{ $avg !== null ? $avg.'%' : '-' }}</td>
                    <td style="padding: var(--pad-cell); text-align: right; white-space: nowrap;">
                        @if ($exam->isDraft())
                            <button type="button" class="btn btn-primary btn-sm"
                                    @click="relAction = '{{ route('v2.teacher.exams.release', $exam) }}'; relTitle = @js($exam->title); open = 'now'; relDur = {{ (int) ($exam->duration_minutes ?: 30) }}; relHasDur = {{ $exam->duration_minutes ? 'true' : 'false' }}; relOpen = true">
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
</div>

{{-- Release modal (shared) - teleported past the layout's transformed wrapper so it centers. --}}
<template x-teleport="body">
<div x-show="relOpen" x-cloak class="tx-modal" style="display:none;" @keydown.escape.window="relOpen = false">
    <div @click="relOpen = false" style="position: absolute; inset: 0; background: rgba(0,0,0,.55);"></div>
    <form method="POST" :action="relAction" x-show="relOpen" x-transition
          style="position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 24px; width: 100%; max-width: 460px; box-shadow: 0 24px 64px rgba(0,0,0,.35);">
        @csrf @method('PATCH')
        <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 4px;">Release test</h3>
        <p style="font-size: 13px; color: var(--text-soft); margin-bottom: 18px;" x-text="'“' + relTitle + '” - choose when students can take it.'"></p>

        <input type="hidden" name="open" :value="open">
        <input type="hidden" name="duration_minutes" :value="relDur">
        <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);margin-bottom:6px;">When does it open?</label>
        <div class="seg" style="margin-bottom:14px;flex-wrap:wrap;">
            <button type="button" :class="open==='now' ? 'on' : ''" @click="open='now'">Open now</button>
            <button type="button" :class="open==='in_5' ? 'on' : ''" @click="open='in_5'">In 5 min</button>
            <button type="button" :class="open==='in_10' ? 'on' : ''" @click="open='in_10'">In 10 min</button>
            <button type="button" :class="open==='schedule' ? 'on' : ''" @click="open='schedule'">Schedule</button>
        </div>

        <div x-show="open === 'schedule'" x-cloak style="margin-bottom: 14px;">
            <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);margin-bottom:6px;">Opens at</label>
            <input type="datetime-local" name="release_at" :required="open==='schedule'" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:14px;color:var(--text);">
        </div>

        <div x-show="!relHasDur" x-cloak style="margin-bottom: 14px;">
            <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);margin-bottom:6px;">Duration (minutes)</label>
            <input type="number" x-model.number="relDur" min="1" max="240" style="width:100%;max-width:180px;padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:14px;color:var(--text);">
        </div>

        <p style="font-size:12px;color:var(--text-soft);margin-bottom:20px;display:flex;align-items:center;gap:6px;">
            <x-icon name="clock" size="12"/> Closes automatically <strong x-text="relDur"></strong> min after it opens. Results unlock once it closes.
        </p>

        <div class="flex items-center justify-end gap-2">
            <button type="button" class="btn btn-ghost" @click="relOpen = false">Cancel</button>
            <button type="submit" class="btn btn-primary"><x-icon name="check" size="13"/> Release</button>
        </div>
    </form>
</div>
</template>
</div>
@endsection
