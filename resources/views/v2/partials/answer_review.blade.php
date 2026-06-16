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
                                    @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Selected</span>@endif
                                </span>
                                <img src="{{ asset('storage/'.$oi->image_path) }}" alt="option {{ $opt->label }}" onerror="this.style.display='none'" class="v2-opt-img">
                            </div>
                        @else
                            <div class="flex items-center gap-3" style="padding: 9px 13px; border: 1px solid; border-radius: 8px; {{ $style }}">
                                <span class="{{ $circle }}">{{ $opt->label }}</span>
                                @if (trim((string) $opt->text) !== '')<span style="font-size: 13.5px;">{{ $opt->text }}</span>@endif
                                <span style="flex: 1;"></span>
                                @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct answer</span>@endif
                                @if ($isWrongPick)<span class="badge badge-blocker" style="font-size:10px;">Selected</span>@endif
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
