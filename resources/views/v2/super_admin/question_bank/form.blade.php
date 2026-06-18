@extends('v2.layouts.super_admin')
@section('page_title', $question->exists ? 'Edit Question' : 'New Question')

@php
    $editing = $question->exists;
    $labelStyle = 'display:block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 6px;';
    $fieldStyle = 'width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);';

    // Prefill (old input wins, then the model on edit).
    $vSubject = old('subject_id', $question->subject_id);
    $vTopic   = old('topic_id', $question->topic_id);
    $vAnswer  = old('correct_answer', $question->correct_answer);
    $vStatus  = old('status', $question->status ?? 'active');
    $optValues = old('options', $editing ? $question->options->pluck('text', 'label')->all() : []);

    $topicsJson = $topics->map(fn ($t) => [
        'id'         => $t->id,
        'subject_id' => $t->subject_id,
        'label'      => $t->external_id.'. '.$t->title,
    ])->values();
@endphp

@section('content')
<a href="{{ route('v2.super_admin.question_bank.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> Question Bank
</a>

<div style="margin-bottom: 20px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $editing ? 'Edit question' : 'New question' }}</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">
        @if ($editing)
            {{ $question->source_paper }} · Q{{ $question->question_number }}
            @if ($question->id)<span style="color: var(--text-faint);">· ID {{ $question->id }}</span>@endif
        @else
            A manually authored multiple-choice question. It is added to a per-subject “Custom” paper in the pool.
        @endif
    </p>
</div>

@if ($errors->any())
    <div style="background: rgba(var(--bad-rgb, 255,0,0), .08); border: 1px solid var(--bad); color: var(--bad); border-radius: var(--r-lg); padding: 12px 16px; margin-bottom: 18px; font-size: 13px;">
        <strong>Please fix the following:</strong>
        <ul style="margin: 6px 0 0 18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST"
      action="{{ $editing ? route('v2.super_admin.question_bank.update', $question) : route('v2.super_admin.question_bank.store') }}"
      x-data='{ subject: @json((string) $vSubject), topic: @json((string) $vTopic), topics: @json($topicsJson) }'>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px;">

        {{-- Classification --}}
        <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 16px;">
            <div>
                <label style="{{ $labelStyle }}">Subject <span style="color: var(--bad);">*</span></label>
                <select name="subject_id" x-model="subject" style="{{ $fieldStyle }}">
                    <option value="">Select subject…</option>
                    @foreach ($subjects as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="{{ $labelStyle }}">Topic</label>
                <select name="topic_id" x-model="topic" style="{{ $fieldStyle }}">
                    <option value="">— Untagged —</option>
                    <template x-for="t in topics.filter(t => String(t.subject_id) === String(subject))" :key="t.id">
                        <option :value="t.id" x-text="t.label" :selected="String(t.id) === String(topic)"></option>
                    </template>
                </select>
                <div style="font-size: 11px; color: var(--text-faint); margin-top: 5px;">Topics are filtered by the chosen subject.</div>
            </div>
            <div>
                <label style="{{ $labelStyle }}">Status <span style="color: var(--bad);">*</span></label>
                <select name="status" style="{{ $fieldStyle }}">
                    @foreach ($statuses as $st)
                        <option value="{{ $st }}" @selected($vStatus === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
                <div style="font-size: 11px; color: var(--text-faint); margin-top: 5px;">Only <strong>active</strong> questions appear in generated tests.</div>
            </div>
        </div>

        {{-- Question stem --}}
        <div style="margin-top: 18px;">
            <label style="{{ $labelStyle }}">Question text <span style="color: var(--bad);">*</span></label>
            <textarea name="question_text" rows="4" style="{{ $fieldStyle }} resize: vertical; line-height: 1.5;"
                      placeholder="Enter the question stem…">{{ old('question_text', $question->question_text) }}</textarea>
        </div>

        {{-- Options + correct answer --}}
        <div style="margin-top: 18px;">
            <label style="{{ $labelStyle }}">Options — select the correct one <span style="color: var(--bad);">*</span></label>
            <div class="space-y-2">
                @foreach ($labels as $label)
                    <div class="flex items-center gap-3" style="gap: 12px;">
                        <label class="flex items-center" style="gap: 7px; font-weight: 700; font-size: 14px; white-space: nowrap; cursor: pointer;">
                            <input type="radio" name="correct_answer" value="{{ $label }}" @checked($vAnswer === $label) style="width: 16px; height: 16px; accent-color: var(--ok);">
                            {{ $label }}
                        </label>
                        <input type="text" name="options[{{ $label }}]" value="{{ $optValues[$label] ?? '' }}"
                               placeholder="Option {{ $label }} text…" style="{{ $fieldStyle }} flex: 1;">
                    </div>
                @endforeach
            </div>
            <div style="font-size: 11px; color: var(--text-faint); margin-top: 8px;">The selected radio marks the correct answer (shown green when students review).</div>
        </div>

        {{-- Meta --}}
        <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 18px;">
            <div>
                <label style="{{ $labelStyle }}">Marks <span style="color: var(--bad);">*</span></label>
                <input type="number" name="marks" min="1" max="20" value="{{ old('marks', $question->marks ?? 1) }}" style="{{ $fieldStyle }}">
            </div>
            <div>
                <label style="{{ $labelStyle }}">Difficulty</label>
                <select name="difficulty" style="{{ $fieldStyle }}">
                    <option value="">—</option>
                    @foreach ($difficulties as $d)
                        <option value="{{ $d }}" @selected(old('difficulty', $question->difficulty) === $d)>{{ ucfirst($d) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="{{ $labelStyle }}">Year</label>
                <input type="number" name="year" min="1990" max="{{ date('Y') + 1 }}" value="{{ old('year', $question->year) }}" placeholder="e.g. {{ date('Y') }}" style="{{ $fieldStyle }}">
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3" style="margin-top: 18px;">
        <button type="submit" class="btn btn-primary"><x-icon name="check" size="14"/> {{ $editing ? 'Save changes' : 'Create question' }}</button>
        <a href="{{ route('v2.super_admin.question_bank.index') }}" class="btn btn-ghost">Cancel</a>
    </div>
</form>
@endsection
