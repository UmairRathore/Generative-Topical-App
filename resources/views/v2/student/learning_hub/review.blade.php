@extends('v2.layouts.student')
@section('page_title', 'Review mistake')

@php
    use App\Models\V2\StudentMistake;
    $statusBadge = [
        'new' => ['badge-soft', 'New'], 'reviewed' => ['badge-review', 'Reviewed'],
        'practiced' => ['badge-review', 'Practiced'], 'mastered' => ['badge-pass', 'Mastered'],
        'archived' => ['badge-soft', 'Archived'],
    ];
    [$bClass, $bLabel] = $statusBadge[$mistake->status] ?? ['badge-soft', ucfirst($mistake->status)];
    $resolved = in_array($mistake->status, StudentMistake::RESOLVED_STATUSES, true);
    $diffTone = $mistake->difficulty === 'hard' ? 'var(--bad)' : ($mistake->difficulty === 'medium' ? 'var(--warn)' : 'var(--ok)');
    $chips = [
        ['Times wrong', $mistake->mistake_count.'×', 'var(--bad)'],
        ['Your answer', $mistake->selected_option ?: '-', 'var(--bad)'],
        ['Correct answer', $mistake->correct_option ?: '-', 'var(--ok)'],
        ['Difficulty', $mistake->difficulty ? ucfirst($mistake->difficulty) : '-', $diffTone],
        ['Reviewed', $mistake->review_count.'×', 'var(--text)'],
    ];
@endphp

@section('content')
{{-- Follows the exam-results page pattern: shell + score-strip header + main/aside grid. --}}
<style>
    .result-shell{max-width:1180px;margin:0 auto;}
    .result-grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:24px;align-items:start;}
    .result-aside{position:sticky;top:84px;max-height:calc(100vh - 100px);overflow:auto;}
    .rs-strip{padding:22px 26px;}
    .ar-card{padding:20px;}
    @media (max-width:900px){
        .result-grid{grid-template-columns:minmax(0,1fr);}
        .result-aside{position:static;order:-1;max-height:none;overflow:visible;height:auto;align-self:start;}
    }
    @media (max-width:600px){ .rs-strip{padding:16px;} .rs-hero{gap:16px;} .ar-card{padding:15px 14px;} }
</style>

<div class="result-shell">
    <a href="{{ route('v2.student.learning_hub.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
        <x-icon name="chev-l" size="14"/> Back to My Mistakes
    </a>

    {{-- Header (score-strip pattern) --}}
    <div class="rs-strip" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); margin-bottom: 18px;">
        <div class="flex items-center gap-6 rs-hero" style="flex-wrap: wrap;">
            <div style="flex: none; width: 92px; height: 92px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 4px solid var(--bad);">
                <div class="serif" style="font-size: 25px; font-weight: 700; color: var(--bad);">{{ $mistake->mistake_count }}&times;</div>
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-faint);">wrong</div>
            </div>
            <div style="min-width: 0;">
                <div class="flex items-center gap-2" style="flex-wrap: wrap; margin-bottom: 4px;"><span class="badge {{ $bClass }}">{{ $bLabel }}</span></div>
                <h2 class="serif" style="font-size: 23px; font-weight: 600;">{{ $mistake->topic?->title ?? 'Untagged topic' }}</h2>
                <div style="font-size: 13.5px; color: var(--text-soft); margin-top: 3px;">{{ $mistake->subject?->name }}@if ($mistake->latestExam) · from {{ $mistake->latestExam->title }}@endif</div>
                <div style="font-size: 12px; color: var(--text-faint); margin-top: 3px;">Last wrong {{ $mistake->last_wrong_at?->diffForHumans() }}@if ($mistake->year) · {{ $mistake->year }}@endif @if ($mistake->source_paper) · {{ $mistake->source_paper }}@endif</div>
            </div>
        </div>

        <div class="flex items-center" style="flex-wrap: wrap; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border);">
            @foreach ($chips as [$label, $val, $color])
                <div style="flex: 1 1 120px; min-width: 108px; background: var(--soft-surface); border: 1px solid var(--border); border-radius: 10px; padding: 10px 13px;">
                    <div style="font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-faint);">{{ $label }}</div>
                    <div style="font-size: 18px; font-weight: 700; margin-top: 3px; color: {{ $color }};">{{ $val }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Question + summary/actions sidebar --}}
    <div class="result-grid">
        <div class="result-main">
            <div style="font-size: 14px; font-weight: 600; margin: 0 2px 12px;">The question</div>
            @include('v2.partials.answer_review', ['exam' => $exam, 'answers' => $answers, 'revealCorrect' => true])

            {{-- Worked solution (display-only; fetched on demand, never generated this phase) --}}
            <div x-data="{
                    open: false, loaded: false, available: {{ $solution ? 'true' : 'false' }}, title: @js($solution?->title), content: @js($solution?->content),
                    toggle() {
                        this.open = ! this.open;
                        if (this.open && ! this.loaded) {
                            this.loaded = true;
                            fetch(@js(route('v2.student.learning_hub.asset', ['mistake' => $mistake, 'type' => 'worked_solution'])), { headers: { 'X-Requested-With': 'fetch' } })
                                .then(r => r.json()).then(d => { this.available = d.available; this.title = d.title; this.content = d.content; }).catch(() => {});
                        }
                    }
                 }"
                 style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px; margin-top: 14px;">
                <div class="flex items-center justify-between" style="gap: 10px;">
                    <div class="flex items-center gap-2" style="font-size: 14px; font-weight: 600;"><x-icon name="sparkle" size="15"/> Worked solution</div>
                    @if ($solution)
                        <button type="button" class="btn btn-ghost btn-sm" @click="toggle()"><span x-text="open ? 'Hide' : 'Show'"></span></button>
                    @else
                        <span style="font-size: 12px; color: var(--text-faint);">Not available yet</span>
                    @endif
                </div>
                @if ($solution)
                    <div x-show="open" x-cloak style="margin-top: 12px;">
                        <template x-if="available">
                            <div>
                                <div x-show="title" x-text="title" style="font-weight: 600; font-size: 13px; margin-bottom: 6px;"></div>
                                <div x-text="content" style="font-size: 13.5px; line-height: 1.6; color: var(--text-soft); white-space: pre-wrap;"></div>
                            </div>
                        </template>
                        <template x-if="! available"><div style="font-size: 13px; color: var(--text-faint);">Learning asset not available yet.</div></template>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sidebar: summary + actions (sticky, like the result palette) --}}
        <div class="result-aside">
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px;">Mistake summary</div>
                <div class="space-y-2" style="margin-bottom: 14px;">
                    @php
                        $rows = [
                            ['Status', $bLabel],
                            ['Times wrong', $mistake->mistake_count],
                            ['Times reviewed', $mistake->review_count],
                            ['First seen', $mistake->first_wrong_at?->diffForHumans() ?? '-'],
                            ['Last wrong', $mistake->last_wrong_at?->diffForHumans() ?? '-'],
                            ['Source test', $mistake->latestExam?->title ?? '-'],
                        ];
                    @endphp
                    @foreach ($rows as [$label, $val])
                        <div class="flex items-center justify-between" style="font-size: 12.5px; gap: 12px;">
                            <span style="color: var(--text-soft);">{{ $label }}</span>
                            <strong style="text-align: right;">{{ $val }}</strong>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-2" style="padding-top: 12px; border-top: 1px solid var(--border);">
                    @if ($resolved)
                        <form method="POST" action="{{ route('v2.student.learning_hub.status', $mistake) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="reset"><button type="submit" class="btn btn-ghost btn-sm" style="width: 100%; justify-content: center;"><x-icon name="refresh" size="13"/> Move back to revise</button></form>
                    @else
                        <form method="POST" action="{{ route('v2.student.learning_hub.status', $mistake) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="mastered"><button type="submit" class="btn btn-primary btn-sm" style="width: 100%; justify-content: center;"><x-icon name="check" size="13"/> Mark as mastered</button></form>
                        <form method="POST" action="{{ route('v2.student.learning_hub.status', $mistake) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="archive"><button type="submit" class="btn btn-ghost btn-sm" style="width: 100%; justify-content: center;"><x-icon name="eye-off" size="13"/> Archive</button></form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
