{{-- Read-only full visual of one question (for the super-admin question bank's
     gallery / QA view): stem, diagrams, and options exactly as a student sees
     them, with the correct answer marked green. Prop: $q with options + images
     loaded. Renders nothing interactive. --}}
@php
    $correct = $q->correct_answer;
    $optionImages = $q->images->where('role', 'option_image')->keyBy('option_label');
    $collapsed = $q->collapsedOptionFigures();
    if ($collapsed) { $optionImages = collect(); }
    $isTable = $q->optionTableImage() !== null;
@endphp

@include('v2.partials.question_stem', ['q' => $q])

@include('v2.partials.options_divider', ['q' => $q])

@if ($collapsed)
    @foreach ($collapsed as $fig)
        @php $cw = $fig->displayWidth(); @endphp
        <div class="v2-figure-wrap">
            <img src="{{ simg($fig->image_path) }}" alt="diagram" loading="lazy" onerror="this.style.display='none'"
                 @if ($cw) style="width:{{ $cw }}px;" @endif>
        </div>
    @endforeach
@endif

@if ($isTable)
    @php $optTable = $q->optionTableImage(); $tw = $optTable?->displayWidth(); @endphp
    <div class="v2-table-pick">
        <div class="picks">
            <span class="hdr"></span>
            @foreach ($q->options as $opt)
                <label><span class="v2-opt-circle {{ $correct && $opt->label === $correct ? 'is-correct' : '' }}">{{ $opt->label }}</span></label>
            @endforeach
        </div>
        <div class="v2-table-wrap">
            <img src="{{ simg($optTable->image_path) }}" alt="options table" loading="lazy" onerror="this.style.display='none'"
                 @if ($tw) style="width:{{ $tw }}px;" @endif>
        </div>
    </div>
    @if ($correct)
        <div style="text-align: center; font-size: 12px; color: var(--text-soft); margin-top: 9px;">
            Correct answer: <strong style="color: var(--ok);">{{ $correct }}</strong>
        </div>
    @endif
@else
    @php $hasOptImgs = $optionImages->isNotEmpty(); @endphp
    <div class="{{ $hasOptImgs ? 'v2-opt-grid' : 'space-y-2' }}">
        @foreach ($q->options as $opt)
            @php
                $isCorrect = $correct && $opt->label === $correct;
                $style = $isCorrect ? 'border-color:var(--ok); background:var(--ok-soft);' : 'border-color:var(--border);';
                $oi = $optionImages[$opt->label] ?? null;
                $circle = 'v2-opt-circle'.($isCorrect ? ' is-correct' : '');
            @endphp
            @if ($oi)
                <div style="display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid; border-radius: 10px; {{ $style }}">
                    <span class="flex items-center gap-2">
                        <span class="{{ $circle }}">{{ $opt->label }}</span>
                        @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct</span>@endif
                    </span>
                    <img src="{{ simg($oi->image_path) }}" alt="option {{ $opt->label }}" loading="lazy" onerror="this.style.display='none'" class="v2-opt-img">
                </div>
            @else
                <div class="flex items-center gap-3" style="padding: 9px 13px; border: 1px solid; border-radius: 8px; {{ $style }}">
                    <span class="{{ $circle }}">{{ $opt->label }}</span>
                    @if (trim((string) $opt->text) !== '')<span style="font-size: 13.5px;">{{ $opt->text }}</span>@endif
                    <span style="flex: 1;"></span>
                    @if ($isCorrect)<span class="badge badge-pass" style="font-size:10px;">Correct answer</span>@endif
                </div>
            @endif
        @endforeach
    </div>
@endif

@unless ($correct)
    <p style="font-size: 12px; color: var(--text-faint); margin-top: 8px;">No answer key imported for this question.</p>
@endunless
