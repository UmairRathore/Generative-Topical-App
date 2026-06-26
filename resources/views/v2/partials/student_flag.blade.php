{{-- Student "report a problem" control, shown under a question on the exam-taking
     and result-review screens. Posts via fetch (multipart) so it never submits the
     exam form or reloads the page (timer keeps running, answers preserved). It is a
     SOFT flag: the question stays live and the report is routed to the exam's teacher.
     One optional screenshot (image-only, client-compressed, ≤3 MB) - pick a file or
     paste it (Ctrl/⌘+V) while the report form is focused. Great for cropped diagrams,
     missing symbols, broken rendering, mobile display bugs.
     Props: $exam, $q (models). Gate inclusion to the student guard at the callsite. --}}
@props(['exam', 'q'])

@php
    // Per-question flag state for THIS student, server-side so it survives a refresh.
    // Fetched once per request (memoised), not once per question:
    //   open      → already reported, pending the teacher
    //   dismissed → the teacher reviewed it and kept the question (no re-report)
    //   voided    → the question was removed from scoring (no re-report, anyone)
    $__sid = auth('v2_student')->id();
    $__key = 'student_flag_state_'.$exam->id;
    if (app()->bound($__key)) {
        [$__map, $__review] = app($__key);
    } else {
        $__map = [];
        $__reviewIds = [];
        foreach (\App\Models\V2\QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)->where('flagged_by_student_id', $__sid)
            ->get(['question_id', 'status', 'quality_review_id']) as $fl) {
            $__map[(int) $fl->question_id] = $fl->status;
            if ($fl->quality_review_id) {
                $__reviewIds[(int) $fl->question_id] = $fl->quality_review_id;
            }
        }
        // A voided question is locked for everyone, regardless of who reported it -
        // but don't overwrite a more specific status the reporter already has (e.g.
        // 'escalated', so they see the "under review" message, not the generic one).
        foreach (\Illuminate\Support\Facades\DB::table('v2_exam_questions')
            ->where('exam_id', $exam->id)->where('is_voided', true)->pluck('question_id') as $vq) {
            $__map[(int) $vq] ??= 'voided';
        }
        // Phase 4.1: the DECIDED Support quality-review outcome linked to this
        // student's report, so a resolved report shows the real outcome (read-only).
        $__review = [];
        if ($__reviewIds) {
            $__decided = \App\Models\V2\QualityReview::whereIn('id', array_values($__reviewIds))
                ->where('status', 'decided')->get()->keyBy('id');
            foreach ($__reviewIds as $__qid => $__rid) {
                if ($__r = $__decided->get($__rid)) {
                    $__review[$__qid] = ['outcome' => $__r->outcome, 'prop' => $__r->propagation_status];
                }
            }
        }
        app()->instance($__key, [$__map, $__review]);
    }

    $lockState = $__map[(int) $q->id] ?? null;
    $lockMessages = [
        'open'      => "You've already reported this question - your teacher will review it.",
        'dismissed' => 'Your teacher has reviewed this report and decided to keep the question. If you still believe there is an issue, please discuss it with your teacher. Your teacher or school administration can escalate the question for quality review if further review is required.',
        'escalated' => "Your teacher has escalated this question for quality review. You'll see any change to your result if it's corrected or removed.",
        'voided'    => 'This question is under review and has been excluded from scoring for this exam.',
    ];
    $lockMessage = $lockState ? ($lockMessages[$lockState] ?? "You've already reported this question.") : null;

    // Phase 4.1: tailored Quality Review outcome message - shown as a SEPARATE
    // status line once Support has decided the review. The exclusion badge above
    // (in answer_review) stays provenance-based and is untouched.
    $qr = $__review[(int) $q->id] ?? null;
    $qrMsg = $qr ? match (true) {
        $qr['outcome'] === 'correct'  => 'Quality Review Outcome: Correct - Matches Source. This question was reviewed and confirmed to accurately match the official Cambridge source.',
        $qr['outcome'] === 'cosmetic' => 'Quality Review Outcome: Non-material Improvement. This question was reviewed and improved for future exams. Your score was not changed because the issue did not affect the meaning of the question.',
        $qr['outcome'] === 'material' && $qr['prop'] === 'propagated' => 'Quality Review Outcome: Material Representation Error Confirmed. This question was reviewed and corrected because its digital version did not accurately match the official Cambridge paper and mark scheme. Historical affected exams were updated where required.',
        $qr['outcome'] === 'material' => 'Quality Review Outcome: Material Representation Error Confirmed. This question has been corrected. Historical propagation is pending.',
        default => null,
    } : null;
@endphp

<div x-data="studentFlag('{{ route('v2.student.exams.flag', [$exam, $q]) }}')" style="margin-top: 12px; border-top: 1px dashed var(--border); padding-top: 10px;">
@if ($qrMsg)
    {{-- Support has decided the quality review - show the outcome to the reporter. --}}
    <div style="display: flex; gap: 8px; align-items: flex-start; font-size: 11.5px; color: var(--text-soft); line-height: 1.55; background: var(--soft-surface); border: 1px solid var(--border); border-radius: 8px; padding: 9px 11px;">
        <x-icon name="check" size="13" style="flex: none; margin-top: 1px; color: var(--ok);"/>
        <span>{{ $qrMsg }}</span>
    </div>
@elseif (in_array($lockState, ['dismissed', 'escalated'], true))
    {{-- Locked for this student; the escalation path is via the teacher/admin, not a re-report. --}}
    <div style="display: flex; gap: 8px; align-items: flex-start; font-size: 11.5px; color: var(--text-soft); line-height: 1.55; background: var(--soft-surface); border: 1px solid var(--border); border-radius: 8px; padding: 9px 11px;">
        <x-icon name="check" size="13" style="flex: none; margin-top: 1px; color: var(--ok);"/>
        <span>{{ $lockMessage }}</span>
    </div>
@elseif ($lockMessage)
    <span style="font-size: 11.5px; color: {{ $lockState === 'voided' ? 'var(--text-soft)' : 'var(--ok)' }}; display: inline-flex; align-items: center; gap: 5px;">
        <x-icon name="{{ $lockState === 'voided' ? 'flag' : 'check' }}" size="12"/> {{ $lockMessage }}
    </span>
@else
    <span x-show="reported" x-cloak style="font-size: 11.5px; color: var(--ok); display: inline-flex; align-items: center; gap: 5px;">
        <x-icon name="check" size="12"/> You've already reported this question - your teacher will review it.
    </span>

    <button type="button" @click="open = !open" x-show="!reported" x-cloak
            style="background: none; border: 0; color: var(--text-faint); font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; padding: 0;">
        <x-icon name="flag" size="12"/> <span x-text="open ? 'Cancel report' : 'Report a problem'"></span>
    </button>

    <div x-show="open && !reported" x-cloak @paste="onPaste($event)" tabindex="0"
         style="margin-top: 10px; padding: 12px; border: 1px solid var(--border); border-radius: 9px; background: var(--soft-surface); outline: none;">
        <div style="display: flex; gap: 7px; align-items: flex-start; font-size: 11.5px; color: var(--text-soft); background: var(--surface); border: 1px solid var(--border); border-radius: 7px; padding: 8px 10px; margin-bottom: 10px;">
            <x-icon name="eye" size="13" style="flex: none; margin-top: 1px;"/>
            <span>Reporting <strong>doesn't</strong> submit, skip, or change this question - please still choose your answer. Your teacher reviews the report separately.</span>
        </div>
        <select x-model="reason" style="width: 100%; padding: 8px 10px; font-size: 12.5px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--text); margin-bottom: 8px;">
            <option value="">What's wrong with this question?</option>
            @foreach (\App\Models\V2\QuestionFlag::REASONS as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
        <textarea x-model="note" rows="2" maxlength="1000" placeholder="Add any detail (optional)"
                  style="width: 100%; padding: 8px 10px; font-size: 12.5px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--text); resize: vertical; margin-bottom: 8px;"></textarea>

        {{-- Optional screenshot: attach OR paste; thumbnail preview with remove --}}
        <div style="margin-bottom: 8px;">
            <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" @change="onFile($event)" x-ref="file" style="display: none;">

            <div class="flex items-start" style="gap: 10px;">
                <button type="button" @click="$refs.file.click()" x-show="!previewUrl"
                        style="background: var(--surface); border: 1px dashed var(--border); border-radius: 7px; color: var(--text-soft); font-size: 11.5px; cursor: pointer; padding: 7px 10px; display: inline-flex; align-items: center; gap: 6px;">
                    <x-icon name="upload" size="12"/> Attach a screenshot (optional)
                </button>

                <div x-show="previewUrl" x-cloak style="position: relative; flex: none;">
                    <img :src="previewUrl" alt="screenshot preview" style="width: 78px; height: 78px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); display: block;">
                    <button type="button" @click="clearFile()" aria-label="Remove screenshot"
                            style="position: absolute; top: -7px; right: -7px; width: 20px; height: 20px; border-radius: 50%; border: 0; background: var(--bad); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(0,0,0,.3);">
                        <x-icon name="x" size="12"/>
                    </button>
                </div>
                <span x-show="previewUrl" x-cloak x-text="fileSize" style="font-size: 11px; color: var(--text-faint); align-self: center;"></span>
            </div>

            <div style="font-size: 10.5px; line-height: 1.6; color: var(--text-faint); margin-top: 7px;">
                Capture, then paste here with <strong>Ctrl</strong>+<strong>V</strong> (<strong>⌘</strong>+<strong>V</strong> on Mac).
                <br>Windows: <strong>⊞ Win</strong>+<strong>Shift</strong>+<strong>S</strong> · Mac: <strong>⌘</strong>+<strong>Ctrl</strong>+<strong>Shift</strong>+<strong>4</strong>
            </div>
        </div>

        <div class="flex items-center" style="gap: 8px;">
            <button type="button" @click="submit()" :disabled="!reason || state === 'sending'" class="btn btn-primary btn-sm">
                <x-icon name="flag" size="12"/> <span x-text="state === 'sending' ? 'Sending…' : 'Send report'"></span>
            </button>
            <span x-show="error" x-text="error" x-cloak style="font-size: 11.5px; color: var(--bad);"></span>
        </div>
    </div>
@endif
</div>

@once
    <span id="v2-student-csrf" data-token="{{ csrf_token() }}" style="display:none;"></span>
    <script>
        function studentFlag(url, reported) {
            return {
                open: false, reported: !!reported, reason: '', note: '', state: '', error: '',
                file: null, fileName: '', fileSize: '', previewUrl: '',
                clearFile() {
                    this.file = null; this.fileName = ''; this.fileSize = '';
                    if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); this.previewUrl = ''; }
                    if (this.$refs.file) this.$refs.file.value = '';
                },
                async accept(f) {
                    this.error = '';
                    if (!f) return;
                    if (!f.type.startsWith('image/')) { this.error = 'Please choose an image file.'; return; }
                    const out = await this.compress(f);
                    if (out.size > 3 * 1024 * 1024) { this.error = 'Image is too large (max 3 MB).'; this.clearFile(); return; }
                    if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                    this.file = out;
                    this.fileName = f.name || 'screenshot';
                    this.fileSize = Math.max(1, Math.round(out.size / 1024)) + ' KB';
                    this.previewUrl = URL.createObjectURL(out);
                },
                onFile(e) { const f = e.target.files && e.target.files[0]; if (f) this.accept(f); },
                onPaste(e) {
                    if (!this.open) return;
                    const items = (e.clipboardData && e.clipboardData.items) || [];
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type && items[i].type.indexOf('image') === 0) {
                            const blob = items[i].getAsFile();
                            if (blob) { this.accept(new File([blob], 'pasted-screenshot.png', { type: blob.type || 'image/png' })); e.preventDefault(); }
                            return;
                        }
                    }
                },
                // Downscale + re-encode big images so phone photos comfortably fit the limit.
                async compress(file) {
                    if (!/image\/(jpeg|png|webp)/.test(file.type) || file.size <= 1.5 * 1024 * 1024) return file;
                    try {
                        const img = await createImageBitmap(file);
                        const max = 1600;
                        let w = img.width, h = img.height;
                        if (w > max || h > max) { const s = Math.min(max / w, max / h); w = Math.round(w * s); h = Math.round(h * s); }
                        const c = document.createElement('canvas'); c.width = w; c.height = h;
                        c.getContext('2d').drawImage(img, 0, 0, w, h);
                        const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', 0.82));
                        return (blob && blob.size < file.size)
                            ? new File([blob], (file.name || 'screenshot').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' })
                            : file;
                    } catch (e) { return file; }
                },
                async submit() {
                    if (!this.reason || this.state === 'sending') return;
                    this.state = 'sending'; this.error = '';
                    try {
                        const token = document.getElementById('v2-student-csrf')?.dataset.token
                            || document.querySelector('input[name=_token]')?.value || '';
                        const fd = new FormData();
                        fd.set('reason', this.reason);
                        fd.set('note', this.note || '');
                        if (this.file) fd.set('screenshot', this.file, this.file.name);
                        const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: fd });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.ok) { this.clearFile(); this.state = ''; this.reported = true; this.open = false; }
                        else { this.state = ''; this.error = j.message || 'Could not send. Try again.'; }
                    } catch (e) { this.state = ''; this.error = 'Network error. Try again.'; }
                },
            };
        }
    </script>
@endonce
