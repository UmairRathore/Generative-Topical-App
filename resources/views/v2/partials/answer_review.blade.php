{{-- Question-by-question review of a submitted attempt: each option marked
     correct (green) / the picked-but-wrong option (red). Shared by the student
     result page and the teacher's per-student paper view.
     Props: $exam (with examQuestions.question.options/images loaded),
            $answers (ExamAnswer collection keyed by question_id). --}}
@foreach ($exam->examQuestions as $eq)
    @php
        $q = $eq->question;
        $ans = $answers[$q->id] ?? null;
        $optionImages = $q->images->where('role', 'option_image')->keyBy('option_label');
        $correct = $ans?->correct_option;
        $selected = $ans?->selected_option;
    @endphp
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 14px; {{ $eq->is_voided ? 'opacity: .72;' : '' }}">
        <div class="flex items-start gap-3">
            <span class="badge {{ $eq->is_voided ? 'badge-soft' : ($ans && $ans->is_correct ? 'badge-pass' : 'badge-blocker') }}" style="flex: none; font-weight: 700;">{{ $eq->sort_order }}</span>
            <div style="flex: 1; min-width: 0;">
                @if ($eq->is_voided)
                    <div class="flex items-center gap-2" style="margin-bottom: 10px; padding: 7px 11px; border: 1px dashed var(--border); border-radius: 8px; background: var(--soft-surface);">
                        <x-icon name="flag" size="13" />
                        @if ($eq->void_source === 'quality_review')
                            <span style="font-size: 12px; color: var(--text-soft);"><strong style="color: var(--text);">Quality Review Update</strong> — this question was excluded because its digital version did not accurately match the official Cambridge paper and mark scheme. Your score and statistics were recalculated automatically.</span>
                        @else
                            <span style="font-size: 12px; color: var(--text-soft);"><strong style="color: var(--text);">Excluded from scoring</strong> — this question was removed after review and does not count towards your result.</span>
                        @endif
                    </div>
                @endif
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
                                @php $c = 'v2-opt-circle'.($correct && $opt->label === $correct ? ' is-correct'
                                       : ($selected && $opt->label === $selected && $opt->label !== $correct ? ' is-wrong' : '')); @endphp
                                <label><span class="{{ $c }}">{{ $opt->label }}</span></label>
                            @endforeach
                        </div>
                        <div class="v2-table-wrap">
                            <img src="{{ simg($optTable->image_path) }}" alt="options table" onerror="this.style.display='none'"
                                 @if ($tw) style="width:{{ $tw }}px;" @endif>
                        </div>
                    </div>
                    @if ($correct)
                        <div style="text-align: center; font-size: 12px; color: var(--text-soft); margin-top: 9px;">
                            Correct answer: <strong style="color: var(--ok);">{{ $correct }}</strong>@if ($selected && $selected !== $correct) · You chose: <strong style="color: var(--bad);">{{ $selected }}</strong>@endif
                        </div>
                    @endif
                @else
                <div class="{{ $hasOptImgs ? 'v2-opt-grid' : 'space-y-2' }}">
                    @foreach ($q->options as $opt)
                        @php
                            $isCorrect = $correct && $opt->label === $correct;
                            $isWrongPick = $selected && $opt->label === $selected && ! $isCorrect;
                            $style = $isCorrect ? 'border-color:var(--ok); background:var(--ok-soft);'
                                   : ($isWrongPick ? 'border-color:var(--bad); background:var(--bad-soft);' : 'border-color:var(--border);');
                            $oi = $optionImages[$opt->label] ?? null;
                            $circle = 'v2-opt-circle'.($isCorrect ? ' is-correct' : ($isWrongPick ? ' is-wrong' : ''));
                        @endphp
                        @if ($oi)
                            <div style="display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid; border-radius: 10px; {{ $style }}">
                                <span class="flex items-center gap-2">
                                    <span class="{{ $circle }}">{{ $opt->label }}</span>
                                    @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct</span>@endif
                                    @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Selected</span>@endif
                                </span>
                                <img src="{{ simg($oi->image_path) }}" alt="option {{ $opt->label }}" onerror="this.style.display='none'" class="v2-opt-img">
                            </div>
                        @else
                            <div class="flex items-center gap-3" style="padding: 9px 13px; border: 1px solid; border-radius: 8px; {{ $style }}">
                                <span class="{{ $circle }}">{{ $opt->label }}</span>
                                @if (trim((string) $opt->text) !== '')<span style="font-size: 13.5px;">{!! sci($opt->text) !!}</span>@endif
                                <span style="flex: 1;"></span>
                                @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct answer</span>@endif
                                @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Selected</span>@endif
                            </div>
                        @endif
                    @endforeach
                </div>
                @endif

                @unless ($correct)
                    <p style="font-size: 12px; color: var(--text-faint); margin-top: 8px;">Answer key for this question is pending import.</p>
                @endunless

                {{-- Students may report an issue while reviewing (higher-quality signal,
                     they can see the marked answer). Hidden on the teacher's paper view. --}}
                @if (auth('v2_student')->check())
                    @include('v2.partials.student_flag', ['exam' => $exam, 'q' => $q])
                @endif
            </div>
        </div>
    </div>
@endforeach
