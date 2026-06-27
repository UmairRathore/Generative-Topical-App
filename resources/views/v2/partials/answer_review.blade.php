{{-- Question-by-question review of a submitted attempt: each option marked
     correct (green) / the picked-but-wrong option (red) / unattempted (neutral).
     Shared by the student result page and the teacher's per-student paper view.
     Props: $exam (with examQuestions.question.options/images loaded),
            $answers (ExamAnswer collection keyed by question_id),
            $revealCorrect (bool, default true) - when false the correct option is
            never revealed (the student still sees their own answer + marks). --}}
@php $revealCorrect = $revealCorrect ?? true; @endphp
@foreach ($exam->examQuestions as $eq)
    @php
        $q = $eq->question;
        $ans = $answers[$q->id] ?? null;
        $optionImages = $q->images->where('role', 'option_image')->keyBy('option_label');
        $correct = $ans?->correct_option;
        $selected = $ans?->selected_option;
        $isVoided = (bool) $eq->is_voided;
        $attempted = $ans && $selected !== null;
        // Only reveal the correct option's identity when allowed.
        $reveal = $revealCorrect && $correct !== null;

        // Number badge: voided / correct / wrong / unattempted (neutral grey).
        $badgeClass = '';
        $badgeStyle = 'float: left; margin-right: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; height: 22px; padding: 0 8px; line-height: 1;';
        if ($isVoided) {
            $badgeClass = 'badge-soft';
        } elseif (! $attempted) {
            $badgeStyle .= ' background: var(--soft-surface); color: var(--text-soft);';
        } elseif ($ans && $ans->is_correct) {
            $badgeClass = 'badge-pass';
        } else {
            $badgeClass = 'badge-blocker';
        }
    @endphp
    <div id="q{{ $eq->sort_order }}" data-result-question-card data-question-number="{{ $eq->sort_order }}" class="ar-card"
         style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); margin-bottom: 14px; scroll-margin-top: 84px; {{ $isVoided ? 'opacity: .72;' : '' }}">
        {{-- Number floats so the statement wraps under it and the options use the full width. --}}
        <span class="badge {{ $badgeClass }}" style="{{ $badgeStyle }}">{{ $eq->sort_order }}</span>
        @if ($isVoided)
                    <div class="flex items-center gap-2" style="margin-bottom: 10px; padding: 7px 11px; border: 1px dashed var(--border); border-radius: 8px; background: var(--soft-surface);">
                        <x-icon name="flag" size="13" />
                        @if ($eq->void_source === 'quality_review')
                            <span style="font-size: 12px; color: var(--text-soft);"><strong style="color: var(--text);">Quality Review Update</strong> - this question was excluded because its digital version did not accurately match the official Cambridge paper and mark scheme. Your score and statistics were recalculated automatically.</span>
                        @else
                            <span style="font-size: 12px; color: var(--text-soft);"><strong style="color: var(--text);">Excluded from scoring</strong> - this question was removed after review and does not count towards your result.</span>
                        @endif
                    </div>
                @endif
                @include('v2.partials.question_stem', ['q' => $q])
                <div style="clear: both;"></div>

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
                            <img src="{{ simg($fig->image_path) }}" alt="diagram" onerror="this.style.display='none'"
                                 @if ($cw) style="width:{{ $cw }}px;" @endif>
                        </div>
                    @endforeach
                @endif

                @php
                    $hasOptImgs = $optionImages->isNotEmpty();
                    $isTable = $q->optionTableImage() !== null;  // table shows each choice
                @endphp
                @if ($isTable)
                    {{-- selectable-style letters beside the answer table, one per row --}}
                    @php $optTable = $q->optionTableImage(); $tw = $optTable?->displayWidth(); @endphp
                    <div class="v2-table-pick">
                        <div class="picks">
                            <span class="hdr"></span>
                            @foreach ($q->options as $opt)
                                @php
                                    $isCorrect = $reveal && $opt->label === $correct;
                                    $isWrongPick = $reveal && $selected && $opt->label === $selected && $opt->label !== $correct;
                                    $isPicked = ! $reveal && $selected && $opt->label === $selected;
                                    $c = 'v2-opt-circle'.($isCorrect ? ' is-correct' : ($isWrongPick ? ' is-wrong' : ''));
                                @endphp
                                <label><span class="{{ $c }}" @if ($isPicked) style="border-color: var(--accent); color: var(--accent);" @endif>{{ $opt->label }}</span></label>
                            @endforeach
                        </div>
                        <div class="v2-table-wrap">
                            <img src="{{ simg($optTable->image_path) }}" alt="options table" onerror="this.style.display='none'"
                                 @if ($tw) style="width:{{ $tw }}px;" @endif>
                        </div>
                    </div>
                @else
                <div class="{{ $hasOptImgs ? 'v2-opt-grid' : 'space-y-2' }}">
                    @foreach ($q->options as $opt)
                        @php
                            $isCorrect = $reveal && $opt->label === $correct;
                            $isWrongPick = $reveal && $selected && $opt->label === $selected && ! $isCorrect;
                            $isPicked = ! $reveal && $selected && $opt->label === $selected;
                            $style = $isCorrect ? 'border-color:var(--ok); background:var(--ok-soft);'
                                   : ($isWrongPick ? 'border-color:var(--bad); background:var(--bad-soft);'
                                   : ($isPicked ? 'border-color:var(--accent); background:var(--soft-surface);' : 'border-color:var(--border);'));
                            $oi = $optionImages[$opt->label] ?? null;
                            $circle = 'v2-opt-circle'.($isCorrect ? ' is-correct' : ($isWrongPick ? ' is-wrong' : ''));
                        @endphp
                        @if ($oi)
                            <div style="display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid; border-radius: 10px; {{ $style }}">
                                <span class="flex items-center gap-2">
                                    <span class="{{ $circle }}">{{ $opt->label }}</span>
                                    @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct</span>@endif
                                    @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Selected</span>@endif
                                    @if ($isPicked)<span class="badge badge-soft" style="font-size:10px;">Your answer</span>@endif
                                </span>
                                <img src="{{ simg($oi->image_path) }}" alt="option {{ $opt->label }}" onerror="this.style.display='none'" class="v2-opt-img">
                            </div>
                        @else
                            <div class="flex items-center gap-3" style="padding: 9px 13px; border: 1px solid; border-radius: 8px; {{ $style }}">
                                <span class="{{ $circle }}" @if ($isPicked) style="border-color: var(--accent); color: var(--accent);" @endif>{{ $opt->label }}</span>
                                @if (trim((string) $opt->text) !== '')<span style="font-size: 13.5px;">{!! sci($opt->text) !!}</span>@endif
                                <span style="flex: 1;"></span>
                                @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct answer</span>@endif
                                @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Selected</span>@endif
                                @if ($isPicked)<span class="badge badge-soft" style="font-size:10px;">Your answer</span>@endif
                            </div>
                        @endif
                    @endforeach
                </div>
                @endif

                {{-- Standardized result footer (shared across all 4 layouts) --}}
                <div class="flex items-center" style="flex-wrap: wrap; gap: 6px 18px; margin-top: 12px; padding-top: 11px; border-top: 1px solid var(--border); font-size: 12.5px;">
                    <span style="color: var(--text-soft);">Your answer:
                        @if ($attempted)<strong style="color: var(--text);">{{ $selected }}</strong>@else<strong style="color: var(--text-faint);">Not answered</strong>@endif
                    </span>
                    @if ($reveal)
                        <span style="color: var(--text-soft);">Correct answer: <strong style="color: var(--ok);">{{ $correct }}</strong></span>
                    @endif
                    <span style="color: var(--text-soft);">Marks:
                        <strong style="color: var(--text);">@if ($isVoided) Excluded @else {{ $ans && $ans->is_correct ? 1 : 0 }} / 1 @endif</strong>
                    </span>
                </div>

                @if ($revealCorrect && ! $correct && ! $isVoided)
                    <p style="font-size: 12px; color: var(--text-faint); margin-top: 8px;">Answer key for this question is pending import.</p>
                @endif

                {{-- Students may report an issue while reviewing (higher-quality signal,
                     they can see the marked answer). Hidden on the teacher's paper view. --}}
                @if (auth('v2_student')->check())
                    @include('v2.partials.student_flag', ['exam' => $exam, 'q' => $q])
                @endif
    </div>
@endforeach
