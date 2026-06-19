@extends('v2.layouts.student')
@section('page_title', 'Test submitted')

@section('content')
<a href="{{ route('v2.student.exams.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to My Exams
</a>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 48px 32px; text-align: center; max-width: 560px; margin: 24px auto;">
    <span style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 99px; background: rgba(var(--ok-rgb,95,160,82),.14); color: var(--ok); margin-bottom: 16px;">
        <x-icon name="check" size="26"/>
    </span>
    <h2 class="serif" style="font-size: 24px; font-weight: 600;">Test submitted</h2>
    <p style="font-size: 14px; color: var(--text-soft); margin-top: 6px;">{{ $exam->title }}</p>

    <div style="margin-top: 22px; padding: 16px 18px; background: var(--soft-surface); border-radius: var(--r-lg); font-size: 13.5px; color: var(--text-soft); line-height: 1.6;">
        You completed this test{{ $attempt->submitted_at ? ' '.$attempt->submitted_at->diffForHumans() : '' }}.
        Your teacher hasn’t released the results yet - your <strong>score and answers</strong> will appear here once they do.
    </div>

    <a href="{{ route('v2.student.exams.index') }}" class="btn btn-ghost" style="margin-top: 22px;">Back to my exams</a>
</div>
@endsection
