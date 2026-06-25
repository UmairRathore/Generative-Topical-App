@extends('v2.layouts.teacher')
@section('page_title', 'Manage test')

@php
    $stateBadge = [
        'draft'     => ['badge-soft', 'Draft'],
        'scheduled' => ['badge-review', 'Scheduled'],
        'live'      => ['badge-pass', 'Live'],
        'expired'   => ['badge-blocker', 'Expired'],
    ];
    [$sbClass, $sbLabel] = $stateBadge[$exam->effectiveStatus()];
    $fieldStyle = 'padding: 8px 11px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);';
    $sessionLabels = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];
@endphp

@section('content')
<style>
    .seg{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;}
    .seg button{padding:8px 18px;font-size:12.5px;font-weight:600;border:0;cursor:pointer;background:var(--bg);color:var(--text-soft);transition:background .12s,color .12s;}
    .seg button.on{background:var(--primary,#061C30);color:#fff;}
    .seg button + button{border-left:1px solid var(--border);}
    [x-cloak]{display:none!important;}
    .qprev-overlay{position:fixed;inset:0;z-index:60;background:rgba(0,0,0,.5);display:flex;justify-content:center;align-items:flex-start;padding:32px 16px;overflow-y:auto;}
    .qprev-panel{background:var(--bg);border-radius:var(--r-lg);width:100%;max-width:780px;margin:auto;box-shadow:0 24px 60px rgba(0,0,0,.4);overflow:hidden;}
    .qprev-head{position:sticky;top:0;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 22px;background:var(--surface);border-bottom:1px solid var(--border);z-index:1;}
    .qprev-close{border:0;background:none;cursor:pointer;color:var(--text-soft);padding:5px;display:inline-flex;border-radius:7px;}
    .qprev-close:hover{background:var(--soft-surface);}
    .qprev-body{padding:18px 22px 26px;}
    .qprev-q{border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface);padding:20px 22px;margin-bottom:14px;}
    .flag-highlight{box-shadow:0 0 0 2px var(--accent);border-color:var(--accent)!important;transition:box-shadow .3s;}
    .shot-thumb{width:46px;height:46px;flex:none;border-radius:7px;border:1px solid var(--border);object-fit:cover;cursor:zoom-in;background:var(--soft-surface);}
</style>
<div x-data="{ preview: false }" @keydown.escape.window="preview = false">
<a href="{{ route('v2.teacher.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Exams
</a>

<div class="flex items-start justify-between" style="margin-bottom: 18px; gap: 16px; flex-wrap: wrap;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $exam->title }}</h2>
        <div class="flex items-center gap-2" style="margin-top: 6px; flex-wrap: wrap;">
            <span class="badge {{ $sbClass }}">{{ $sbLabel }}</span>
            @if ($exam->resultsReleased())
                <span class="badge badge-pass">Results released</span>
            @elseif ($exam->isReleased())
                <span class="badge badge-soft">Results hidden</span>
            @endif
            <span class="badge badge-soft">{{ $exam->schoolClass?->name }}</span>
            <span class="badge badge-emerald">{{ $exam->topic?->title ?? 'Mixed topics' }}</span>
            <span class="badge badge-soft">{{ $exam->question_count }} questions</span>
            @php $toReview = $studentFlags->where('has_open', true)->count(); @endphp
            @if ($studentFlags->isNotEmpty())
                <a href="#reported-questions" class="badge {{ $toReview > 0 ? 'badge-review' : 'badge-soft' }}" style="text-decoration: none;">
                    <x-icon name="flag" size="11"/> {{ $studentFlags->count() }} reported{{ $toReview > 0 ? ' · '.$toReview.' to review' : '' }}
                </a>
            @endif
        </div>
    </div>
    <button type="button" class="btn btn-ghost" @click="preview = true">
        <x-icon name="eye" size="15"/> Preview questions
    </button>
</div>

@if (session('success'))
    <div style="margin-bottom: 16px; padding: 11px 16px; background: rgba(var(--ok-rgb,95,160,82),.12); border: 1px solid var(--ok); border-radius: 8px; font-size: 13px; color: var(--ok); font-weight: 600;">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div style="margin-bottom: 16px; padding: 11px 16px; background: rgba(var(--bad-rgb,200,70,70),.12); border: 1px solid var(--bad); border-radius: 8px; font-size: 13px; color: var(--bad); font-weight: 600;">{{ session('error') }}</div>
@endif

{{-- ============ MANAGE: availability + results ============ --}}
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px; align-items: stretch;">

    {{-- Availability --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
        <div class="flex items-center gap-2" style="margin-bottom: 14px;">
            <x-icon name="clipboard" size="15"/>
            <span style="font-size: 14px; font-weight: 700;">Test availability</span>
        </div>

        @if ($exam->isDraft())
            <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 14px; line-height: 1.5;">
                This test is a <strong>draft</strong> - students can't see it yet. Release it now or schedule it, with an optional expiry after which no student can access it.
            </p>
            <form method="POST" action="{{ route('v2.teacher.exams.release', $exam) }}" x-data="{ mode: 'now' }">
                @csrf @method('PATCH')
                <input type="hidden" name="mode" :value="mode">
                <div class="seg" style="margin-bottom:14px;">
                    <button type="button" :class="mode==='now' ? 'on' : ''" @click="mode='now'">Release now</button>
                    <button type="button" :class="mode==='schedule' ? 'on' : ''" @click="mode='schedule'">Schedule</button>
                </div>
                <div class="space-y-3">
                    <div x-show="mode==='schedule'">
                        <label style="display:block; font-size:11px; color:var(--text-faint); margin-bottom:4px;">Opens at</label>
                        <input type="datetime-local" name="release_at" :required="mode==='schedule'" style="{{ $fieldStyle }} width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; color:var(--text-faint); margin-bottom:4px;">Expires at <span style="color:var(--text-faint);">- optional</span></label>
                        <input type="datetime-local" name="expires_at" style="{{ $fieldStyle }} width:100%;">
                    </div>
                    <label class="flex items-center gap-2" style="font-size:12.5px; color:var(--text-soft); cursor:pointer;">
                        <input type="checkbox" name="release_results" value="1" style="width:15px;height:15px;">
                        Release results immediately - students see their score as they submit
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:14px;"><x-icon name="play" size="13"/> Release test</button>
            </form>
        @else
            <div style="font-size: 13px; color: var(--text-soft); line-height: 1.7;">
                <div>
                    @if ($exam->isScheduled())
                        <strong style="color:var(--text);">Opens</strong> {{ $exam->available_from->format('D j M, g:i A') }} ({{ $exam->available_from->diffForHumans() }})
                    @else
                        <strong style="color:var(--text);">Released</strong> {{ $exam->released_at?->diffForHumans() }}, open since {{ $exam->available_from?->format('D j M, g:i A') }}
                    @endif
                </div>
                <div>
                    @if ($exam->available_until)
                        <strong style="color:var(--text);">{{ $exam->isExpired() ? 'Closed' : 'Closes' }}</strong> {{ $exam->available_until->format('D j M, g:i A') }} ({{ $exam->available_until->diffForHumans() }})
                    @else
                        <strong style="color:var(--text);">No expiry</strong> - stays open until you close it.
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Results --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
        <div class="flex items-center gap-2" style="margin-bottom: 14px;">
            <x-icon name="chart" size="15"/>
            <span style="font-size: 14px; font-weight: 700;">Results to students</span>
        </div>

        <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 14px; line-height: 1.5;">
            <strong>{{ $submittedCount }}</strong> of {{ $students->count() }} {{ \Illuminate\Support\Str::plural('student', $students->count()) }} submitted.
            Students don't see their score or answers until you release results.
        </p>

        @if ($exam->resultsReleased())
            <div class="flex items-center gap-2" style="font-size: 13px; color: var(--ok); font-weight: 600; margin-bottom: 14px;">
                <x-icon name="check" size="15"/> Released {{ $exam->results_released_at->diffForHumans() }} - students can see their results.
            </div>
            <form method="POST" action="{{ route('v2.teacher.exams.release_results', $exam) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="release" value="0">
                <button type="submit" class="btn btn-ghost btn-sm">Hide results again</button>
            </form>
        @else
            <div style="font-size: 13px; color: var(--text-soft); margin-bottom: 14px;">Results are currently <strong>hidden</strong> - submitted students see only “awaiting results”.</div>
            <form method="POST" action="{{ route('v2.teacher.exams.release_results', $exam) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="release" value="1">
                <button type="submit" class="btn btn-primary"><x-icon name="check" size="13"/> Release results to students</button>
            </form>
        @endif
    </div>
</div>

{{-- Stat cards --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
    @foreach ([
        ['Class average', $avg !== null ? $avg.'%' : '-', 'chart'],
        ['Submitted', $submittedCount.' / '.$students->count(), 'check'],
        ['Questions', $exam->question_count, 'clipboard'],
        ['Topic', $exam->topic?->title ?? 'Mixed', 'target'],
    ] as [$label, $value, $icon])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                <x-icon :name="$icon" size="13"/> {{ $label }}
            </div>
            <div class="serif" style="font-size: 22px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 20px; align-items: start;">

    {{-- Student results (paginated, 5 per page) --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;"
         x-data="{ page: 1, perPage: 5, total: {{ $students->count() }}, get pages() { return Math.max(1, Math.ceil(this.total / this.perPage)); } }">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Student results</div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    @foreach (['Student', 'Roll', 'Status', 'Time', 'Score'] as $h)
                        <th style="padding: var(--pad-cell); text-align: {{ $loop->last ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    @php $a = $attempts[$student->id] ?? null; $done = $a && $a->status === 'submitted'; @endphp
                    <tr x-show="{{ $loop->index }} >= (page - 1) * perPage && {{ $loop->index }} < page * perPage"
                        style="border-bottom: 1px solid var(--border);{{ $loop->index >= 5 ? 'display:none;' : '' }}">
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">
                            @if ($done)
                                <a href="{{ route('v2.teacher.exams.student_paper', [$exam, $student]) }}"
                                   class="flex items-center gap-1" style="color: var(--emerald-700); text-decoration: none;">
                                    {{ $student->name }} <x-icon name="chev-r" size="13"/>
                                </a>
                            @else
                                {{ $student->name }}
                            @endif
                        </td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ $student->roll_number }}</td>
                        <td style="padding: var(--pad-cell);">
                            @if ($done)
                                <span class="badge badge-pass">Completed</span>
                            @elseif ($a)
                                <span class="badge badge-review">In progress</span>
                            @elseif ($exam->isExpired())
                                <span class="badge badge-blocker">Missed</span>
                            @elseif ($exam->isLive())
                                <span class="badge badge-soft">Not started</span>
                            @else
                                <span class="badge badge-soft">-</span>
                            @endif
                        </td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">
                            {{ $done ? ($a->time_taken ?? '-') : '-' }}
                        </td>
                        <td style="padding: var(--pad-cell); text-align: right; font-weight: 600; font-size: 13px;">
                            @if ($done)
                                {{ $a->score }}/{{ $a->total_questions }} <span style="color: var(--text-faint); font-weight: 500;">({{ $a->percentage }}%)</span>
                            @else
                                <span style="color: var(--text-faint);">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 32px; text-align: center; color: var(--text-faint); font-size: 13px;">No students enrolled in this class.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($students->count() > 5)
            <div class="flex items-center justify-between" style="padding: 11px 18px; border-top: 1px solid var(--border); font-size: 12px;">
                <span style="color: var(--text-soft);">
                    Showing <span x-text="(page - 1) * perPage + 1"></span>–<span x-text="Math.min(page * perPage, total)"></span> of {{ $students->count() }}
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" :disabled="page === 1" @click="if (page > 1) page--">Prev</button>
                    <span style="color: var(--text-soft);">Page <span x-text="page"></span> / <span x-text="pages"></span></span>
                    <button type="button" class="btn btn-ghost btn-sm" :disabled="page >= pages" @click="if (page < pages) page++">Next</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Per-topic stats --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Performance by topic</div>
        @forelse ($topicStats as $t)
            <div style="margin-bottom: 14px;">
                <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                    <span style="font-weight: 500;">{{ $t['topic'] }}</span>
                    <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
                </div>
                <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                    <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $t['percent'] >= 60 ? 'var(--ok)' : ($t['percent'] >= 40 ? 'var(--warn)' : 'var(--bad)') }};"></div>
                </div>
            </div>
        @empty
            <p style="font-size: 13px; color: var(--text-faint);">No submissions yet - stats appear once students complete the test.</p>
        @endforelse
    </div>
</div>

{{-- ============ STUDENT-REPORTED ISSUES (at end of page) ============ --}}
@if ($studentFlags->isNotEmpty())
    <div id="reported-questions" x-data="{ lightbox: null }" @keydown.escape.window="lightbox = null"
         style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-top: 24px;">
        <div class="flex items-center gap-2" style="margin-bottom: 6px;">
            <x-icon name="flag" size="15"/>
            <span style="font-size: 14px; font-weight: 700;">Reported questions</span>
            @php $toReview = $studentFlags->where('has_open', true)->count(); @endphp
            @if ($toReview > 0)<span class="badge badge-review">{{ $toReview }} to review</span>@endif
        </div>
        <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 16px; line-height: 1.5;">
            <strong>Dismiss</strong> if the question is valid — it stays in the exam and keeps counting. Or
            <strong>Send for Quality Review</strong> if it may have an issue: this voids it for this exam (so no student is
            graded on it), recomputes marks, rankings &amp; analytics{{ $exam->resultsReleased() ? ' and notifies affected students' : '' }},
            and sends the bank question to the Support Team. Each action applies to <strong>all</strong> reports on that question.
        </p>

        @foreach ($studentFlags as $f)
            @php
                $reporters = collect($f['flags'])
                    ->map(fn ($x) => trim(($x['student'] ?? 'A student').($x['roll'] ? ' (Roll '.$x['roll'].')' : '')))
                    ->filter()->unique()->values();
            @endphp
            <div x-data="{ show: false, esc: false }" id="flag-q{{ $f['sort_order'] }}"
                 x-init="if (window.location.hash === '#flag-q{{ $f['sort_order'] }}') { show = true; $el.scrollIntoView({behavior:'smooth', block:'center'}); $el.classList.add('flag-highlight'); }"
                 style="border: 1px solid var(--border); border-radius: 10px; padding: 13px 15px; margin-bottom: 10px;">
                <div class="flex items-center justify-between" style="gap: 12px; flex-wrap: wrap;">
                    <div class="flex items-center gap-2" style="min-width: 0; flex-wrap: wrap;">
                        <span class="badge badge-emerald" style="font-weight: 700;">Q{{ $f['sort_order'] }}</span>
                        @if ($f['is_escalated'])<span class="badge badge-review">Under Quality Review</span>
                        @elseif ($f['is_voided'])<span class="badge badge-blocker">Voided</span>
                        @elseif ($f['has_open'])<span class="badge badge-soft">Open</span>@endif
                        @if (! empty($f['qr_outcome']))
                            @php
                                [$qrCls, $qrTxt] = [
                                    'correct'  => ['badge-pass', 'Correct — Matches Source'],
                                    'cosmetic' => ['badge-emerald', 'Cosmetic Improvement'],
                                    'material' => ['badge-blocker', 'Material Error Confirmed'],
                                ][$f['qr_outcome']] ?? ['badge-soft', ucfirst($f['qr_outcome'])];
                            @endphp
                            <span class="badge {{ $qrCls }}">Quality Review: {{ $qrTxt }}</span>
                            @if (! empty($f['qr_propagation']))
                                <span class="badge {{ $f['qr_propagation'] === 'completed' ? 'badge-soft' : 'badge-review' }}">Propagation: {{ ucfirst($f['qr_propagation']) }}</span>
                            @endif
                        @endif
                        @if ($reporters->isNotEmpty())
                            <span style="font-size: 12.5px; color: var(--text-soft);">
                                Reported by <span style="font-weight: 600; color: var(--text);">{{ $reporters->take(2)->implode(', ') }}</span>@if ($reporters->count() > 2) <span style="color: var(--text-faint);">+{{ $reporters->count() - 2 }} more</span>@endif
                            </span>
                        @endif
                        @if (! empty($f['flags']))
                            <button type="button" @click="show = !show" style="font-size: 12px; background: none; border: 0; color: var(--accent); cursor: pointer;" x-text="show ? 'Hide detail' : 'View detail'"></button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2" style="flex: none;">
                        @unless ($f['is_escalated'] || $f['is_voided'])
                            @if ($f['has_open'])
                                <form method="POST" action="{{ route('v2.teacher.exams.dismiss_flags', [$exam, hid($f['question_id'])]) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-ghost btn-sm">Dismiss</button>
                                </form>
                            @endif
                            <button type="button" @click="esc = !esc" class="btn btn-primary btn-sm"><x-icon name="send" size="12"/> Send for Quality Review</button>
                        @endunless
                    </div>
                </div>

                {{-- Send for quality review: voids for this exam + queues a Support Team review --}}
                @unless ($f['is_escalated'] || $f['is_voided'])
                    <div x-show="esc" x-cloak style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                        <form method="POST" action="{{ route('v2.teacher.exams.send_for_review', [$exam, hid($f['question_id'])]) }}">
                            @csrf @method('PATCH')
                            <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-faint); margin-bottom: 5px;">What's the concern? (optional — the Support Team sees this)</label>
                            <textarea name="note" rows="2" maxlength="1000" placeholder="e.g. I've confirmed the answer key is wrong against the mark scheme."
                                      style="width: 100%; padding: 8px 10px; font-size: 12.5px; border: 1px solid var(--border); border-radius: 7px; background: var(--bg); color: var(--text); resize: vertical; margin-bottom: 8px;"></textarea>
                            <button type="submit" class="btn btn-primary btn-sm"><x-icon name="send" size="12"/> Send for Quality Review</button>
                            <span style="font-size: 11px; color: var(--text-faint); margin-left: 8px;">Voids this question for this exam and hides the bank question from new exams until review completes (existing exams &amp; scores are unaffected).</span>
                        </form>
                    </div>
                @endunless

                {{-- Per-report detail with screenshot thumbnails (lightbox, not a new tab) --}}
                <div x-show="show" x-cloak style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                    @foreach ($f['flags'] as $fl)
                        <div class="flex" style="gap: 10px; margin-bottom: 10px;">
                            @if ($fl['shot'])
                                <img src="{{ asset('storage/'.$fl['shot']) }}" alt="screenshot" class="shot-thumb" @click="lightbox = '{{ asset('storage/'.$fl['shot']) }}'" onerror="this.style.display='none'">
                            @endif
                            <div style="font-size: 12.5px; min-width: 0;">
                                <span style="font-weight: 600;">{{ $fl['reason'] }}</span>
                                <span style="color: var(--text-faint);">· {{ $fl['student'] ?? 'A student' }}@if ($fl['roll']) (Roll {{ $fl['roll'] }})@endif · {{ $fl['when']?->diffForHumans() }}</span>
                                @if ($fl['note'])<div style="color: var(--text-soft); margin-top: 2px;">“{{ $fl['note'] }}”</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Screenshot lightbox (teleported past the layout's transformed wrapper) --}}
        <template x-teleport="body">
            <div x-show="lightbox" x-cloak @click.self="lightbox = null"
                 style="position: fixed; inset: 0; z-index: 80; background: rgba(0,0,0,.72); display: flex; align-items: center; justify-content: center; padding: 28px;">
                <img :src="lightbox" alt="screenshot" style="max-width: 92vw; max-height: 90vh; border-radius: 8px; box-shadow: 0 24px 70px rgba(0,0,0,.5);">
                <button type="button" @click="lightbox = null" aria-label="Close" style="position: absolute; top: 18px; right: 22px; width: 38px; height: 38px; border-radius: 50%; border: 0; background: rgba(255,255,255,.15); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"><x-icon name="x" size="20"/></button>
            </div>
        </template>
    </div>
@endif

{{-- ============ PREVIEW: the exact frozen paper (custom + random alike) ============ --}}
{{-- Teleport past the layout's transformed .fade-in wrapper so the fixed overlay
     anchors to the viewport, not the (scrollable) content column. --}}
<template x-teleport="body">
<div class="qprev-overlay" x-show="preview" x-cloak @click.self="preview = false" style="display:none;">
    <div class="qprev-panel">
        <div class="qprev-head">
            <div>
                <div style="font-size: 15px; font-weight: 700;">Preview · {{ $exam->title }}</div>
                <div style="font-size: 12px; color: var(--text-faint); margin-top: 2px;">{{ $exam->question_count }} {{ \Illuminate\Support\Str::plural('question', $exam->question_count) }} · correct answers shown in green</div>
            </div>
            <button type="button" class="qprev-close" @click="preview = false" aria-label="Close"><x-icon name="x" size="18"/></button>
        </div>
        <div class="qprev-body">
            @forelse ($exam->examQuestions as $eq)
                @php $q = $eq->question; @endphp
                @continue (! $q)
                <div class="qprev-q">
                    <div class="flex items-center" style="flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
                        <span class="badge badge-emerald" style="flex: none; font-weight: 700;">{{ $eq->sort_order }}</span>
                        <span style="font-size: 13px; font-weight: 700;">{{ $q->source_paper }}</span>
                        @if ($q->subject?->level)<span style="font-size: 12.5px; font-weight: 800; color: var(--ink);">{{ $q->subject->level }}</span>@endif
                        <span style="font-size: 12px; color: var(--text-faint);">Q{{ $q->question_number }} · {{ $q->year }} · {{ $sessionLabels[$q->paper?->session_code] ?? $q->paper?->session_code }}</span>
                        <span style="flex: 1;"></span>
                        <button type="button" class="btn btn-ghost btn-sm" style="color: var(--text-soft);"
                                @click="$dispatch('flag-question', { action: '{{ route('v2.teacher.questions.flag', $q) }}', label: '{{ $q->source_paper }} · Q{{ $q->question_number }}', id: {{ $q->id }} })">
                            <x-icon name="flag" size="13"/> Report
                        </button>
                    </div>
                    @include('v2.partials.question_card', ['q' => $q])
                </div>
            @empty
                <p style="font-size: 13px; color: var(--text-faint); text-align: center; padding: 24px;">This test has no questions.</p>
            @endforelse
        </div>
    </div>
</div>
</template>

@include('v2.partials.flag_modal')
</div>
@endsection
