@extends('v2.layouts.teacher')
@section('page_title', 'Create a test')

@php
    $fld = 'width: 100%; padding: 10px 13px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);';
    $lbl = 'display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;';
@endphp

@section('content')
<style>
    .ex-wrap{width:100%;}
    /* The form/header stay in a readable centered column; the preview gallery below breaks out to full content width. */
    .ex-col{max-width:760px;margin:0 auto;}
    .ex-tabs{display:flex;gap:0;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:20px;}
    .ex-tabs a{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;font-size:13.5px;font-weight:600;text-decoration:none;color:var(--text-soft);background:var(--surface);}
    .ex-tabs a.on{background:var(--primary,#061C30);color:#fff;}
    .ex-tabs a + a{border-left:1px solid var(--border);}
    /* Config form: reliable vertical rhythm (the V2 layout has no Tailwind space-y). */
    .gen-form{display:flex;flex-direction:column;gap:18px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:26px;}
    .gen-row2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    @media (max-width:560px){.gen-row2{grid-template-columns:1fr;}}
    .ms-control{min-height:44px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:7px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg);cursor:text;}
    .ms-control.open{border-color:var(--accent);}
    .ms-chip{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:3px 6px 3px 9px;border-radius:99px;background:var(--ok-soft,rgba(95,160,82,.14));color:var(--ok);}
    .ms-chip button{border:0;background:none;cursor:pointer;color:inherit;font-size:14px;line-height:1;padding:0;}
    .ms-drop{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:30;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 10px 28px rgba(0,0,0,.14);max-height:260px;display:flex;flex-direction:column;overflow:hidden;}
    .ms-opt{padding:9px 12px;font-size:13px;cursor:pointer;}
    .ms-opt:hover{background:var(--soft-surface);}
    [x-cloak]{display:none!important;}
    /* Inline preview */
    .gen-bar{position:sticky;bottom:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:0 -6px 24px rgba(0,0,0,.08);padding:13px 18px;margin-top:10px;}
    /* Gallery cards (shared markup with the custom drawer) */
    .selq-card{border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface);overflow:hidden;margin-bottom:12px;}
    /* Preview as a responsive gallery grid (matches the custom page), not a single-column paper. */
    .gen-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px;align-items:start;}
    .gen-gallery .selq-card{margin-bottom:0;}
    @media(max-width:560px){.gen-gallery{grid-template-columns:1fr;}}
    .selq-card.is-chosen{border-color:var(--accent,#0097d3);box-shadow:0 0 0 1px var(--accent,#0097d3) inset;}
    .selq-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 14px;border-bottom:1px solid var(--border);background:var(--bg);}
    .selq-body{padding:14px 16px;}
    .sel-num{flex:none;width:22px;height:22px;border-radius:99px;background:var(--soft-surface);color:var(--text-soft);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;}
    .gen-pick{width:18px;height:18px;flex:none;cursor:pointer;accent-color:var(--accent,#0097d3);}
    .sel-swap{flex:none;display:inline-flex;align-items:center;gap:5px;border:1px solid var(--accent,#0097d3);background:transparent;color:var(--accent,#0097d3);font-size:12px;font-weight:600;padding:4px 9px;border-radius:7px;cursor:pointer;}
    .sel-swap:hover{background:rgba(var(--accent-rgb,0,151,211),.08);}
    /* Selected sidebar (mirrors the custom-selection drawer) */
    .sel-shell{position:fixed;inset:0;z-index:60;pointer-events:none;}
    .sel-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);opacity:0;transition:opacity .15s;pointer-events:none;}
    .sel-shell.is-open .sel-backdrop{opacity:1;pointer-events:auto;}
    .sel-drawer{position:absolute;top:0;right:0;bottom:0;width:clamp(380px,44vw,600px);display:flex;flex-direction:column;background:var(--bg);border-left:1px solid var(--border);box-shadow:-12px 0 36px rgba(0,0,0,.18);transform:translateX(100%);transition:transform .2s ease;pointer-events:auto;}
    .sel-shell.is-open .sel-drawer{transform:none;}
    .sel-head{flex:none;display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid var(--border);background:var(--surface);}
    .sel-x{border:0;background:none;cursor:pointer;color:var(--text-soft);padding:5px;display:inline-flex;border-radius:7px;}
    .sel-x:hover{background:var(--soft-surface);}
    .sel-body{flex:1;min-height:0;overflow-y:auto;padding:14px 16px;background:var(--soft-surface);}
    .sel-rm{flex:none;border:0;background:none;cursor:pointer;color:var(--text-faint);padding:4px;display:inline-flex;border-radius:6px;}
    .sel-rm:hover{background:var(--bad-soft,rgba(200,60,60,.12));color:var(--bad);}
    .sel-foot{flex:none;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-top:1px solid var(--border);background:var(--surface);}
    @media (max-width:560px){.sel-drawer{width:100%;}}
</style>

<div class="ex-wrap"
     x-data="{
        classes: @js($classMeta),
        classId: '{{ old('class_id', $classes->first()->id ?? '') }}',
        title: @js(old('title', 'Random Test')),
        count: {{ (int) old('question_count', 20) }},
        duration: '{{ old('duration_minutes') }}',
        selected: [],
        open: false,
        search: '',
        pUrl: '{{ route('v2.teacher.exams.generate_preview') }}',
        sUrl: '{{ route('v2.teacher.exams.generate_swap') }}',
        rUrl: '{{ route('v2.teacher.exams.generate_regenerate') }}',
        selUrl: '{{ route('v2.teacher.exams.custom_selected') }}',
        previewOpen: false, previewLoading: false, previewHtml: '', previewIds: [], previewError: '',
        chosen: [],
        panel: false, drawerHtml: '', drawerLoading: false,
        get topics(){ return (this.classId && this.classes[this.classId]) ? this.classes[this.classId].topics : []; },
        get available(){ const q = this.search.toLowerCase(); const ids = this.selected.map(s => s.id); return this.topics.filter(t => ids.indexOf(t.id) === -1 && t.label.toLowerCase().includes(q)); },
        add(t){ this.selected.push(t); this.search = ''; },
        remove(id){ this.selected = this.selected.filter(s => s.id !== id); },
        onClassChange(){ this.selected = []; this.search = ''; this.open = false; },
        params(){ const p = new URLSearchParams(); p.set('class_id', this.classId); this.selected.forEach(t => p.append('topic_ids[]', t.id)); return p; },
        preview(){
            if (! this.classId){ return; }
            this.previewOpen = true; this.previewLoading = true; this.previewError = '';
            const p = this.params(); p.set('question_count', this.count);
            fetch(this.pUrl + '?' + p.toString(), { headers: { 'X-Requested-With': 'fetch' } })
                .then(r => r.ok ? r.json() : r.json().then(e => Promise.reject(e)))
                .then(d => {
                    this.previewIds = d.ids || []; this.previewHtml = d.html || '';
                    this.chosen = [];                              // nothing locked yet - tick the ones you want to keep
                    if (! this.previewIds.length) this.previewError = 'No questions match these topics yet.';
                    this.$nextTick(() => { this.syncChecks(); this.$refs.genPreview && this.$refs.genPreview.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
                })
                .catch(e => { this.previewError = (e && e.message) || 'Could not build a preview.'; })
                .finally(() => { this.previewLoading = false; });
        },
        regenerate(){
            if (! this.previewIds.length || this.chosen.length === this.previewIds.length) return;
            this.previewLoading = true; this.previewError = '';
            const p = this.params();
            this.previewIds.forEach(x => p.append('ids[]', x));
            this.chosen.forEach(x => p.append('keep[]', x));       // ticked questions are locked in place
            fetch(this.rUrl + '?' + p.toString(), { headers: { 'X-Requested-With': 'fetch' } })
                .then(r => r.ok ? r.json() : r.json().then(e => Promise.reject(e)))
                .then(d => {
                    this.previewIds = d.ids || []; this.previewHtml = d.html || '';
                    this.chosen = this.chosen.filter(x => this.previewIds.indexOf(x) > -1);
                    this.$nextTick(() => this.syncChecks());
                    if (this.panel) this.loadDrawer();
                })
                .catch(e => { this.previewError = (e && e.error) || 'Could not regenerate.'; })
                .finally(() => { this.previewLoading = false; });
        },
        swap(id){
            this.previewLoading = true; this.previewError = '';
            const oldIds = this.previewIds.slice();
            const p = this.params(); this.previewIds.forEach(x => p.append('ids[]', x)); p.set('replace', id);
            fetch(this.sUrl + '?' + p.toString(), { headers: { 'X-Requested-With': 'fetch' } })
                .then(r => r.ok ? r.json() : r.json().then(e => Promise.reject(e)))
                .then(d => {
                    const newId = (d.ids || []).find(x => oldIds.indexOf(x) === -1);
                    this.previewIds = d.ids || []; this.previewHtml = d.html || '';
                    if (newId != null) this.chosen = this.chosen.map(x => x === id ? newId : x);  // keep the slot's tick state
                    this.$nextTick(() => this.syncChecks());
                    if (this.panel) this.loadDrawer();
                })
                .catch(e => { this.previewError = (e && e.error) || 'Could not swap that question.'; })
                .finally(() => { this.previewLoading = false; });
        },
        syncChecks(){
            const root = this.$refs.previewList; if (! root) return;
            root.querySelectorAll('[data-pick]').forEach(cb => { cb.checked = this.chosen.indexOf(parseInt(cb.dataset.pick, 10)) > -1; });
            root.querySelectorAll('[data-card]').forEach(c => { c.classList.toggle('is-chosen', this.chosen.indexOf(parseInt(c.dataset.card, 10)) > -1); });
        },
        onPreviewClick(e){ const b = e.target.closest('[data-swap]'); if (b){ this.swap(parseInt(b.dataset.swap, 10)); } },
        onPreviewChange(e){
            const cb = e.target.closest('[data-pick]'); if (! cb) return;
            const id = parseInt(cb.dataset.pick, 10);
            if (cb.checked){ if (this.chosen.indexOf(id) === -1) this.chosen.push(id); }
            else { this.chosen = this.chosen.filter(x => x !== id); }
            const card = this.$refs.previewList.querySelector('[data-card=\'' + id + '\']');
            if (card) card.classList.toggle('is-chosen', cb.checked);
            if (this.panel) this.loadDrawer();
        },
        openPanel(){ this.panel = true; this.loadDrawer(); },
        loadDrawer(){
            if (! this.chosen.length){ this.drawerHtml = ''; return; }
            this.drawerLoading = true;
            fetch(this.selUrl + '?ids=' + this.chosen.join(','), { headers: { 'X-Requested-With': 'fetch' } })
                .then(r => r.text()).then(h => { this.drawerHtml = h; })
                .catch(() => { this.drawerHtml = '<p style=\'padding:16px;color:var(--text-faint);font-size:13px;\'>Could not load the selection.</p>'; })
                .finally(() => { this.drawerLoading = false; });
        },
        onDrawerClick(e){ const b = e.target.closest('[data-remove]'); if (b){ this.removeChosen(parseInt(b.dataset.remove, 10)); } },
        removeChosen(id){ this.chosen = this.chosen.filter(x => x !== id); this.syncChecks(); this.loadDrawer(); },
        clearChosen(){ this.chosen = []; this.syncChecks(); this.drawerHtml = ''; },
        submit(ids){ if (! ids.length) return; this.$refs.qids.value = ids.join(','); this.$refs.genForm.submit(); },
        createSelected(){ this.submit(this.chosen); },
        createAll(){ this.submit(this.previewIds); },
        onFlagged(e){ const id = e.detail && e.detail.id; if (id != null && this.previewIds.indexOf(id) > -1){ this.swap(id); } }
     }"
     @question-flagged.window="onFlagged($event)">
    <div class="ex-col">
    <a href="{{ route('v2.teacher.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
        <x-icon name="chev-l" size="14"/> Back to Exams
    </a>
    <h2 class="serif" style="font-size: 24px; font-weight: 600; margin-bottom: 4px;">Create a test</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-bottom: 20px;">Two ways to build a test from the question bank: let us pick at random, or hand-pick the exact questions yourself.</p>

    <div class="ex-tabs">
        <a href="{{ route('v2.teacher.exams.create') }}" class="on"><x-icon name="sparkle" size="15"/> Random generator</a>
        <a href="{{ route('v2.teacher.exams.custom') }}"><x-icon name="list" size="15"/> Custom selection</a>
    </div>
    </div>{{-- /.ex-col header --}}

    @if ($classes->isEmpty())
        <div class="ex-col"><div style="padding: 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
            You are not assigned to any class yet. Ask your school admin to assign you to a class.
        </div></div>
    @else
        <div class="ex-col">
        <form @submit.prevent="preview()" class="gen-form">
            <div>
                <label style="{{ $lbl }}">Test title</label>
                <input type="text" x-model="title" required maxlength="120" style="{{ $fld }}">
                @error('title') <p style="font-size: 12px; color: var(--bad); margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div class="gen-row2">
                <div>
                    <label style="{{ $lbl }}">Class</label>
                    <select x-model="classId" @change="onClassChange()" required style="{{ $fld }}">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} - {{ $c->subject?->name }}</option>
                        @endforeach
                    </select>
                    @error('class_id') <p style="font-size: 12px; color: var(--bad); margin-top: 4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label style="{{ $lbl }}">Number to draw <span style="font-weight:400;color:var(--text-faint);">(max 40)</span></label>
                    <input type="number" x-model="count" min="1" max="40" required style="{{ $fld }}">
                    <p style="font-size:11.5px;color:var(--text-faint);margin-top:5px;">Tip: draw a few extra, then keep the best ones.</p>
                </div>
            </div>

            <div>
                <label style="{{ $lbl }}">Topics <span style="font-weight:400;color:var(--text-faint);">(pick one or more, or leave empty for a mixed test)</span></label>
                <div style="position: relative;" @click.outside="open = false">
                    <div class="ms-control" :class="open ? 'open' : ''" @click="open = true; $nextTick(() => $refs.msSearch && $refs.msSearch.focus())">
                        <template x-for="t in selected" :key="t.id">
                            <span class="ms-chip">
                                <span x-text="t.label"></span>
                                <button type="button" @click.stop="remove(t.id)">&times;</button>
                            </span>
                        </template>
                        <input type="text" x-ref="msSearch" x-model="search" @focus="open = true" @keydown.backspace="search === '' && selected.length && remove(selected[selected.length-1].id)"
                               :placeholder="selected.length ? '' : (topics.length ? 'Select topics…' : 'No tagged topics for this subject')"
                               :disabled="!topics.length"
                               style="flex:1; min-width:120px; border:0; outline:none; background:transparent; font-size:13.5px; color:var(--text); padding:3px 2px;">
                    </div>

                    <div class="ms-drop" x-show="open && topics.length" x-cloak>
                        <div style="overflow-y:auto;">
                            <template x-for="t in available" :key="t.id">
                                <div class="ms-opt" @click="add(t); $refs.msSearch.focus()" x-text="t.label"></div>
                            </template>
                            <template x-if="available.length === 0">
                                <div style="padding:10px 12px; font-size:12.5px; color:var(--text-faint);" x-text="selected.length === topics.length ? 'All topics selected' : 'No matching topics'"></div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label style="{{ $lbl }}">Time limit <span style="font-weight:400;color:var(--text-faint);">(minutes, optional)</span></label>
                <input type="number" x-model="duration" min="1" max="240" style="{{ $fld }} max-width: 220px;">
            </div>

            <div>
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
                    <x-icon name="eye" size="15"/> <span x-text="previewOpen ? 'Draw a fresh set' : 'Preview questions'"></span>
                </button>
                <p style="font-size:12px;color:var(--text-faint);text-align:center;margin-top:8px;" x-text="previewOpen ? 'Draws a brand-new set with these settings (clears your ticks). To keep some and re-roll the rest, use Regenerate below.' : 'We\'ll draw the questions below so you can review, swap, or pick a subset - before the test is created.'"></p>
            </div>
        </form>

        {{-- Hidden form that actually creates the test from the chosen question set. --}}
        <form method="POST" action="{{ route('v2.teacher.exams.store_custom') }}" x-ref="genForm" style="display:none;">
            @csrf
            <input type="hidden" name="class_id" :value="classId">
            <input type="hidden" name="title" :value="title">
            <input type="hidden" name="duration_minutes" :value="duration">
            <input type="hidden" name="question_ids" x-ref="qids">
        </form>
        </div>{{-- /.ex-col form --}}

        {{-- Inline preview: the drawn questions render right here on the page (full content width). --}}
        <div x-ref="genPreview" x-show="previewOpen" x-cloak style="margin-top: 22px;">
            <div class="flex items-center justify-between" style="gap: 10px; flex-wrap: wrap; margin-bottom: 12px;">
                <div>
                    <h3 class="serif" style="font-size: 18px; font-weight: 600;">Preview <span style="font-weight:400;color:var(--text-faint);" x-text="'· ' + previewIds.length + ' drawn'"></span></h3>
                    <p style="font-size: 12.5px; color: var(--text-faint); margin-top: 2px;"><strong>Tick</strong> the ones you want to keep, <strong>Swap</strong> a single question, or <strong>Regenerate</strong> to re-roll everything you haven't kept. Then create the test from all of them - or just the ones you kept.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" @click="previewOpen = false">Hide preview</button>
            </div>

            <p x-show="previewLoading" style="font-size:13px;color:var(--text-faint);text-align:center;padding:18px 0;">Drawing questions…</p>
            <p x-show="previewError" x-text="previewError" x-cloak style="font-size:13px;color:var(--bad);text-align:center;padding:18px 0;"></p>

            <div x-ref="previewList" class="gen-gallery" @click="onPreviewClick($event)" @change="onPreviewChange($event)" x-show="previewIds.length" x-html="previewHtml"></div>

            <div class="gen-bar" x-show="previewIds.length">
                <div style="font-size:14px; font-weight:700; white-space:nowrap;"><span x-text="chosen.length"></span> of <span x-text="previewIds.length"></span> kept</div>
                <div class="flex items-center gap-2" style="flex-wrap:wrap;">
                    <button type="button" class="btn btn-ghost btn-sm" @click="openPanel()" x-show="chosen.length"><x-icon name="eye" size="13"/> Review kept</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="regenerate()" :disabled="previewLoading || chosen.length === previewIds.length">
                        <x-icon name="refresh" size="13"/> <span x-text="chosen.length ? ('Regenerate ' + (previewIds.length - chosen.length) + ' others') : 'Regenerate all'"></span>
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="createAll()" :disabled="previewLoading || !previewIds.length">Create all (<span x-text="previewIds.length"></span>)</button>
                    <button type="button" class="btn btn-primary" @click="createSelected()" :disabled="!chosen.length || previewLoading"><x-icon name="check" size="14"/> Create kept (<span x-text="chosen.length"></span>)</button>
                </div>
            </div>
        </div>

        {{-- Selected-questions sidebar (same pattern as custom selection), teleported past the layout's transformed wrapper. --}}
        <template x-teleport="body">
        <div class="sel-shell" :class="panel ? 'is-open' : ''" x-cloak @keydown.escape.window="panel = false">
            <div class="sel-backdrop" @click="panel = false"></div>
            <div class="sel-drawer">
                <div class="sel-head">
                    <div style="font-size:14px; font-weight:700;">Kept questions <span x-text="'('+chosen.length+')'"></span></div>
                    <button type="button" class="sel-x" @click="panel = false" aria-label="Close"><x-icon name="x" size="18"/></button>
                </div>
                <div class="sel-body" @click="onDrawerClick($event)">
                    <p x-show="!chosen.length" style="font-size:13px; color:var(--text-faint); padding:20px 0; text-align:center;">Nothing kept yet. Tick questions in the preview to keep them.</p>
                    <p x-show="drawerLoading" style="font-size:13px; color:var(--text-faint); padding:20px 0; text-align:center;">Loading kept questions…</p>
                    <div x-show="chosen.length" x-html="drawerHtml"></div>
                </div>
                <div class="sel-foot">
                    <button type="button" class="btn btn-ghost btn-sm" @click="clearChosen()" x-show="chosen.length">Clear all</button>
                    <button type="button" class="btn btn-primary btn-sm" @click="createSelected()" :disabled="!chosen.length"><x-icon name="check" size="13"/> Create test (<span x-text="chosen.length"></span>)</button>
                </div>
            </div>
        </div>
        </template>
    @endif
</div>

@include('v2.partials.flag_modal')
@endsection
