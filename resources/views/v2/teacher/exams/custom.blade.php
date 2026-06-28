@extends('v2.layouts.teacher')
@section('page_title', 'Custom selection')

@php
    $fld = 'padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);';
    $sessionLabels = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];
    $allTopicsJson = $topics->map(fn ($t) => ['id' => (string) $t->id, 'label' => $t->external_id.'. '.$t->title])->values();
    $selectedTopicsJson = $topics->filter(fn ($t) => in_array($t->id, $topicIds))->map(fn ($t) => ['id' => (string) $t->id, 'label' => $t->external_id.'. '.$t->title])->values();
@endphp

@section('content')
<style>
    .ex-tabs{display:flex;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:20px;max-width:760px;}
    .ex-tabs a{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;font-size:13.5px;font-weight:600;text-decoration:none;color:var(--text-soft);background:var(--surface);}
    .ex-tabs a.on{background:var(--primary,#061C30);color:#fff;}
    .ex-tabs a + a{border-left:1px solid var(--border);}
    .pick-row{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;cursor:pointer;background:var(--surface);}
    .pick-bar{position:sticky;bottom:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:0 -6px 24px rgba(0,0,0,.08);padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:8px;}
    .ms-control{min-height:42px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg);cursor:text;}
    .ms-control.open{border-color:var(--accent);}
    .ms-chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:3px 5px 3px 9px;border-radius:99px;background:var(--ok-soft,rgba(95,160,82,.14));color:var(--ok);}
    .ms-chip button{border:0;background:none;cursor:pointer;color:inherit;font-size:14px;line-height:1;padding:0;}
    .ms-drop{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:30;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 10px 28px rgba(0,0,0,.14);max-height:240px;display:flex;flex-direction:column;overflow:hidden;}
    .ms-opt{padding:9px 12px;font-size:13px;cursor:pointer;}
    .ms-opt:hover{background:var(--soft-surface);}
    [x-cloak]{display:none!important;}
    .view-seg{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;}
    .view-seg a{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;font-size:12.5px;font-weight:600;text-decoration:none;color:var(--text-soft);background:var(--bg);}
    .view-seg a.on{background:var(--primary,#061C30);color:#fff;}
    .view-seg a + a{border-left:1px solid var(--border);}
    .gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:14px;}
    .gal-card{display:flex;flex-direction:column;border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface);overflow:hidden;}
    .gal-card.is-sel{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent) inset;}
    .gal-pick{display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid var(--border);background:var(--soft-surface);cursor:pointer;flex-wrap:wrap;}
    .gal-pick input{width:17px;height:17px;flex:none;}
    .gal-body{padding:16px 18px;}
    @media (max-width:560px){.gal-grid{grid-template-columns:1fr;}}
    .sel-shell{position:fixed;inset:0;z-index:60;pointer-events:none;}
    .sel-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);opacity:0;transition:opacity .15s;pointer-events:none;}
    .sel-shell.is-open .sel-backdrop{opacity:1;pointer-events:auto;}
    .sel-drawer{position:absolute;top:0;right:0;bottom:0;width:clamp(380px,44vw,600px);display:flex;flex-direction:column;background:var(--bg);border-left:1px solid var(--border);box-shadow:-12px 0 36px rgba(0,0,0,.18);transform:translateX(100%);transition:transform .2s ease;pointer-events:auto;}
    .sel-shell.is-open .sel-drawer{transform:none;}
    .sel-head{flex:none;display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid var(--border);background:var(--surface);}
    .sel-x{border:0;background:none;cursor:pointer;color:var(--text-soft);padding:5px;display:inline-flex;border-radius:7px;}
    .sel-x:hover{background:var(--soft-surface);}
    .sel-body{flex:1;min-height:0;overflow-y:auto;padding:14px 16px;background:var(--soft-surface);}
    .sel-num{flex:none;width:22px;height:22px;border-radius:99px;background:var(--soft-surface);color:var(--text-soft);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;}
    .sel-rm{flex:none;border:0;background:none;cursor:pointer;color:var(--text-faint);padding:4px;display:inline-flex;border-radius:6px;}
    .sel-rm:hover{background:var(--bad-soft,rgba(200,60,60,.12));color:var(--bad);}
    .sel-foot{flex:none;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-top:1px solid var(--border);background:var(--surface);}
    .selq-card{border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface);overflow:hidden;margin-bottom:12px;}
    .selq-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 14px;border-bottom:1px solid var(--border);background:var(--bg);}
    .selq-body{padding:14px 16px;}
    @media (max-width:560px){.sel-drawer{width:100%;}}
</style>

<a href="{{ route('v2.teacher.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Exams
</a>
<h2 class="serif" style="font-size: 24px; font-weight: 600; margin-bottom: 4px;">Create a test</h2>
<p style="color: var(--text-soft); font-size: 13px; margin-bottom: 20px;">Hand-pick the exact questions for this test. Your selection is remembered as you filter and page.</p>

<div class="ex-tabs">
    <a href="{{ route('v2.teacher.exams.create') }}"><x-icon name="sparkle" size="15"/> Random generator</a>
    <a href="{{ route('v2.teacher.exams.custom') }}" class="on"><x-icon name="list" size="15"/> Custom selection</a>
</div>

@if ($classes->isEmpty())
    <div style="padding: 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
        You are not assigned to any class yet.
    </div>
@else
<div x-data="{
        classKey: '{{ $classId }}',
        selUrl: '{{ route('v2.teacher.exams.custom_selected') }}',
        selected: [],
        panel: false,
        cardsHtml: '',
        loading: false,
        init(){ try { this.selected = JSON.parse(localStorage.getItem('exPick_'+this.classKey) || '[]'); } catch(e){ this.selected = []; } },
        has(id){ return this.selected.includes(id); },
        toggle(id){ var i = this.selected.indexOf(id); if (i > -1) this.selected.splice(i,1); else this.selected.push(id); this.persist(); if (this.panel) this.loadCards(); },
        remove(id){ var i = this.selected.indexOf(id); if (i > -1){ this.selected.splice(i,1); this.persist(); } },
        clear(){ this.selected = []; this.persist(); this.cardsHtml = ''; },
        persist(){ localStorage.setItem('exPick_'+this.classKey, JSON.stringify(this.selected)); },
        openPanel(){ this.panel = true; this.loadCards(); },
        loadCards(){
            if (! this.selected.length){ this.cardsHtml = ''; return; }
            this.loading = true;
            fetch(this.selUrl + '?ids=' + this.selected.join(','), { headers: { 'X-Requested-With': 'fetch' } })
                .then(r => r.text())
                .then(h => { this.cardsHtml = h; })
                .catch(() => { this.cardsHtml = '<p style=\'padding:16px;color:var(--text-faint);font-size:13px;\'>Could not load the selected questions.</p>'; })
                .finally(() => { this.loading = false; });
        },
        onCardClick(e){ var btn = e.target.closest('[data-remove]'); if (btn){ this.remove(parseInt(btn.dataset.remove, 10)); this.loadCards(); } },
        onFlagged(e){ var id = e.detail && e.detail.id; if (id != null && this.has(id)){ this.remove(id); if (this.panel) this.loadCards(); } }
    }"
    @question-flagged.window="onFlagged($event)">

    {{-- Filters --}}
    <form method="GET" id="topicFilterForm" action="{{ route('v2.teacher.exams.custom') }}"
          x-data="{
              open: false,
              search: '',
              all: @js($allTopicsJson),
              sel: @js($selectedTopicsJson),
              get available(){ const q = this.search.toLowerCase(); const ids = this.sel.map(s => s.id); return this.all.filter(t => ids.indexOf(t.id) === -1 && t.label.toLowerCase().includes(q)); },
              add(t){ this.sel.push(t); this.search = ''; this.$nextTick(() => document.getElementById('topicFilterForm').submit()); },
              remove(id){ this.sel = this.sel.filter(s => s.id !== id); this.$nextTick(() => document.getElementById('topicFilterForm').submit()); }
          }"
          style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px; margin-bottom: 16px;">
        {{-- keep the chosen view (list/gallery) across class + topic filtering --}}
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="flex items-end gap-4" style="flex-wrap: wrap;">
            <div>
                <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--text-faint); margin-bottom:5px;">Class</label>
                <select name="class_id" onchange="this.form.submit()" style="{{ $fld }} min-width: 240px;">
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($c->id === $classId)>{{ $c->name }} - {{ $c->subject?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 280px;">
                <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--text-faint); margin-bottom:5px;">Filter by topic</label>
                <div style="position: relative;" @click.outside="open = false">
                    <div class="ms-control" :class="open ? 'open' : ''" @click="open = true; $nextTick(() => $refs.tsearch && $refs.tsearch.focus())">
                        <template x-for="t in sel" :key="t.id">
                            <span class="ms-chip"><span x-text="t.label"></span><button type="button" @click.stop="remove(t.id)">&times;</button></span>
                        </template>
                        <input type="text" x-ref="tsearch" x-model="search" @focus="open = true"
                               :placeholder="sel.length ? '' : (all.length ? 'All topics - type to filter…' : 'No tagged topics')"
                               :disabled="!all.length"
                               style="flex:1; min-width:120px; border:0; outline:none; background:transparent; font-size:13px; color:var(--text); padding:2px;">
                    </div>
                    <div class="ms-drop" x-show="open && all.length" x-cloak>
                        <div style="overflow-y:auto;">
                            <template x-for="t in available" :key="t.id"><div class="ms-opt" @click="add(t)" x-text="t.label"></div></template>
                            <template x-if="available.length === 0"><div style="padding:10px 12px; font-size:12.5px; color:var(--text-faint);" x-text="sel.length === all.length ? 'All topics selected' : 'No matching topics'"></div></template>
                        </div>
                    </div>
                </div>
                {{-- submitted filter values --}}
                <template x-for="t in sel" :key="'h' + t.id"><input type="hidden" name="topic_ids[]" :value="t.id"></template>
            </div>
        </div>
    </form>

    @if ($questions && $questions->total())
        <div class="flex items-center justify-between gap-3" style="margin-bottom: 12px; flex-wrap: wrap;">
            <div style="font-size: 12.5px; color: var(--text-faint);">
                {{ number_format($questions->total()) }} {{ \Illuminate\Support\Str::plural('question', $questions->total()) }} match · <span x-text="selected.length"></span> selected
            </div>
            <div class="view-seg">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" class="{{ $view === 'list' ? 'on' : '' }}"><x-icon name="list" size="14"/> List</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'gallery']) }}" class="{{ $view === 'gallery' ? 'on' : '' }}"><x-icon name="grid" size="14"/> Gallery</a>
            </div>
        </div>

        @if ($view === 'gallery')
            <div class="gal-grid">
                @foreach ($questions as $q)
                    <div class="gal-card" :class="has({{ $q->id }}) ? 'is-sel' : ''">
                        <label class="gal-pick">
                            <input type="checkbox" :checked="has({{ $q->id }})" @change="toggle({{ $q->id }})">
                            @if ($q->topic)<span class="badge badge-emerald">{{ $q->topic->external_id }}. {{ \Illuminate\Support\Str::limit($q->topic->title, 22) }}</span>@else<span class="badge badge-soft">Untagged</span>@endif
                            <span style="font-size:11.5px; color:var(--text-faint);">{{ $q->source_paper }} · Q{{ $q->question_number }}</span>
                            <span style="flex:1;"></span>
                            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--text-soft);padding:3px 8px;"
                                    @click.stop.prevent="$dispatch('flag-question', { action: '{{ route('v2.teacher.questions.flag', $q) }}', label: '{{ $q->source_paper }} · Q{{ $q->question_number }}' })">
                                <x-icon name="flag" size="12"/> Report
                            </button>
                        </label>
                        <div class="gal-body">
                            @include('v2.partials.question_card', ['q' => $q])
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            @foreach ($questions as $q)
                <label class="pick-row" :style="has({{ $q->id }}) ? 'border-color:var(--accent); background:rgba(var(--accent-rgb,0,151,211),.05);' : ''">
                    <input type="checkbox" :checked="has({{ $q->id }})" @change="toggle({{ $q->id }})" style="width:17px;height:17px;margin-top:2px;flex:none;">
                    <div style="min-width:0; flex:1;">
                        <div style="font-size:13.5px; line-height:1.45;">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $q->question_text), 160) }}</div>
                        <div class="flex items-center gap-2" style="margin-top:6px; flex-wrap:wrap;">
                            @if ($q->topic)<span class="badge badge-emerald">{{ $q->topic->external_id }}. {{ \Illuminate\Support\Str::limit($q->topic->title, 22) }}</span>@else<span class="badge badge-soft">Untagged</span>@endif
                            <span style="font-size:11.5px; color:var(--text-faint);">{{ $q->source_paper }} · Q{{ $q->question_number }}</span>
                            @if ($q->correct_answer)<span class="badge badge-pass" style="font-size:10px;">Ans {{ $q->correct_answer }}</span>@endif
                            <span class="badge badge-soft" style="font-size:10px;">{{ str_replace('_', ' ', $q->layout_type) }}</span>
                        </div>
                    </div>
                </label>
            @endforeach
        @endif

        {{-- Pager --}}
        @if ($questions->hasPages())
            <div class="flex items-center justify-between" style="margin-top: 14px;">
                <div style="font-size: 12.5px; color: var(--text-faint);">Page {{ $questions->currentPage() }} of {{ $questions->lastPage() }}</div>
                <div class="flex items-center gap-2">
                    @if ($questions->onFirstPage())<span class="btn btn-ghost btn-sm" style="opacity:.45;pointer-events:none;">Prev</span>@else<a href="{{ $questions->previousPageUrl() }}" class="btn btn-ghost btn-sm">Prev</a>@endif
                    @if ($questions->hasMorePages())<a href="{{ $questions->nextPageUrl() }}" class="btn btn-ghost btn-sm">Next</a>@else<span class="btn btn-ghost btn-sm" style="opacity:.45;pointer-events:none;">Next</span>@endif
                </div>
            </div>
        @endif
    @else
        <div style="padding: 40px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-faint); font-size: 14px;">No questions match this filter.</div>
    @endif

    {{-- Sticky create bar --}}
    <form method="POST" action="{{ route('v2.teacher.exams.store_custom') }}" class="pick-bar">
        @csrf
        <input type="hidden" name="class_id" value="{{ $classId }}">
        <input type="hidden" name="question_ids" :value="selected.join(',')">
        <div style="font-size:14px; font-weight:700; white-space:nowrap;"><span x-text="selected.length"></span> selected <span style="font-weight:400; color:var(--text-faint);">(max 40)</span></div>
        <button type="button" class="btn btn-ghost btn-sm" @click="openPanel()" x-show="selected.length"><x-icon name="eye" size="13"/> Review</button>
        <button type="button" class="btn btn-ghost btn-sm" @click="clear()" x-show="selected.length">Clear</button>
        <input type="text" name="title" value="{{ old('title', 'Custom Test') }}" required maxlength="120" placeholder="Test title" style="{{ $fld }} flex:1; min-width:180px;">
        <button type="submit" class="btn btn-primary" :disabled="selected.length === 0" :style="selected.length === 0 ? 'opacity:.5;cursor:not-allowed;' : ''"><x-icon name="check" size="14"/> Create test</button>
    </form>

    {{-- Selected-questions drawer: always reachable so the teacher can review
         everything they've picked across pages/filters, and remove any of them.
         Teleported to <body> so the fixed drawer escapes the layout's transformed
         .fade-in wrapper and anchors to the viewport edge. --}}
    <template x-teleport="body">
    <div class="sel-shell" :class="panel ? 'is-open' : ''" x-cloak @keydown.escape.window="panel = false">
        <div class="sel-backdrop" @click="panel = false"></div>
        <div class="sel-drawer">
            <div class="sel-head">
                <div style="font-size:14px; font-weight:700;">Selected questions <span x-text="'('+selected.length+')'"></span></div>
                <button type="button" class="sel-x" @click="panel = false" aria-label="Close"><x-icon name="x" size="18"/></button>
            </div>
            <div class="sel-body" @click="onCardClick($event)">
                <p x-show="!selected.length" style="font-size:13px; color:var(--text-faint); padding:20px 0; text-align:center;">No questions selected yet. Tick questions to build your test.</p>
                <p x-show="loading" style="font-size:13px; color:var(--text-faint); padding:20px 0; text-align:center;">Loading selected questions…</p>
                <div x-show="selected.length" x-html="cardsHtml"></div>
            </div>
            <div class="sel-foot">
                <button type="button" class="btn btn-ghost btn-sm" @click="clear()" x-show="selected.length">Clear all</button>
                <button type="button" class="btn btn-primary btn-sm" @click="panel = false">Done</button>
            </div>
        </div>
    </div>
    </template>

    @include('v2.partials.flag_modal')
</div>
@endif
@endsection
