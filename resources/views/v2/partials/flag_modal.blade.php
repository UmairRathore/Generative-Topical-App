{{-- Teacher "report a wrong question" modal. Shared by the exam preview and the
     question gallery. Opened by dispatching a window event carrying the flag
     POST url + a human label:
       $dispatch('flag-question', { action: '<route>', label: '9702 · Q12' })
     Reason is required; note + screenshot are optional. A screenshot can be
     pasted straight from the clipboard (Ctrl/⌘+V) with a live preview. Never
     rendered on student-facing pages, so students never see a flag control. --}}
@php $flagReasons = \App\Models\V2\QuestionFlag::REASONS; @endphp
<div x-data="{
        open: false, action: '', label: '', qid: null, previewUrl: '', submitting: false, error: '', flash: '',
        start(e){ this.action = e.detail.action; this.label = e.detail.label || ''; this.qid = e.detail.id ?? null; this.error = ''; this.clearPreview(); this.open = true;
                  this.$nextTick(() => { this.$refs.form && this.$refs.form.reset(); }); },
        close(){ this.open = false; this.clearPreview(); },
        submitFlag(form){
            if (this.submitting) return;
            this.submitting = true; this.error = '';
            fetch(this.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
                .then(r => r.ok ? r.json() : r.json().then(e => Promise.reject(e)).catch(() => Promise.reject({})))
                .then(d => {
                    var id = this.qid;
                    this.close();
                    // Let the host page react (e.g. swap the pulled question out of a preview) — no reload, state preserved.
                    window.dispatchEvent(new CustomEvent('question-flagged', { detail: { id: id } }));
                    this.showFlash((d && d.message) || 'Reported and pulled from the pool for review.');
                })
                .catch(e => { this.error = (e && e.message) || 'Could not submit the report. Please try again.'; })
                .finally(() => { this.submitting = false; });
        },
        showFlash(msg){ this.flash = msg; clearTimeout(this._ft); this._ft = setTimeout(() => { this.flash = ''; }, 4000); },
        onPaste(e){
            if (! this.open) return;
            var items = (e.clipboardData && e.clipboardData.items) || [];
            for (var i = 0; i < items.length; i++){
                if (items[i].type && items[i].type.indexOf('image') === 0){
                    var blob = items[i].getAsFile();
                    if (blob){ this.setFile(blob); e.preventDefault(); }
                    return;
                }
            }
        },
        onFile(e){ var f = e.target.files && e.target.files[0]; if (f) this.accept(f); else this.clearPreview(); },
        setFile(blob){ this.accept(new File([blob], 'pasted-screenshot.png', { type: blob.type || 'image/png' })); },
        // One image, image-only, client-compressed, ≤3 MB — same limits as the student flag.
        async accept(file){
            this.error = '';
            if (! file) return;
            if (! file.type.startsWith('image/')) { this.error = 'Please choose an image file.'; this.clearShot(); return; }
            var out = await this.compress(file);
            if (out.size > 3 * 1024 * 1024) { this.error = 'Image is too large (max 3 MB).'; this.clearShot(); return; }
            var dt = new DataTransfer(); dt.items.add(out);
            this.$refs.shot.files = dt.files;
            this.showPreview(out);
        },
        async compress(file){
            if (! /image\/(jpeg|png|webp)/.test(file.type) || file.size <= 1.5 * 1024 * 1024) return file;
            try {
                var img = await createImageBitmap(file);
                var max = 1600, w = img.width, h = img.height;
                if (w > max || h > max) { var s = Math.min(max / w, max / h); w = Math.round(w * s); h = Math.round(h * s); }
                var c = document.createElement('canvas'); c.width = w; c.height = h;
                c.getContext('2d').drawImage(img, 0, 0, w, h);
                var blob = await new Promise(function (res) { c.toBlob(res, 'image/jpeg', 0.82); });
                return (blob && blob.size < file.size) ? new File([blob], (file.name || 'screenshot').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
            } catch (e) { return file; }
        },
        showPreview(f){ this.clearPreview(); this.previewUrl = URL.createObjectURL(f); },
        clearPreview(){ if (this.previewUrl){ URL.revokeObjectURL(this.previewUrl); } this.previewUrl = ''; },
        clearShot(){ if (this.$refs.shot) this.$refs.shot.value = ''; this.clearPreview(); }
     }"
     @flag-question.window="start($event)"
     @paste.window="onPaste($event)"
     @keydown.escape.window="close()">
<style>
    .flag-overlay{position:fixed;inset:0;z-index:70;background:rgba(0,0,0,.5);display:flex;justify-content:center;align-items:center;padding:24px 16px;overflow-y:auto;}
    .flag-panel{display:flex;flex-direction:column;background:var(--bg);border-radius:var(--r-lg);width:100%;max-width:480px;max-height:calc(100vh - 48px);box-shadow:0 24px 60px rgba(0,0,0,.4);overflow:hidden;}
    .flag-head{flex:none;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:16px 20px;background:var(--surface);border-bottom:1px solid var(--border);}
    .flag-form{flex:1;min-height:0;display:flex;flex-direction:column;}
    .flag-body{flex:1;min-height:0;overflow-y:auto;padding:18px 20px;}
    .flag-foot{flex:none;display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border);background:var(--surface);}
    .flag-lbl{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);margin-bottom:5px;}
    .flag-field{width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);}
    .flag-tip{font-size:11.5px;line-height:1.6;color:var(--text-soft);background:var(--soft-surface);border:1px solid var(--border);border-radius:8px;padding:9px 11px;margin-bottom:10px;}
    .flag-tip kbd{font-family:inherit;font-size:11px;font-weight:600;background:var(--bg);border:1px solid var(--border);border-radius:5px;padding:1px 6px;}
    .flag-shot-preview{position:relative;margin-top:10px;border:1px solid var(--border);border-radius:8px;overflow:hidden;background:var(--soft-surface);}
    .flag-shot-preview img{display:block;max-width:100%;max-height:220px;margin:0 auto;}
    .flag-shot-clear{position:absolute;top:6px;right:6px;border:0;border-radius:6px;background:rgba(0,0,0,.6);color:#fff;cursor:pointer;padding:4px;display:inline-flex;}
    .flag-err{font-size:12.5px;color:var(--bad);background:var(--bad-soft,rgba(200,60,60,.1));border:1px solid var(--bad);border-radius:8px;padding:8px 11px;margin-bottom:12px;}
    .flag-toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%);z-index:80;display:flex;align-items:center;gap:8px;background:var(--primary,#061C30);color:#fff;font-size:13px;font-weight:600;padding:12px 18px;border-radius:99px;box-shadow:0 12px 32px rgba(0,0,0,.28);max-width:min(92vw,460px);}
</style>
    {{-- Teleport to <body>: the teacher layout's .fade-in wrapper carries a
         transform, which would otherwise trap this position:fixed overlay inside
         the content column and push it off-screen. --}}
    <template x-teleport="body">
    <div class="flag-overlay" x-show="open" x-cloak @click.self="close()" style="display:none;">
        <div class="flag-panel">
            <div class="flag-head">
                <div>
                    <div style="font-size:15px;font-weight:700;display:flex;align-items:center;gap:7px;"><x-icon name="flag" size="15"/> Report this question</div>
                    <div style="font-size:12px;color:var(--text-faint);margin-top:3px;" x-text="label"></div>
                </div>
                <button type="button" @click="close()" aria-label="Close" style="border:0;background:none;cursor:pointer;color:var(--text-soft);padding:5px;"><x-icon name="x" size="18"/></button>
            </div>
            <form method="POST" :action="action" enctype="multipart/form-data" x-ref="form" class="flag-form" @submit.prevent="submitFlag($event.target)">
                @csrf
                <div class="flag-body">
                    <p style="font-size:12.5px;color:var(--text-soft);line-height:1.5;margin-bottom:14px;">
                        This pulls the question from the pool right away and sends it to the admin team to fix. Students won't see it.
                    </p>

                    <div class="flag-err" x-show="error" x-text="error" x-cloak></div>

                    <div style="margin-bottom:14px;">
                        <label class="flag-lbl">What's wrong?</label>
                        <select name="reason" required class="flag-field">
                            <option value="" disabled selected>Choose a reason…</option>
                            @foreach ($flagReasons as $value => $text)
                                <option value="{{ $value }}">{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label class="flag-lbl">Details <span style="text-transform:none;letter-spacing:0;font-weight:400;">— optional</span></label>
                        <textarea name="note" rows="3" maxlength="1000" class="flag-field" placeholder="e.g. the diagram is cut off on the right edge"></textarea>
                    </div>

                    <div>
                        <label class="flag-lbl">Screenshot <span style="text-transform:none;letter-spacing:0;font-weight:400;">— optional · one image, max 3 MB</span></label>
                        <div class="flag-tip">
                            Capture, then paste it here with <kbd>Ctrl</kbd> + <kbd>V</kbd> (<kbd>⌘</kbd> + <kbd>V</kbd> on Mac).<br>
                            <strong>Windows:</strong> <kbd>⊞ Win</kbd> + <kbd>Shift</kbd> + <kbd>S</kbd> &nbsp;·&nbsp; <strong>Mac:</strong> <kbd>⌘</kbd> + <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>4</kbd>
                        </div>
                        <input type="file" name="screenshot" accept="image/*" class="flag-field" style="padding:7px 10px;" x-ref="shot" @change="onFile($event)">
                        <div class="flag-shot-preview" x-show="previewUrl" x-cloak>
                            <button type="button" class="flag-shot-clear" @click="clearShot()" aria-label="Remove screenshot"><x-icon name="x" size="14"/></button>
                            <img :src="previewUrl" alt="Screenshot preview">
                        </div>
                    </div>
                </div>

                <div class="flag-foot">
                    <button type="button" class="btn btn-ghost btn-sm" @click="close()" :disabled="submitting">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="submitting"><x-icon name="flag" size="13"/> <span x-text="submitting ? 'Submitting…' : 'Submit report'"></span></button>
                </div>
            </form>
        </div>
    </div>
    </template>

    {{-- Success toast — survives the modal closing; no page reload. --}}
    <template x-teleport="body">
        <div class="flag-toast" x-show="flash" x-transition x-cloak><x-icon name="check" size="15"/> <span x-text="flash"></span></div>
    </template>
</div>
