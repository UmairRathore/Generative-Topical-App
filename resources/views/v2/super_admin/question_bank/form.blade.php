@extends('v2.layouts.super_admin')
@section('page_title', $question->exists ? 'Edit Question' : 'New Question')

@php
    $editing = $question->exists;
    $labelStyle = 'display:block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 6px;';
    $fieldStyle = 'width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);';
    $optStr = 'font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--text-faint);';

    // Prefill (old input wins, then the model on edit).
    $vSubject = old('subject_id', $question->subject_id);
    $vTopic   = old('topic_id', $question->topic_id);
    $vAnswer  = old('correct_answer', $question->correct_answer ?? 'A');
    $vStatus  = old('status', $question->status ?? 'active');
    $vBefore  = old('text_before', $question->text_before);
    $vAfter   = old('text_after', $question->text_after);
    $optValues = old('options', $editing ? $question->options->pluck('text', 'label')->all() : []);

    // Existing images, grouped by role.
    $betweenImages = $editing ? $question->images->where('role', 'question_image_between_text')->sortBy('sort_order')->values() : collect();
    $afterImages   = $editing ? $question->images->where('role', 'question_image_after_text')->sortBy('sort_order')->values() : collect();
    $answerImage   = $editing ? $question->images->firstWhere('role', 'table') : null;
    $optImages     = $editing ? $question->images->where('role', 'option_image')->keyBy('option_label') : collect();

    $initialMode = old('answer_mode', $answerImage ? 'image' : 'options');
    $answerType  = old('answer_image_type', $answerImage && str_starts_with((string) $answerImage->caption, 'answer:') ? substr($answerImage->caption, 7) : 'table');

    $topicsJson = $topics->map(fn ($t) => ['id' => $t->id, 'subject_id' => $t->subject_id, 'label' => $t->external_id.'. '.$t->title])->values();
@endphp

@section('content')
<style>
    .qb-up{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;border:1.5px dashed var(--border);border-radius:10px;padding:14px;cursor:pointer;text-align:center;color:var(--text-soft);font-size:12.5px;transition:border-color .15s,background .15s;}
    .qb-up:hover{border-color:var(--accent);background:rgba(var(--accent-rgb,0,151,211),.05);}
    .qb-thumbs{display:flex;flex-wrap:wrap;gap:10px;}
    .qb-thumb{display:flex;flex-direction:column;align-items:center;}
    .qb-thumb img,.qb-up img{height:72px;width:auto;border:1px solid var(--border);border-radius:8px;display:block;background:var(--bg);}
    .qb-rm{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--bad);margin-top:4px;cursor:pointer;}
    .qb-optimg img{height:48px;width:auto;border:1px solid var(--border);border-radius:6px;display:block;background:var(--bg);}
    .qb-seg{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;}
    .qb-seg button{padding:7px 14px;font-size:12.5px;font-weight:600;background:var(--bg);color:var(--text-soft);border:0;cursor:pointer;}
    .qb-seg button.on{background:var(--primary,#061C30);color:#fff;}
    .qb-circle{display:inline-flex;align-items:center;gap:7px;padding:7px 13px;border:1px solid var(--border);border-radius:99px;cursor:pointer;font-weight:700;font-size:13px;}
    .qb-circle.on{border-color:var(--ok);background:var(--ok-soft,rgba(95,160,82,.12));color:var(--ok);}
    .qb-sub{border:1px solid var(--border);border-radius:10px;padding:14px;background:var(--bg);}
</style>

<a href="{{ route('v2.super_admin.question_bank.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> Question Bank
</a>

<div style="margin-bottom: 20px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $editing ? 'Edit question' : 'New question' }}</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">
        @if ($editing)
            {{ $question->source_paper }} · Q{{ $question->question_number }}@if ($question->id)<span style="color: var(--text-faint);"> · ID {{ $question->id }}</span>@endif
        @else
            A manually authored question, added to a per-subject “Custom” paper in the pool.
        @endif
    </p>
</div>

@if ($errors->any())
    <div style="background: rgba(var(--bad-rgb, 255,0,0), .08); border: 1px solid var(--bad); color: var(--bad); border-radius: var(--r-lg); padding: 12px 16px; margin-bottom: 18px; font-size: 13px;">
        <strong>Please fix the following:</strong>
        <ul style="margin: 6px 0 0 18px;">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" enctype="multipart/form-data"
      action="{{ $editing ? route('v2.super_admin.question_bank.update', $question) : route('v2.super_admin.question_bank.store') }}"
      x-data='{ subject: @json((string) $vSubject), topic: @json((string) $vTopic), topics: @json($topicsJson), mode: @json($initialMode), correct: @json((string) $vAnswer) }'>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px;">

        {{-- Classification --}}
        <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 16px;">
            <div>
                <label style="{{ $labelStyle }}">Subject <span style="color: var(--bad);">*</span></label>
                <select name="subject_id" x-model="subject" style="{{ $fieldStyle }}">
                    <option value="">Select subject…</option>
                    @foreach ($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>@endforeach
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
            </div>
            <div>
                <label style="{{ $labelStyle }}">Status <span style="color: var(--bad);">*</span></label>
                <select name="status" style="{{ $fieldStyle }}">
                    @foreach ($statuses as $st)<option value="{{ $st }}" @selected($vStatus === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>@endforeach
                </select>
                <div style="font-size: 11px; color: var(--text-faint); margin-top: 5px;">Only <strong>active</strong> questions appear in generated tests.</div>
            </div>
        </div>

        {{-- Question text --}}
        <div style="margin-top: 18px;">
            <label style="{{ $labelStyle }}">Question text <span style="color: var(--bad);">*</span></label>
            <textarea name="question_text" rows="3" style="{{ $fieldStyle }} resize: vertical; line-height: 1.5;"
                      placeholder="The full question stem…">{{ old('question_text', $question->question_text) }}</textarea>
            <div style="font-size: 11px; color: var(--text-faint); margin-top: 5px;">The complete stem. Used for search and shown when there is no in-line diagram below.</div>
        </div>

        {{-- Stem diagrams --}}
        <div class="qb-sub" style="margin-top: 18px;">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 4px;">Stem diagrams <span style="{{ $optStr }}">— optional</span></div>
            <p style="font-size: 11.5px; color: var(--text-faint); margin-bottom: 12px;">For a figure that sits <strong>inside</strong> the text, fill the before/after text and add a “between” diagram. For a figure simply shown below the stem, use “after the text”.</p>

            {{-- between: text_before -> diagram -> text_after --}}
            <label style="{{ $labelStyle }}">Text before the in-line diagram</label>
            <textarea name="text_before" rows="2" style="{{ $fieldStyle }} resize: vertical;" placeholder="Lead-in text shown above the in-line diagram…">{{ $vBefore }}</textarea>

            <div style="margin: 12px 0;">
                <label style="{{ $labelStyle }}">In-line diagram(s) — between the text</label>
                @include('v2.super_admin.question_bank._uploader', ['existing' => $betweenImages, 'name' => 'question_images_between', 'multiple' => true])
            </div>

            <label style="{{ $labelStyle }}">Text after the in-line diagram</label>
            <textarea name="text_after" rows="2" style="{{ $fieldStyle }} resize: vertical;" placeholder="Text shown below the in-line diagram…">{{ $vAfter }}</textarea>

            <div style="margin-top: 14px; border-top: 1px solid var(--border); padding-top: 14px;">
                <label style="{{ $labelStyle }}">Diagram(s) after the text</label>
                @include('v2.super_admin.question_bank._uploader', ['existing' => $afterImages, 'name' => 'question_images_after', 'multiple' => true])
            </div>
        </div>

        {{-- Answer area --}}
        <div style="margin-top: 20px;">
            <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
                <label style="{{ $labelStyle }} margin-bottom: 0;">Answers <span style="color: var(--bad);">*</span></label>
                <div class="qb-seg">
                    <button type="button" :class="mode === 'options' ? 'on' : ''" @click="mode = 'options'">Options A–D</button>
                    <button type="button" :class="mode === 'image' ? 'on' : ''" @click="mode = 'image'">Single answer image</button>
                </div>
            </div>
            <input type="hidden" name="answer_mode" :value="mode">

            {{-- Correct answer (shared by both modes) --}}
            <div style="margin-bottom: 14px;">
                <span style="font-size: 12px; color: var(--text-soft); margin-right: 8px;">Correct answer:</span>
                @foreach ($labels as $label)
                    <label class="qb-circle" :class="correct === '{{ $label }}' ? 'on' : ''" style="margin-right: 6px;">
                        <input type="radio" name="correct_answer" value="{{ $label }}" x-model="correct" style="display:none;"> {{ $label }}
                    </label>
                @endforeach
            </div>

            {{-- Mode: A–D options --}}
            <div x-show="mode === 'options'" class="space-y-2">
                @foreach ($labels as $label)
                    @php $oi = $optImages[$label] ?? null; @endphp
                    <div style="border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px;" :style="correct === '{{ $label }}' ? 'border-color:var(--ok);' : ''">
                        <div class="flex items-center" style="gap: 12px;">
                            <span class="qb-circle" :class="correct === '{{ $label }}' ? 'on' : ''" @click="correct = '{{ $label }}'" style="cursor:pointer;">{{ $label }}</span>
                            <input type="text" name="options[{{ $label }}]" value="{{ $optValues[$label] ?? '' }}" placeholder="Option {{ $label }} text…" style="{{ $fieldStyle }} flex: 1;">
                        </div>
                        <div class="qb-optimg" x-data="{ url: '' }" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 9px; padding-left: 4px;">
                            @if ($oi)
                                <div class="qb-thumb" x-show="!url" x-data="{ rm: false }">
                                    <img src="{{ simg($oi->image_path) }}" alt="option {{ $label }}" :style="rm ? 'opacity:.3; filter:grayscale(1);' : ''">
                                    <label class="qb-rm"><input type="checkbox" name="remove_option_images[{{ $label }}]" value="1" x-model="rm"> <span x-text="rm ? 'Will remove' : 'Remove'"></span></label>
                                </div>
                            @endif
                            <img x-show="url" :src="url" alt="new option {{ $label }}" style="height: 48px; border: 1px solid var(--accent); border-radius: 6px;">
                            <label style="font-size: 12px; color: var(--text-soft); display: inline-flex; align-items: center; gap: 6px;">
                                <span style="white-space: nowrap;">{{ $oi ? 'Replace image:' : 'Add image (graph):' }}</span>
                                <input type="file" name="option_images[{{ $label }}]" accept="image/png,image/jpeg,image/webp,image/gif" style="font-size: 12px;"
                                       @change="url = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : ''">
                            </label>
                        </div>
                    </div>
                @endforeach
                <div style="font-size: 11px; color: var(--text-faint);">Each option needs <strong>text or a graph image</strong>. The highlighted circle is the correct answer.</div>
            </div>

            {{-- Mode: single answer image (table / graph) --}}
            <div x-show="mode === 'image'" class="qb-sub">
                <div class="flex items-center gap-3" style="flex-wrap: wrap; margin-bottom: 12px;">
                    <span style="font-size: 12px; color: var(--text-soft);">Image type:</span>
                    @foreach ($answerTypes as $t)
                        <label class="qb-circle" x-data><input type="radio" name="answer_image_type" value="{{ $t }}" @checked($answerType === $t) style="margin-right:5px;"> {{ ucfirst($t) }}</label>
                    @endforeach
                </div>
                <div x-data="{ url: '' }" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                    @if ($answerImage)
                        <div class="qb-thumb" x-show="!url" x-data="{ rm: false }">
                            <img src="{{ simg($answerImage->image_path) }}" alt="answer image" style="height:96px;border:1px solid var(--border);border-radius:8px;" :style="rm ? 'opacity:.3; filter:grayscale(1);' : ''">
                            <label class="qb-rm"><input type="checkbox" name="remove_answer_image" value="1" x-model="rm"> <span x-text="rm ? 'Will remove' : 'Remove'"></span></label>
                        </div>
                    @endif
                    <img x-show="url" :src="url" alt="new answer image" style="height:96px;border:1px solid var(--accent);border-radius:8px;">
                    <label class="qb-up" style="flex:1; min-width:220px;">
                        <input type="file" name="answer_image" accept="image/png,image/jpeg,image/webp,image/gif" hidden
                               @change="url = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : ''">
                        <span x-show="!url"><x-icon name="plus" size="13"/> {{ $answerImage ? 'Click to replace the answer image' : 'Click to upload the table / graph image' }}</span>
                        <span x-show="url" style="font-size:11px;color:var(--text-faint);">New image selected — click to change</span>
                    </label>
                </div>
                <p style="font-size: 11.5px; color: var(--text-faint); margin-top: 10px;">The image is shown with selectable <strong>A / B / C / D</strong> circles beside it. Leave the A–D option text empty unless you also want to label each row.</p>
            </div>
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
                    @foreach ($difficulties as $d)<option value="{{ $d }}" @selected(old('difficulty', $question->difficulty) === $d)>{{ ucfirst($d) }}</option>@endforeach
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
