@extends('v2.layouts.teacher')
@section('page_title', 'Create a test')

@php
    $fld = 'width: 100%; padding: 10px 13px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);';
    $lbl = 'display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;';
@endphp

@section('content')
<style>
    .ex-wrap{max-width:760px;margin:0 auto;}
    .ex-tabs{display:flex;gap:0;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:20px;}
    .ex-tabs a{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;font-size:13.5px;font-weight:600;text-decoration:none;color:var(--text-soft);background:var(--surface);}
    .ex-tabs a.on{background:var(--primary,#061C30);color:#fff;}
    .ex-tabs a + a{border-left:1px solid var(--border);}
    .ms-control{min-height:44px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:7px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg);cursor:text;}
    .ms-control.open{border-color:var(--accent);}
    .ms-chip{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:3px 6px 3px 9px;border-radius:99px;background:var(--ok-soft,rgba(95,160,82,.14));color:var(--ok);}
    .ms-chip button{border:0;background:none;cursor:pointer;color:inherit;font-size:14px;line-height:1;padding:0;}
    .ms-drop{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:30;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 10px 28px rgba(0,0,0,.14);max-height:260px;display:flex;flex-direction:column;overflow:hidden;}
    .ms-opt{padding:9px 12px;font-size:13px;cursor:pointer;}
    .ms-opt:hover{background:var(--soft-surface);}
    [x-cloak]{display:none!important;}
</style>

<div class="ex-wrap">
    <a href="{{ route('v2.teacher.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
        <x-icon name="chev-l" size="14"/> Back to Exams
    </a>
    <h2 class="serif" style="font-size: 24px; font-weight: 600; margin-bottom: 4px;">Create a test</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-bottom: 20px;">Two ways to build a test from the question bank: let us pick at random, or hand-pick the exact questions yourself.</p>

    <div class="ex-tabs">
        <a href="{{ route('v2.teacher.exams.create') }}" class="on"><x-icon name="sparkle" size="15"/> Random generator</a>
        <a href="{{ route('v2.teacher.exams.custom') }}"><x-icon name="list" size="15"/> Custom selection</a>
    </div>

    @if ($classes->isEmpty())
        <div style="padding: 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
            You are not assigned to any class yet. Ask your school admin to assign you to a class.
        </div>
    @else
        <form method="POST" action="{{ route('v2.teacher.exams.store') }}"
              x-data="{
                  classes: @js($classMeta),
                  classId: '{{ old('class_id', $classes->first()->id) }}',
                  selected: [],
                  open: false,
                  search: '',
                  get topics(){ return (this.classId && this.classes[this.classId]) ? this.classes[this.classId].topics : []; },
                  get available(){ const q = this.search.toLowerCase(); const ids = this.selected.map(s => s.id); return this.topics.filter(t => ids.indexOf(t.id) === -1 && t.label.toLowerCase().includes(q)); },
                  add(t){ this.selected.push(t); this.search = ''; },
                  remove(id){ this.selected = this.selected.filter(s => s.id !== id); },
                  onClassChange(){ this.selected = []; this.search = ''; this.open = false; }
              }"
              class="space-y-5"
              style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 26px;">
            @csrf

            <div>
                <label style="{{ $lbl }}">Test title</label>
                <input type="text" name="title" value="{{ old('title', 'Kinematics Test') }}" required maxlength="120" style="{{ $fld }}">
                @error('title') <p style="font-size: 12px; color: var(--bad); margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="{{ $lbl }}">Class</label>
                    <select name="class_id" x-model="classId" @change="onClassChange()" required style="{{ $fld }}">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} - {{ $c->subject?->name }}</option>
                        @endforeach
                    </select>
                    @error('class_id') <p style="font-size: 12px; color: var(--bad); margin-top: 4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label style="{{ $lbl }}">Number of questions <span style="font-weight:400;color:var(--text-faint);">(max 40)</span></label>
                    <input type="number" name="question_count" value="{{ old('question_count', 20) }}" min="1" max="40" required style="{{ $fld }}">
                    @error('question_count') <p style="font-size: 12px; color: var(--bad); margin-top: 4px;">{{ $message }}</p> @enderror
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

                {{-- Submitted values --}}
                <template x-for="t in selected" :key="'h' + t.id"><input type="hidden" name="topic_ids[]" :value="t.id"></template>
            </div>

            <div>
                <label style="{{ $lbl }}">Time limit <span style="font-weight:400;color:var(--text-faint);">(minutes, optional)</span></label>
                <input type="number" name="duration_minutes" value="{{ old('duration_minutes') }}" min="1" max="240" style="{{ $fld }} max-width: 220px;">
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
                <x-icon name="sparkle" size="15"/> Generate Test
            </button>
        </form>
    @endif
</div>
@endsection
