@extends('v2.layouts.student')
@section('page_title', $exam->title)

@php
    // Elapsed/remaining derived from the attempt's started_at so the clock is
    // correct across page reloads (it does not restart).
    $elapsed = (int) abs($attempt->started_at?->diffInSeconds(now()) ?? 0);
    $hasLimit = (bool) $exam->duration_minutes;
    $remaining = $hasLimit ? max(0, $exam->duration_minutes * 60 - $elapsed) : null;
@endphp

@section('content')
<style>[x-cloak]{display:none!important}.tx-modal{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px}</style>
<div x-data="examTaker({{ $exam->question_count }}, {{ $hasLimit ? 'true' : 'false' }}, {{ $remaining ?? 'null' }}, {{ $elapsed }})" style="max-width: 760px; margin: 0 auto;">

    {{-- Sticky status bar --}}
    <div class="flex items-center justify-between" style="position: sticky; top: 64px; z-index: 5; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 18px; margin-bottom: 20px;">
        <div>
            <div style="font-size: 15px; font-weight: 600;">{{ $exam->title }}</div>
            <div style="font-size: 12px; color: var(--text-faint);">{{ $exam->topic?->title ?? 'Mixed' }} · {{ $exam->subject?->name }}</div>
        </div>
        <div class="flex items-center gap-4">
            <div style="font-size: 12.5px; color: var(--text-soft);"><span x-text="Object.keys(answers).length"></span> / {{ $exam->question_count }} answered</div>
            <div class="badge {{ $hasLimit ? 'badge-review' : 'badge-soft' }}" style="font-size: 13px;" title="{{ $hasLimit ? 'Time left' : 'Time on test' }}">
                <x-icon name="clock" size="13"/> <span x-text="clock"></span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('v2.student.exams.submit', $exam) }}" x-ref="form"
          @submit.prevent="showConfirm = true">
        @csrf

        @foreach ($exam->examQuestions as $eq)
            @php
                $q = $eq->question;
                $optionImages = $q->images->where('role', 'option_image')->keyBy('option_label');
            @endphp
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px; margin-bottom: 16px;">
                <div class="flex items-start gap-3">
                    <span class="badge badge-emerald" style="flex: none; font-weight: 700;">{{ $eq->sort_order }}</span>
                    <div style="flex: 1; min-width: 0;">
                        @include('v2.partials.question_stem', ['q' => $q])

                        @include('v2.partials.options_divider', ['q' => $q])

                        @php
                            // Safety net for any unfixed mis-tagged question (see
                            // Question::collapsedOptionFigures): show the figure(s)
                            // once and fall back to plain lettered options.
                            $collapsed = $q->collapsedOptionFigures();
                            if ($collapsed) { $optionImages = collect(); }
                        @endphp
                        @if ($collapsed)
                            @foreach ($collapsed as $fig)
                                @php $cw = $fig->displayWidth(); @endphp
                                <div class="v2-figure-wrap">
                                    <img src="{{ simg($fig->image_path) }}" alt="diagram" loading="lazy" onerror="this.style.display='none'"
                                         @if ($cw) style="width:{{ $cw }}px;" @endif>
                                </div>
                            @endforeach
                        @endif

                        @php
                            $hasOptImgs = $optionImages->isNotEmpty();
                            // option_table: the table above shows each row's content, so the
                            // choices are just selectable letters (the option text is a garbled
                            // duplicate of the table and is hidden).
                            $isTable = $q->optionTableImage() !== null;
                        @endphp
                        @if ($isTable)
                            @php $optTable = $q->optionTableImage(); $tw = $optTable?->displayWidth(); @endphp
                            <div class="v2-table-pick">
                                <div class="picks">
                                    <span class="hdr"></span>
                                    @foreach ($q->options as $opt)
                                        <label>
                                            <input type="radio" name="answers[{{ $q->id }}]" value="{{ $opt->label }}" x-model="answers['{{ $q->id }}']" style="position:absolute; left:-9999px;">
                                            <span :class="answers['{{ $q->id }}']==='{{ $opt->label }}' ? 'v2-opt-circle is-on' : 'v2-opt-circle'">{{ $opt->label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="v2-table-wrap">
                                    <img src="{{ simg($optTable->image_path) }}" alt="options table" loading="lazy" onerror="this.style.display='none'"
                                         @if ($tw) style="width:{{ $tw }}px;" @endif>
                                </div>
                            </div>
                        @else
                        <div class="{{ $hasOptImgs ? 'v2-opt-grid' : 'space-y-2' }}">
                            @foreach ($q->options as $opt)
                                @php $oi = $optionImages[$opt->label] ?? null; @endphp
                                @if ($oi)
                                    {{-- image option: card in the 2-up grid, lettered circle, borderless image --}}
                                    <label style="cursor: pointer; display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid var(--border); border-radius: 10px; transition: all .12s;"
                                           :style="answers['{{ $q->id }}']==='{{ $opt->label }}' ? 'border-color: var(--emerald-700); background: var(--emerald-50);' : ''">
                                        <input type="radio" name="answers[{{ $q->id }}]" value="{{ $opt->label }}" x-model="answers['{{ $q->id }}']" style="position:absolute; left:-9999px;">
                                        <span :class="answers['{{ $q->id }}']==='{{ $opt->label }}' ? 'v2-opt-circle is-on' : 'v2-opt-circle'">{{ $opt->label }}</span>
                                        <img src="{{ simg($oi->image_path) }}" alt="option {{ $opt->label }}" loading="lazy" onerror="this.style.display='none'" class="v2-opt-img">
                                    </label>
                                @else
                                    <label class="flex items-center gap-3" style="cursor: pointer; padding: 11px 14px; border: 1px solid var(--border); border-radius: 9px; transition: all .12s;"
                                           :style="answers['{{ $q->id }}']==='{{ $opt->label }}' ? 'border-color: var(--emerald-700); background: var(--emerald-50);' : ''">
                                        <input type="radio" name="answers[{{ $q->id }}]" value="{{ $opt->label }}" x-model="answers['{{ $q->id }}']" style="position:absolute; left:-9999px;">
                                        <span :class="answers['{{ $q->id }}']==='{{ $opt->label }}' ? 'v2-opt-circle is-on' : 'v2-opt-circle'">{{ $opt->label }}</span>
                                        @if (trim((string) $opt->text) !== '')
                                            <span style="font-size: 14px;">{{ $opt->text }}</span>
                                        @endif
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        @endif

                        @include('v2.partials.student_flag', ['exam' => $exam, 'q' => $q])
                    </div>
                </div>
            </div>
        @endforeach

        <div class="flex items-center justify-between" style="margin: 22px 0 40px;">
            <a href="{{ route('v2.student.exams.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none;">Save & exit</a>
            <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" size="15"/> Submit Test</button>
        </div>
    </form>

    {{-- Submit confirmation modal (replaces the native confirm dialog) - teleported so the
         content column's transform can't trap this fixed overlay off-screen. --}}
    <template x-teleport="body">
    <div x-show="showConfirm" x-cloak class="tx-modal" style="display:none;" @keydown.escape.window="showConfirm = false">
        <div @click="showConfirm = false" style="position: absolute; inset: 0; background: rgba(0,0,0,.55);"></div>
        <div x-show="showConfirm" x-transition
             style="position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 24px; width: 100%; max-width: 420px; box-shadow: 0 24px 64px rgba(0,0,0,.35);">
            <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Submit your test?</h3>
            <p style="font-size: 13px; color: var(--text-soft); margin-bottom: 6px;">
                You've answered <span style="font-weight: 600; color: var(--text);" x-text="Object.keys(answers).length"></span> of {{ $exam->question_count }} questions.
            </p>
            <p style="font-size: 13px; color: var(--text-soft); margin-bottom: 22px;">Once submitted, you can't change your answers.</p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" class="btn btn-ghost" @click="showConfirm = false">Keep working</button>
                <button type="button" class="btn btn-primary" @click="confirming = true; $refs.form.submit()"><x-icon name="check" size="14"/> Submit test</button>
            </div>
        </div>
    </div>
    </template>
</div>

@if ($periodicTable)
    {{-- Periodic Table reference: a floating button that opens a centered modal (desktop + mobile).
         Chemistry exams only. Teleported to <body> so the content column's transform can't off-center it. --}}
    <style>
        [x-cloak]{display:none!important}
        .pt-fab{position:fixed;bottom:22px;right:22px;z-index:46;display:inline-flex;align-items:center;gap:7px;padding:11px 16px;border-radius:99px;border:0;background:var(--primary,#061C30);color:#fff;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 6px 22px rgba(0,0,0,.22);}
        .pt-modal{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px;}
        .pt-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55);}
        .pt-card{position:relative;background:var(--surface);border:1px solid var(--border);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;width:min(1100px,96vw);max-height:90vh;box-shadow:0 24px 64px rgba(0,0,0,.4);}
        .pt-head{display:flex;align-items:center;justify-content:space-between;padding:11px 14px;border-bottom:1px solid var(--border);flex:none;}
        .pt-close{border:0;background:none;cursor:pointer;color:var(--text-soft);padding:4px;display:inline-flex;}
        .pt-body{overflow:auto;padding:12px;flex:1;background:var(--soft-surface);-webkit-overflow-scrolling:touch;}
        .pt-body img{display:block;max-width:100%;height:auto;margin:0 auto;background:#fff;border-radius:6px;}
    </style>
    <div x-data="{ open: false }" x-cloak @keydown.escape.window="open = false">
        {{-- Teleported to <body> so the floating button stays pinned to the viewport
             (the content column's transform would otherwise trap this fixed button). --}}
        <template x-teleport="body">
        <button type="button" class="pt-fab" @click="open = true" x-show="!open" x-transition.opacity x-cloak>
            <x-icon name="grid" size="16"/> Periodic Table
        </button>
        </template>
        <template x-teleport="body">
        <div class="pt-modal" x-show="open" x-cloak style="display:none;">
            <div class="pt-backdrop" @click="open = false"></div>
            <div class="pt-card" x-show="open" x-transition>
                <div class="pt-head">
                    <span style="font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:7px;"><x-icon name="grid" size="14"/> Periodic Table</span>
                    <button type="button" class="pt-close" @click="open = false" aria-label="Close"><x-icon name="x" size="16"/></button>
                </div>
                <div class="pt-body">
                    <img src="{{ $periodicTable }}" alt="Periodic Table of the Elements">
                </div>
            </div>
        </div>
        </template>
    </div>
@endif

<script>
function examTaker(total, hasLimit, remaining, elapsed) {
    return {
        total, answers: {}, confirming: false, showConfirm: false, hasLimit, remaining, elapsed, clock: '',
        init() {
            if (this.hasLimit && this.remaining <= 0) { this.autoSubmit(); return; }
            this.render();
            setInterval(() => this.tick(), 1000);
        },
        render() {
            const v = this.hasLimit ? this.remaining : this.elapsed;
            const m = Math.floor(v / 60), s = v % 60;
            this.clock = m + ':' + String(s).padStart(2, '0');
        },
        tick() {
            if (this.hasLimit) {
                if (this.remaining <= 0) { this.autoSubmit(); return; }
                this.remaining--;
            } else {
                this.elapsed++;
            }
            this.render();
        },
        autoSubmit() { this.confirming = true; this.$refs.form.submit(); },
    };
}
</script>
@endsection
