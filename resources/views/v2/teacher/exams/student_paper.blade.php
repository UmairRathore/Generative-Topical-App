@extends('v2.layouts.teacher')
@section('page_title', $student->name.' — paper')

@php $pct = $attempt->percentage; $tone = $pct >= 60 ? 'var(--ok)' : ($pct >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp

@section('content')
<div style="max-width: 760px; margin: 0 auto;">
    <a href="{{ route('v2.teacher.exams.show', $exam) }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
        <x-icon name="chev-l" size="14"/> Back to {{ $exam->title }}
    </a>

    {{-- Score hero --}}
    <div class="flex items-center gap-6" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 26px 28px; margin-bottom: 20px;">
        <div style="flex: none; width: 96px; height: 96px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 4px solid {{ $tone }};">
            <div class="serif" style="font-size: 26px; font-weight: 700; color: {{ $tone }};">{{ $pct }}%</div>
        </div>
        <div>
            <h2 class="serif" style="font-size: 24px; font-weight: 600;">{{ $student->name }}</h2>
            <div style="font-size: 14px; color: var(--text-soft); margin-top: 4px;">
                Scored <strong style="color: var(--text);">{{ $attempt->score }} out of {{ $attempt->total_questions }}</strong>
                · {{ $exam->topic?->title ?? 'Mixed topics' }}
                @if ($student->roll_number)<span style="color: var(--text-faint);">· Roll {{ $student->roll_number }}</span>@endif
            </div>
            <div style="font-size: 12px; color: var(--text-faint); margin-top: 4px;">Submitted {{ $attempt->submitted_at?->diffForHumans() }}@if ($attempt->time_taken) · time taken {{ $attempt->time_taken }}@endif</div>
        </div>
    </div>

    {{-- Per-topic --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">{{ $student->name }}'s performance by topic</div>
        @foreach ($topicStats as $t)
            <div style="margin-bottom: 13px;">
                <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                    <span style="font-weight: 500;">{{ $t['topic'] }}</span>
                    <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
                </div>
                <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                    <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $t['percent'] >= 60 ? 'var(--ok)' : ($t['percent'] >= 40 ? 'var(--warn)' : 'var(--bad)') }};"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Question-by-question review --}}
    <div style="font-size: 14px; font-weight: 600; margin: 0 2px 12px;">Question-by-question</div>
    @include('v2.partials.answer_review', ['exam' => $exam, 'answers' => $answers])
</div>
@endsection
