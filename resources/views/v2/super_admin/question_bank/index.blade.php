@extends('v2.layouts.super_admin')
@section('page_title', 'Question Bank')

@php
    $selStyle = 'padding: 8px 11px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text); min-width: 0;';
    $labelStyle = 'display:block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 5px;';
    $sessionLabels = $sessions;
@endphp

@section('content')

{{-- Heading --}}
<div style="margin-bottom: 20px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">Question Bank</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">
        The complete pool every generated test draws from. Browse and filter across all subjects, papers and topics.
    </p>
</div>

{{-- Stats --}}
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px;">
    @foreach ([
        ['Total questions', number_format($stats['total']), 'layers'],
        ['Matching filters', number_format($stats['matched']), 'filter'],
        ['Papers', number_format($stats['papers']), 'book'],
        ['Topic-tagged', $stats['total'] ? round($stats['tagged'] / $stats['total'] * 100).'%' : '0%', 'target'],
    ] as [$label, $value, $icon])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                <x-icon :name="$icon" size="13" /> {{ $label }}
            </div>
            <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('v2.super_admin.question_bank.index') }}"
      style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px; margin-bottom: 18px;">
    <input type="hidden" name="view" value="{{ $view }}">
    <div class="grid" style="grid-template-columns: repeat(6, 1fr); gap: 14px;">

        <div>
            <label style="{{ $labelStyle }}">Level / Grade</label>
            <select name="level" style="{{ $selStyle }} width: 100%;">
                <option value="">All levels</option>
                @foreach ($levels as $lvl)
                    <option value="{{ $lvl }}" @selected($filters['level'] === $lvl)>{{ $lvl }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label style="{{ $labelStyle }}">Subject</label>
            <select name="subject" style="{{ $selStyle }} width: 100%;">
                <option value="">All subjects</option>
                @foreach ($subjects as $s)
                    <option value="{{ $s->id }}" @selected($filters['subject'] === $s->id)>{{ $s->name }} ({{ $s->code }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label style="{{ $labelStyle }}">Year</label>
            <select name="year" style="{{ $selStyle }} width: 100%;">
                <option value="">All years</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label style="{{ $labelStyle }}">Session</label>
            <select name="session" style="{{ $selStyle }} width: 100%;">
                <option value="">All sessions</option>
                @foreach ($sessionLabels as $code => $lbl)
                    <option value="{{ $code }}" @selected($filters['session'] === $code)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label style="{{ $labelStyle }}">Paper variant</label>
            <select name="variant" style="{{ $selStyle }} width: 100%;">
                <option value="">All variants</option>
                @foreach ($variants as $v)
                    <option value="{{ $v }}" @selected($filters['variant'] === $v)>Paper {{ $v }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label style="{{ $labelStyle }}">Topic</label>
            <select name="topic" style="{{ $selStyle }} width: 100%;">
                <option value="">All topics</option>
                @foreach ($topics as $t)
                    <option value="{{ $t->id }}" @selected($filters['topic'] === $t->id)>{{ $t->external_id }}. {{ $t->title }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-end gap-3" style="margin-top: 14px;">
        <div style="flex: 1;">
            <label style="{{ $labelStyle }}">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Question text or paper code…"
                   style="{{ $selStyle }} width: 100%;">
        </div>
        <div style="width: 180px;">
            <label style="{{ $labelStyle }}">Type</label>
            <select name="layout" style="{{ $selStyle }} width: 100%;">
                <option value="">All types</option>
                @foreach ($layouts as $lt)
                    <option value="{{ $lt }}" @selected($filters['layout'] === $lt)>{{ ucfirst(str_replace('_', ' ', $lt)) }}</option>
                @endforeach
            </select>
        </div>
        <div style="width: 150px;">
            <label style="{{ $labelStyle }}">Answer</label>
            <select name="answer" style="{{ $selStyle }} width: 100%;">
                <option value="">Any</option>
                <option value="answered" @selected($filters['answer'] === 'answered')>Has answer</option>
                <option value="unanswered" @selected($filters['answer'] === 'unanswered')>No answer</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="filter" size="14"/> Apply</button>
        <a href="{{ route('v2.super_admin.question_bank.index') }}" class="btn btn-ghost">Reset</a>
    </div>
</form>

{{-- View toggle --}}
<div class="flex items-center justify-between" style="margin-bottom: 14px;">
    <div style="font-size: 12.5px; color: var(--text-faint);">
        {{ number_format($stats['matched']) }} {{ Str::plural('question', $stats['matched']) }} match
    </div>
    <div class="flex items-center" style="gap: 6px;">
        <a href="{{ request()->fullUrlWithQuery(['view' => 'table', 'page' => 1]) }}"
           class="btn btn-sm {{ $view === 'table' ? 'btn-primary' : 'btn-ghost' }}"><x-icon name="list" size="14"/> Table</a>
        <a href="{{ request()->fullUrlWithQuery(['view' => 'gallery', 'page' => 1]) }}"
           class="btn btn-sm {{ $view === 'gallery' ? 'btn-primary' : 'btn-ghost' }}"><x-icon name="grid" size="14"/> Gallery</a>
    </div>
</div>

@if ($view === 'gallery')
    {{-- Gallery: each question rendered exactly as students see it (for visual QA) --}}
    @forelse ($questions as $q)
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); margin-bottom: 16px; overflow: hidden;">
            <div class="flex items-center" style="flex-wrap: wrap; gap: 8px; padding: 12px 18px; border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                <span style="font-size: 13px; font-weight: 700;">{{ $q->source_paper }}</span>
                <span style="font-size: 12px; color: var(--text-faint);">Q{{ $q->question_number }} · {{ $q->year }} · {{ $sessionLabels[$q->paper?->session_code] ?? $q->paper?->session_code }}</span>
                <span style="flex: 1;"></span>
                @if ($q->topic)
                    <span class="badge badge-emerald">{{ $q->topic->external_id }}. {{ \Illuminate\Support\Str::limit($q->topic->title, 26) }}</span>
                @else
                    <span class="badge badge-soft">Untagged</span>
                @endif
                <span class="badge badge-soft" style="font-size: 10px;">{{ str_replace('_', ' ', $q->layout_type) }}</span>
                <span class="badge badge-soft" style="font-size: 10px;">ID {{ $q->id }}</span>
                @if ($q->correct_answer)
                    <span class="badge badge-pass" style="font-weight: 700;">Ans {{ $q->correct_answer }}</span>
                @else
                    <span class="badge badge-blocker" style="font-size: 10px;">No answer</span>
                @endif
            </div>
            <div style="padding: 20px 22px; max-width: 760px;">
                @include('v2.partials.question_card', ['q' => $q])
            </div>
        </div>
    @empty
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">
            No questions match these filters.
            <a href="{{ route('v2.super_admin.question_bank.index') }}" style="color: var(--gold-700); font-weight: 600; text-decoration: none;">Reset filters →</a>
        </div>
    @endforelse
@else
{{-- Results --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table class="tbl" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">Paper</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">Topic</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">Question</th>
                <th style="padding: var(--pad-cell); text-align: center; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">Type</th>
                <th style="padding: var(--pad-cell); text-align: center; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">Ans</th>
                <th style="padding: var(--pad-cell); text-align: center; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-faint);">Diag</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($questions as $q)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: var(--pad-cell); vertical-align: top; white-space: nowrap;">
                        <div style="font-size: 13px; font-weight: 600;">{{ $q->source_paper }}</div>
                        <div style="font-size: 11.5px; color: var(--text-faint); margin-top: 2px;">
                            Q{{ $q->question_number }} · {{ $q->year }} · {{ $sessionLabels[$q->paper?->session_code] ?? $q->paper?->session_code }}
                        </div>
                    </td>
                    <td style="padding: var(--pad-cell); vertical-align: top; white-space: nowrap;">
                        @if ($q->topic)
                            <span class="badge badge-emerald">{{ $q->topic->external_id }}. {{ \Illuminate\Support\Str::limit($q->topic->title, 22) }}</span>
                        @else
                            <span class="badge badge-soft">Untagged</span>
                        @endif
                    </td>
                    <td style="padding: var(--pad-cell); vertical-align: top; max-width: 460px;">
                        <span style="font-size: 13px; color: var(--text); line-height: 1.45;"
                              title="{{ $q->question_text }}">
                            {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $q->question_text), 150) }}
                        </span>
                    </td>
                    <td style="padding: var(--pad-cell); vertical-align: top; text-align: center; white-space: nowrap;">
                        <span class="badge badge-soft" style="font-size: 10px;">{{ str_replace('_', ' ', $q->layout_type) }}</span>
                    </td>
                    <td style="padding: var(--pad-cell); vertical-align: top; text-align: center;">
                        @if ($q->correct_answer)
                            <span class="badge badge-pass" style="font-weight: 700;">{{ $q->correct_answer }}</span>
                        @else
                            <span style="color: var(--text-faint);">—</span>
                        @endif
                    </td>
                    <td style="padding: var(--pad-cell); vertical-align: top; text-align: center; font-size: 12.5px; color: var(--text-soft);">
                        {{ $q->images_count ?: '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">
                        No questions match these filters.
                        <a href="{{ route('v2.super_admin.question_bank.index') }}" style="color: var(--gold-700); font-weight: 600; text-decoration: none;">Reset filters →</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- Pager --}}
@if ($questions->hasPages())
    <div class="flex items-center justify-between" style="margin-top: 16px;">
        <div style="font-size: 12.5px; color: var(--text-faint);">
            Showing {{ $questions->firstItem() }}–{{ $questions->lastItem() }} of {{ number_format($questions->total()) }}
        </div>
        <div class="flex items-center gap-2">
            @if ($questions->onFirstPage())
                <span class="btn btn-ghost btn-sm" style="opacity:.45; pointer-events:none;"><x-icon name="chev-l" size="13"/> Prev</span>
            @else
                <a href="{{ $questions->previousPageUrl() }}" class="btn btn-ghost btn-sm"><x-icon name="chev-l" size="13"/> Prev</a>
            @endif
            <span style="font-size: 12.5px; color: var(--text-soft); padding: 0 6px;">Page {{ $questions->currentPage() }} of {{ $questions->lastPage() }}</span>
            @if ($questions->hasMorePages())
                <a href="{{ $questions->nextPageUrl() }}" class="btn btn-ghost btn-sm">Next <x-icon name="chev-r" size="13"/></a>
            @else
                <span class="btn btn-ghost btn-sm" style="opacity:.45; pointer-events:none;">Next <x-icon name="chev-r" size="13"/></span>
            @endif
        </div>
    </div>
@endif

@endsection
