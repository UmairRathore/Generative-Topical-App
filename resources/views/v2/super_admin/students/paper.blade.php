@extends('v2.layouts.super_admin')
@section('page_title', $student->name.' — paper')

@php $pct = $attempt->percentage; $tone = $pct >= 60 ? 'var(--ok)' : ($pct >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp

@section('content')
<div style="max-width: 760px; margin: 0 auto;">
    <a href="{{ route('v2.super_admin.schools.students.show', [$school, $student]) }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
        <x-icon name="chev-l" size="14"/> Back to {{ $student->name }}
    </a>

    {{-- Score hero --}}
    <div class="flex items-center gap-6" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 26px 28px; margin-bottom: 20px;">
        <div style="flex: none; width: 96px; height: 96px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 4px solid {{ $tone }};">
            <div class="serif" style="font-size: 26px; font-weight: 700; color: {{ $tone }};">{{ $pct }}%</div>
        </div>
        <div>
            <h2 class="serif" style="font-size: 24px; font-weight: 600;">{{ $exam->title }}</h2>
            <div style="font-size: 14px; color: var(--text-soft); margin-top: 4px;">
                {{ $student->name }} scored <strong style="color: var(--text);">{{ $attempt->score }} out of {{ $attempt->total_questions }}</strong>
                · {{ $exam->topic?->title ?? 'Mixed topics' }}
                @if ($student->roll_number)<span style="color: var(--text-faint);">· Roll {{ $student->roll_number }}</span>@endif
            </div>
            <div style="font-size: 12px; color: var(--text-faint); margin-top: 4px;">Submitted {{ $attempt->submitted_at?->diffForHumans() }}@if ($attempt->time_taken) · time taken {{ $attempt->time_taken }}@endif</div>
        </div>
    </div>

    {{-- Per-topic --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">Performance by topic</div>
        @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No topic data for this attempt.'])
    </div>

    {{-- Question-by-question review --}}
    <div style="font-size: 14px; font-weight: 600; margin: 0 2px 12px;">Question-by-question</div>
    @include('v2.partials.answer_review', ['exam' => $exam, 'answers' => $answers])
</div>
@endsection
