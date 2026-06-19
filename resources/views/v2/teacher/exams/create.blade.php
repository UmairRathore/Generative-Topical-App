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
              x-data="{ classes: @js($classMeta), classId: '{{ old('class_id', $classes->first()->id) }}', get topics(){ return (this.classId && this.classes[this.classId]) ? this.classes[this.classId].topics : [] } }"
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
                    <select name="class_id" x-model="classId" required style="{{ $fld }}">
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
                <label style="{{ $lbl }}">Topics <span style="font-weight:400;color:var(--text-faint);">(pick one or more, or leave all unchecked for a mixed test)</span></label>
                <div style="max-height: 168px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 6px; background: var(--bg);">
                    <template x-if="topics.length === 0">
                        <div style="padding: 10px 8px; font-size: 12.5px; color: var(--text-faint);">This class's subject has no tagged topics - a test will draw from all its questions.</div>
                    </template>
                    <template x-for="t in topics" :key="t.id">
                        <label style="display:flex; align-items:center; gap:9px; padding:6px 8px; border-radius:6px; cursor:pointer; font-size:13px;">
                            <input type="checkbox" name="topic_ids[]" :value="t.id" style="width:15px;height:15px;">
                            <span x-text="t.label"></span>
                        </label>
                    </template>
                </div>
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
