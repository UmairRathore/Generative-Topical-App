@extends('v2.layouts.teacher')
@section('page_title', 'Create Exam')

@php
    $fld = 'width: 100%; padding: 10px 13px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);';
    $lbl = 'display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;';
@endphp

@section('content')
<div style="max-width: 600px;">
    <a href="{{ route('v2.teacher.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 18px;">
        <x-icon name="chev-l" size="14"/> Back to Exams
    </a>
    <h2 class="serif" style="font-size: 24px; font-weight: 600; margin-bottom: 4px;">Generate a Test</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-bottom: 22px;">Pick a class and topic, choose how many questions, and we build it from the question bank in seconds.</p>

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
                @error('title') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="{{ $lbl }}">Class</label>
                <select name="class_id" x-model="classId" required style="{{ $fld }}">
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} — {{ $c->subject?->name }}</option>
                    @endforeach
                </select>
                @error('class_id') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="{{ $lbl }}">Topic</label>
                <select name="topic_id" style="{{ $fld }}">
                    <option value="">All topics (mixed)</option>
                    <template x-for="t in topics" :key="t.id">
                        <option :value="t.id" x-text="t.label" :selected="t.id == '{{ old('topic_id') }}'"></option>
                    </template>
                </select>
                <p style="font-size: 12px; color: var(--text-faint); margin-top: 5px;">Per-topic stats are richest when you pick one topic, e.g. Kinematics.</p>
            </div>

            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="{{ $lbl }}">Number of questions <span style="font-weight:400;color:var(--text-faint);">(max 40)</span></label>
                    <input type="number" name="question_count" value="{{ old('question_count', 20) }}" min="1" max="40" required style="{{ $fld }}">
                    @error('question_count') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label style="{{ $lbl }}">Time limit <span style="font-weight:400;color:var(--text-faint);">(mins, optional)</span></label>
                    <input type="number" name="duration_minutes" value="{{ old('duration_minutes') }}" min="1" max="240" style="{{ $fld }}">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
                <x-icon name="sparkle" size="15"/> Generate Test
            </button>
        </form>
    @endif
</div>
@endsection
