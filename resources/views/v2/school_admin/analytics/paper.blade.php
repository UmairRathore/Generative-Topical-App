@extends('v2.layouts.school_admin')
@section('page_title', $student->name.' - paper')

@section('content')
<div class="result-shell">
    <a href="{{ route('v2.school.analytics.student', $student) }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
        <x-icon name="chev-l" size="14"/> Back to {{ $student->name }}
    </a>

    @include('v2.partials.result_score_strip', [
        'title'        => $student->name,
        'subtitle'     => $exam->title.' · '.($exam->topic?->title ?? 'Mixed topics').($student->roll_number ? ' · Roll '.$student->roll_number : ''),
        'submittedLine'=> 'Submitted '.$attempt->submitted_at?->diffForHumans().($attempt->time_taken ? ' · time taken '.$attempt->time_taken : ''),
        'attempt'      => $attempt,
        'breakdown'    => $breakdown,
    ])

    {{-- Per-topic --}}
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin-bottom: 20px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px;">{{ $student->name }}'s performance by topic</div>
        @forelse ($topicStats as $t)
            <div style="margin-bottom: 13px;">
                <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                    <span style="font-weight: 500;">{{ $t['topic'] }}</span>
                    <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
                </div>
                <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                    <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $t['percent'] >= 60 ? 'var(--ok)' : ($t['percent'] >= 40 ? 'var(--warn)' : 'var(--bad)') }};"></div>
                </div>
            </div>
        @empty
            <div style="font-size: 12.5px; color: var(--text-faint);">No topic data for this attempt.</div>
        @endforelse
    </div>

    {{-- Paper + sticky palette --}}
    <div class="result-grid">
        <div class="result-main">
            <div style="font-size: 14px; font-weight: 600; margin: 0 2px 12px;">Question-by-question</div>
            @include('v2.partials.answer_review', ['exam' => $exam, 'answers' => $answers, 'revealCorrect' => $revealCorrect])
        </div>
        @include('v2.partials.result_palette', ['palette' => $palette, 'breakdown' => $breakdown])
    </div>
</div>
@endsection
