@extends('v2.layouts.student')
@section('page_title', 'Learning Hub')

@php
    use App\Models\V2\StudentMistake;
    $statusBadge = [
        'new'       => ['badge-soft', 'New'],
        'reviewed'  => ['badge-review', 'Reviewed'],
        'practiced' => ['badge-review', 'Practiced'],
        'mastered'  => ['badge-pass', 'Mastered'],
        'archived'  => ['badge-soft', 'Archived'],
    ];
    $diffTone = fn ($d) => $d === 'hard' ? 'var(--bad)' : ($d === 'medium' ? 'var(--warn)' : 'var(--ok)');
    // Teaser only (anti-scraping): the query already truncates the stem at
    // source; cap the rendered text at 100 chars so the list never becomes
    // a bulk question browser.
    $snippet = fn ($q) => \Illuminate\Support\Str::limit(trim(strip_tags((string) ($q->question_text ?: $q->text_before))) ?: 'Question', 100);
    $grouped = $sort === 'grouped';
    $curSubject = null; $curTopic = null;
@endphp

@section('content')
<div style="max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">My Mistakes</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Every question you've answered incorrectly, gathered here to revise. Nothing disappears - once you master it, it stays in your history.</p>
    </div>

    @if (session('success'))
        <div style="margin-bottom: 16px; padding: 11px 16px; background: rgba(var(--ok-rgb,95,160,82),.12); border: 1px solid var(--ok); border-radius: 8px; font-size: 13px; color: var(--ok); font-weight: 600;">{{ session('success') }}</div>
    @endif

    {{-- Analytics --}}
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 14px;">
        @foreach ([['Total mistakes', $analytics['total'], 'target'], ['To revise', $analytics['unresolved'], 'clock'], ['Mastered', $analytics['mastered'], 'trophy']] as [$label, $value, $icon])
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
                <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;"><x-icon :name="$icon" size="13"/> {{ $label }}</div>
                <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 5px;">{{ $value }}</div>
            </div>
        @endforeach
        @if ($analytics['most_repeated'])
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px;">
                <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;"><x-icon name="refresh" size="13"/> Most repeated</div>
                <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 5px;">{{ $analytics['most_repeated']->mistake_count }}&times;</div>
            </div>
        @endif
    </div>

    @if ($analytics['top_topics']->isNotEmpty())
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px; margin-bottom: 14px;">
            <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 9px;">Weakest topics</div>
            <div class="flex items-center" style="flex-wrap: wrap; gap: 8px;">
                @foreach ($analytics['top_topics'] as $t)
                    <span class="badge badge-emerald" style="font-weight: 500;">{{ $t->topic }} <span style="opacity:.7;">· {{ $t->questions }}</span></span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($analytics['pending'] > 0)
        <div class="flex items-center gap-2" style="margin-bottom: 14px; padding: 9px 14px; border: 1px dashed var(--border); border-radius: 8px; background: var(--soft-surface); font-size: 12.5px; color: var(--text-soft);">
            <x-icon name="clock" size="13"/> {{ $analytics['pending'] }} more {{ \Illuminate\Support\Str::plural('mistake', $analytics['pending']) }} will appear here once your teacher releases those results.
        </div>
    @endif

    {{-- Filters + sort --}}
    <form method="GET" class="flex items-center" style="flex-wrap: wrap; gap: 8px; margin-bottom: 16px;"
          x-data="{ subjectId: @js((string) ($filters['subject_id'] ?? '')), topicId: @js((string) ($filters['topic_id'] ?? '')), topics: @js($topicsBySubject) }">
        @php $sel = 'padding:7px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface); font-size:12.5px; color:var(--text);'; @endphp
        <select name="filter" style="{{ $sel }}">
            @foreach (['all' => 'All', 'needs_review' => 'Needs review', 'most_repeated' => 'Most repeated', 'never_reviewed' => 'Never reviewed', 'mastered' => 'Mastered'] as $k => $v)
                <option value="{{ $k }}" @selected(($filters['filter'] ?? 'all') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="subject_id" x-model="subjectId" @change="topicId = ''" style="{{ $sel }}">
            <option value="">All subjects</option>
            @foreach ($subjects as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </select>
        {{-- Topic is subject-scoped: disabled until a subject is picked, and lists only
             that subject's topics that actually have mistakes. --}}
        <select name="topic_id" x-model="topicId" :disabled="! subjectId"
                style="{{ $sel }}" :style="! subjectId ? { opacity: '0.55', cursor: 'not-allowed' } : {}">
            <option value="" x-text="subjectId ? 'All topics' : 'Select a subject first'"></option>
            <template x-for="t in (topics[subjectId] || [])" :key="t.id">
                <option :value="t.id" x-text="t.title"></option>
            </template>
        </select>
        <select name="difficulty" style="{{ $sel }}">
            <option value="">Any difficulty</option>
            @foreach (['easy', 'medium', 'hard'] as $d)
                <option value="{{ $d }}" @selected(($filters['difficulty'] ?? '') === $d)>{{ ucfirst($d) }}</option>
            @endforeach
        </select>
        <select name="sort" style="{{ $sel }}">
            @foreach (['grouped' => 'Group by topic', 'recent' => 'Newest', 'oldest' => 'Oldest', 'most_mistakes' => 'Most mistakes', 'difficulty' => 'Hardest first'] as $k => $v)
                <option value="{{ $k }}" @selected($sort === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2" style="font-size: 12.5px; color: var(--text-soft);">
            <input type="checkbox" name="hide_completed" value="1" @checked(! empty($filters['hide_completed']))> Hide completed
        </label>
        <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="filter" size="13"/> Apply</button>
    </form>

    {{-- Mistake list --}}
    @forelse ($mistakes as $m)
        @if ($grouped)
            @if ($m->subject_id !== $curSubject)
                @php $curSubject = $m->subject_id; $curTopic = null; @endphp
                <h3 class="serif" style="font-size: 16px; font-weight: 600; margin: 18px 0 8px;">{{ $m->subject?->name ?? 'Subject' }}</h3>
            @endif
            @if ($m->topic_id !== $curTopic)
                @php $curTopic = $m->topic_id; @endphp
                <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-faint); margin: 10px 0 6px;">{{ $m->topic?->title ?? 'Untagged topic' }}</div>
            @endif
        @endif

        @php [$bClass, $bLabel] = $statusBadge[$m->status] ?? ['badge-soft', ucfirst($m->status)]; @endphp
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 15px 17px; margin-bottom: 10px;">
            <div class="flex items-center" style="gap: 8px; flex-wrap: wrap; margin-bottom: 7px;">
                @if ($m->difficulty)<span class="badge" style="background: {{ $diffTone($m->difficulty) }}1a; color: {{ $diffTone($m->difficulty) }};">{{ ucfirst($m->difficulty) }}</span>@endif
                <span class="badge {{ $bClass }}">{{ $bLabel }}</span>
                @if ($m->mistake_count > 1)<span class="badge badge-blocker">Wrong {{ $m->mistake_count }}&times;</span>@endif
                @unless ($grouped)
                    @if ($m->subject)<span class="badge badge-emerald">{{ $m->subject->name }}</span>@endif
                @endunless
            </div>

            <div style="font-size: 14px; line-height: 1.5;">{{ $snippet($m->question) }}</div>

            <div class="flex items-center" style="gap: 6px 16px; flex-wrap: wrap; margin-top: 9px; font-size: 11.5px; color: var(--text-faint);">
                <span>Last wrong {{ $m->last_wrong_at?->diffForHumans() }}</span>
                @if ($m->latestExam)<span>· {{ $m->latestExam->title }}</span>@endif
                @if ($m->year)<span>· {{ $m->year }}</span>@endif
                @if ($m->source_paper)<span>· {{ $m->source_paper }}</span>@endif
                @if ($m->review_count > 0)<span>· reviewed {{ $m->review_count }}&times;</span>@endif
            </div>

            {{-- AI Tutor status - aggregates only, never chat content. --}}
            @php $aiMsgs = (int) ($m->ai_message_count ?? 0); @endphp
            <div class="flex items-center" style="gap: 6px 12px; flex-wrap: wrap; margin-top: 9px; font-size: 11.5px;">
                @if ($aiMsgs > 0)
                    <span class="badge badge-review">AI discussed · {{ $aiMsgs }} {{ \Illuminate\Support\Str::plural('message', $aiMsgs) }}</span>
                    @if ($m->ai_last_message_at)<span style="color: var(--text-faint);">Last asked {{ \Illuminate\Support\Carbon::parse($m->ai_last_message_at)->diffForHumans() }}</span>@endif
                    @if ($m->ai_latest_quiz_score !== null)<span class="badge badge-soft">Quiz {{ $m->ai_latest_quiz_score }}/{{ $m->ai_latest_quiz_total }}</span>@endif
                @else
                    <span class="badge badge-soft" style="color: var(--text-faint);">AI not discussed yet</span>
                @endif
            </div>

            <div class="flex items-center" style="gap: 8px; flex-wrap: wrap; margin-top: 12px;">
                <a href="{{ route('v2.student.learning_hub.review', $m) }}" class="btn btn-primary btn-sm"><x-icon name="eye" size="13"/> Review</a>
                <a href="{{ route('v2.student.learning_hub.studio', $m) }}?tab=ai-tutor" class="btn btn-ghost btn-sm">
                    <x-icon name="sparkle" size="13"/> {{ $aiMsgs > 0 ? 'Continue AI' : 'Ask AI Tutor' }}
                </a>
                @if (in_array($m->status, StudentMistake::RESOLVED_STATUSES, true))
                    <form method="POST" action="{{ route('v2.student.learning_hub.status', $m) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="reset"><button type="submit" class="btn btn-ghost btn-sm"><x-icon name="refresh" size="13"/> Move back to revise</button></form>
                @else
                    <form method="POST" action="{{ route('v2.student.learning_hub.status', $m) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="mastered"><button type="submit" class="btn btn-ghost btn-sm"><x-icon name="check" size="13"/> Mark mastered</button></form>
                @endif
            </div>
        </div>
    @empty
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 44px; text-align: center; color: var(--text-faint); font-size: 14px;">
            <x-icon name="trophy" size="22"/>
            <p style="margin-top: 10px;">No mistakes to revise{{ ($filters['filter'] ?? 'all') !== 'all' || $filters['subject_id'] || $filters['difficulty'] ? ' for this filter' : '' }} - nice work.</p>
            @if ($analytics['pending'] > 0)<p style="font-size: 12.5px; margin-top: 6px;">{{ $analytics['pending'] }} {{ \Illuminate\Support\Str::plural('mistake', $analytics['pending']) }} waiting on released results.</p>@endif
        </div>
    @endforelse

    <div style="margin-top: 16px;">{{ $mistakes->links() }}</div>
</div>
@endsection
