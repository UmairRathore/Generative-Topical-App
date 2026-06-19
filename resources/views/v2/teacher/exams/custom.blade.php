@extends('v2.layouts.teacher')
@section('page_title', 'Custom selection')

@php
    $fld = 'padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);';
    $sessionLabels = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];
@endphp

@section('content')
<style>
    .ex-tabs{display:flex;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:20px;max-width:760px;}
    .ex-tabs a{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;font-size:13.5px;font-weight:600;text-decoration:none;color:var(--text-soft);background:var(--surface);}
    .ex-tabs a.on{background:var(--primary,#061C30);color:#fff;}
    .ex-tabs a + a{border-left:1px solid var(--border);}
    .pick-row{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;cursor:pointer;background:var(--surface);}
    .pick-bar{position:sticky;bottom:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:0 -6px 24px rgba(0,0,0,.08);padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:8px;}
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
        selected: [],
        init(){ try { this.selected = JSON.parse(localStorage.getItem('exPick_'+this.classKey) || '[]'); } catch(e){ this.selected = []; } },
        has(id){ return this.selected.includes(id); },
        toggle(id){ var i = this.selected.indexOf(id); if (i > -1) this.selected.splice(i,1); else this.selected.push(id); localStorage.setItem('exPick_'+this.classKey, JSON.stringify(this.selected)); },
        clear(){ this.selected = []; localStorage.removeItem('exPick_'+this.classKey); }
    }">

    {{-- Filters --}}
    <form method="GET" action="{{ route('v2.teacher.exams.custom') }}"
          style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px; margin-bottom: 16px;">
        <div class="flex items-end gap-4" style="flex-wrap: wrap;">
            <div>
                <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--text-faint); margin-bottom:5px;">Class</label>
                <select name="class_id" onchange="this.form.submit()" style="{{ $fld }} min-width: 240px;">
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($c->id === $classId)>{{ $c->name }} - {{ $c->subject?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 240px;">
                <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--text-faint); margin-bottom:5px;">Filter by topic</label>
                <div style="display:flex; flex-wrap:wrap; gap:6px; max-height:84px; overflow-y:auto;">
                    @forelse ($topics as $t)
                        <label class="badge {{ in_array($t->id, $topicIds) ? 'badge-pass' : 'badge-soft' }}" style="cursor:pointer; font-weight:500; gap:5px; display:inline-flex; align-items:center;">
                            <input type="checkbox" name="topic_ids[]" value="{{ $t->id }}" @checked(in_array($t->id, $topicIds)) onchange="this.form.submit()" style="width:13px;height:13px;">
                            {{ $t->external_id }}. {{ \Illuminate\Support\Str::limit($t->title, 20) }}
                        </label>
                    @empty
                        <span style="font-size:12.5px; color:var(--text-faint);">No tagged topics for this subject - showing all questions.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </form>

    @if ($questions && $questions->total())
        <div style="font-size: 12.5px; color: var(--text-faint); margin-bottom: 10px;">
            {{ number_format($questions->total()) }} {{ \Illuminate\Support\Str::plural('question', $questions->total()) }} match · <span x-text="selected.length"></span> selected
        </div>

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
        <button type="button" class="btn btn-ghost btn-sm" @click="clear()" x-show="selected.length">Clear</button>
        <input type="text" name="title" value="{{ old('title', 'Custom Test') }}" required maxlength="120" placeholder="Test title" style="{{ $fld }} flex:1; min-width:180px;">
        <input type="number" name="duration_minutes" min="1" max="240" placeholder="Time (min)" style="{{ $fld }} width:120px;">
        <button type="submit" class="btn btn-primary" :disabled="selected.length === 0" :style="selected.length === 0 ? 'opacity:.5;cursor:not-allowed;' : ''"><x-icon name="check" size="14"/> Create test</button>
    </form>
</div>
@endif
@endsection
