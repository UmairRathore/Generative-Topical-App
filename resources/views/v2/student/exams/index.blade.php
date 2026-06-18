@extends('v2.layouts.student')
@section('page_title', 'My Exams')

@section('content')
<div style="margin-bottom: 22px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">My Exams</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Tests assigned to your class. Complete them online and see your score instantly.</p>
</div>

@if (session('success'))
    <div style="margin-bottom: 16px; padding: 12px 16px; background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 8px; font-size: 13px; color: #065f46;">{{ session('success') }}</div>
@endif

<div class="space-y-3">
    @forelse ($exams as $exam)
        @php $a = $attempts[$exam->id] ?? null; $done = $a && $a->status === 'submitted'; @endphp
        <div class="flex items-center justify-between" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px 20px;">
            <div style="min-width: 0;">
                <div style="font-size: 15px; font-weight: 600;">{{ $exam->title }}</div>
                <div class="flex items-center gap-2" style="margin-top: 7px;">
                    <span class="badge badge-emerald">{{ $exam->topic?->title ?? 'Mixed' }}</span>
                    <span class="badge badge-soft">{{ $exam->subject?->name }}</span>
                    <span class="badge badge-soft">{{ $exam->question_count }} questions</span>
                    @if ($exam->duration_minutes)<span class="badge badge-soft">{{ $exam->duration_minutes }} min</span>@endif
                </div>
            </div>
            <div class="flex items-center gap-4" style="flex: none;">
                @if ($done)
                    <div style="text-align: right;">
                        <div style="font-size: 18px; font-weight: 700; color: var(--emerald-700);">{{ $a->percentage }}%</div>
                        <div style="font-size: 11.5px; color: var(--text-faint);">{{ $a->score }}/{{ $a->total_questions }}</div>
                    </div>
                    <a href="{{ route('v2.student.exams.result', $exam) }}" class="btn btn-ghost btn-sm">View result</a>
                @elseif ($exam->isScheduled())
                    <span class="badge badge-soft" title="{{ $exam->available_from->format('D j M, g:i A') }}">Opens {{ $exam->available_from->diffForHumans() }}</span>
                @elseif ($exam->isExpired())
                    <span class="badge badge-blocker" title="Closed {{ $exam->available_until->format('D j M, g:i A') }}">Missed · closed</span>
                @elseif ($a)
                    <span class="badge badge-review">In progress</span>
                    <a href="{{ route('v2.student.exams.take', $exam) }}" class="btn btn-primary btn-sm">Continue <x-icon name="chev-r" size="13"/></a>
                @else
                    @if ($exam->available_until)<span class="badge badge-soft" title="{{ $exam->available_until->format('D j M, g:i A') }}">Closes {{ $exam->available_until->diffForHumans() }}</span>@endif
                    <a href="{{ route('v2.student.exams.take', $exam) }}" class="btn btn-primary btn-sm"><x-icon name="play" size="12"/> Start</a>
                @endif
            </div>
        </div>
    @empty
        <div style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
            No exams assigned yet. Your teacher's tests will show up here.
        </div>
    @endforelse
</div>
@endsection
