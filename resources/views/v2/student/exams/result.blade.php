@extends('v2.layouts.student')
@section('page_title', 'Result')

@php $pct = $attempt->percentage; $tone = $pct >= 60 ? 'var(--emerald-700)' : ($pct >= 40 ? 'var(--accent)' : '#ef4444'); @endphp

@section('content')
<div style="max-width: 760px; margin: 0 auto;">
    <a href="{{ route('v2.student.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
        <x-icon name="chev-l" size="14"/> Back to My Exams
    </a>

    {{-- Score hero --}}
    <div class="flex items-center gap-6" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 26px 28px; margin-bottom: 20px;">
        <div style="flex: none; width: 96px; height: 96px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 4px solid {{ $tone }};">
            <div class="serif" style="font-size: 26px; font-weight: 700; color: {{ $tone }};">{{ $pct }}%</div>
        </div>
        <div>
            <h2 class="serif" style="font-size: 24px; font-weight: 600;">{{ $exam->title }}</h2>
            <div style="font-size: 14px; color: var(--text-soft); margin-top: 4px;">
                You scored <strong style="color: var(--text);">{{ $attempt->score }} out of {{ $attempt->total_questions }}</strong>
                · {{ $exam->topic?->title ?? 'Mixed topics' }}
            </div>
            <div style="font-size: 12px; color: var(--text-faint); margin-top: 4px;">Submitted {{ $attempt->submitted_at?->diffForHumans() }}@if ($attempt->time_taken) · time taken {{ $attempt->time_taken }}@endif</div>
        </div>
    </div>

    {{-- Per-topic --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Your performance by topic</div>
        @foreach ($topicStats as $t)
            <div style="margin-bottom: 13px;">
                <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                    <span style="font-weight: 500;">{{ $t['topic'] }}</span>
                    <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
                </div>
                <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                    <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $t['percent'] >= 60 ? 'var(--emerald-700)' : ($t['percent'] >= 40 ? 'var(--accent)' : '#ef4444') }};"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Review --}}
    <div style="font-size: 14px; font-weight: 600; margin: 0 2px 12px;">Review answers</div>
    @foreach ($exam->examQuestions as $eq)
        @php
            $q = $eq->question;
            $ans = $answers[$q->id] ?? null;
            $optionImages = $q->images->where('role', 'option_image')->keyBy('option_label');
            $correct = $ans?->correct_option;
            $selected = $ans?->selected_option;
        @endphp
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 14px;">
            <div class="flex items-start gap-3">
                <span class="badge {{ $ans && $ans->is_correct ? 'badge-pass' : 'badge-blocker' }}" style="flex: none; font-weight: 700;">{{ $eq->sort_order }}</span>
                <div style="flex: 1; min-width: 0;">
                    @include('v2.partials.question_stem', ['q' => $q])

                    @include('v2.partials.options_divider', ['q' => $q])

                    @php
                        // Safety net for any unfixed mis-tagged question: show the
                        // figure(s) once, then fall back to plain lettered options.
                        $collapsed = $q->collapsedOptionFigures();
                        if ($collapsed) { $optionImages = collect(); }
                    @endphp
                    @if ($collapsed)
                        @foreach ($collapsed as $fig)
                            @php $cw = $fig->displayWidth(); @endphp
                            <div class="v2-figure-wrap">
                                <img src="{{ asset('storage/'.$fig->image_path) }}" alt="diagram" onerror="this.style.display='none'"
                                     @if ($cw) style="width:{{ $cw }}px;" @endif>
                            </div>
                        @endforeach
                    @endif

                    @php $hasOptImgs = $optionImages->isNotEmpty(); @endphp
                    <div class="{{ $hasOptImgs ? 'v2-opt-grid' : 'space-y-2' }}">
                        @foreach ($q->options as $opt)
                            @php
                                $isCorrect = $correct && $opt->label === $correct;
                                $isWrongPick = $selected && $opt->label === $selected && ! $isCorrect;
                                $style = $isCorrect ? 'border-color:#6ee7b7; background:var(--emerald-50);'
                                       : ($isWrongPick ? 'border-color:#fca5a5; background:#fef2f2;' : 'border-color:var(--border);');
                                $oi = $optionImages[$opt->label] ?? null;
                                $circle = 'v2-opt-circle'.($isCorrect ? ' is-correct' : ($isWrongPick ? ' is-wrong' : ''));
                            @endphp
                            @if ($oi)
                                <div style="display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid; border-radius: 10px; {{ $style }}">
                                    <span class="flex items-center gap-2">
                                        <span class="{{ $circle }}">{{ $opt->label }}</span>
                                        @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct</span>@endif
                                        @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Your answer</span>@endif
                                    </span>
                                    <img src="{{ asset('storage/'.$oi->image_path) }}" alt="option {{ $opt->label }}" onerror="this.style.display='none'" class="v2-opt-img">
                                </div>
                            @else
                                <div class="flex items-center gap-3" style="padding: 9px 13px; border: 1px solid; border-radius: 8px; {{ $style }}">
                                    <span style="font-weight: 700; font-size: 12.5px; color: var(--text-soft); width: 16px;">{{ $opt->label }}</span>
                                    @if (trim((string) $opt->text) !== '')<span style="font-size: 13.5px;">{{ $opt->text }}</span>@endif
                                    <span style="flex: 1;"></span>
                                    @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct answer</span>@endif
                                    @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Your answer</span>@endif
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @unless ($correct)
                        <p style="font-size: 12px; color: var(--text-faint); margin-top: 8px;">Answer key for this question is pending import.</p>
                    @endunless
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
