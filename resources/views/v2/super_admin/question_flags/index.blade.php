@extends('v2.layouts.super_admin')
@section('page_title', 'Quality Reviews')

@php
    $statusBadge = [
        'active'       => ['badge-pass', 'Active'],
        'draft'        => ['badge-soft', 'Draft'],
        'under_review' => ['badge-review', 'Under review'],
        'archived'     => ['badge-blocker', 'Archived'],
    ];
    $outcomeBadge = [
        'correct'  => ['badge-pass', 'Correct — matches source'],
        'cosmetic' => ['badge-emerald', 'Cosmetic fix'],
        'material' => ['badge-blocker', 'Material error'],
    ];
    $tabs = ['open' => 'Open', 'decided' => 'Decided'];
@endphp

@section('content')
<style>
    .flagq{display:flex;border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface);overflow:hidden;margin-bottom:18px;}
    .flagq-main{flex:1;min-width:0;padding:20px 22px;border-right:1px solid var(--border);}
    .flagq-side{width:340px;flex:none;padding:18px 20px;background:var(--soft-surface);display:flex;flex-direction:column;gap:12px;}
    .flag-item{border:1px solid var(--border);border-radius:10px;background:var(--bg);padding:11px 13px;}
    .ftab{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:20px;}
    .ftab a{padding:8px 16px;font-size:12.5px;font-weight:600;text-decoration:none;color:var(--text-soft);background:var(--bg);}
    .ftab a.on{background:var(--primary,#061C30);color:#fff;}
    .ftab a + a{border-left:1px solid var(--border);}
    @media (max-width:880px){.flagq{flex-direction:column;}.flagq-main{border-right:0;border-bottom:1px solid var(--border);}.flagq-side{width:auto;}}
</style>

<div class="flex items-start justify-between" style="margin-bottom:6px;gap:16px;flex-wrap:wrap;">
    <div>
        <h2 class="serif" style="font-size:24px;font-weight:600;">Quality Reviews</h2>
        <p style="color:var(--text-soft);font-size:13px;margin-top:4px;max-width:660px;">
            Reported questions, grouped into one review each. <strong>Mark correct</strong> if our digital copy already
            matches the official Cambridge source. To change a question, use <strong>Correct question</strong> — it opens the
            <a href="{{ route('v2.super_admin.question_bank.index') }}" style="color:var(--accent);">Question Bank</a>
            editor where you classify the fix as cosmetic or material on save.
        </p>
    </div>
</div>

<div class="ftab">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('v2.super_admin.question_flags.index', ['status' => $key]) }}" class="{{ $status === $key ? 'on' : '' }}">
            {{ $label }} ({{ $counts[$key] ?? 0 }})
        </a>
    @endforeach
</div>

@forelse ($reviews as $review)
    @php
        $q = $review->question;
        [$sbClass, $sbLabel] = $statusBadge[$q->status] ?? ['badge-soft', ucfirst(str_replace('_', ' ', $q->status))];
    @endphp
    <div class="flagq">
        <div class="flagq-main">
            <div class="flex items-center" style="flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                <span class="badge {{ $sbClass }}">{{ $sbLabel }}</span>
                <span style="font-size:13px;font-weight:700;">{{ $q->subject?->name }}</span>
                @if ($q->subject?->level)<span style="font-size:12px;font-weight:800;color:var(--ink);">{{ $q->subject->level }}</span>@endif
                <span style="font-size:12px;color:var(--text-faint);">{{ $q->source_paper }} · Q{{ $q->question_number }} · {{ $q->year }}</span>
                @if ($q->topic)<span class="badge badge-emerald">{{ $q->topic->external_id }}. {{ $q->topic->title }}</span>@endif
                @if ($review->status === 'decided' && $review->outcome)
                    @php [$ocClass, $ocLabel] = $outcomeBadge[$review->outcome] ?? ['badge-soft', ucfirst($review->outcome)]; @endphp
                    <span class="badge {{ $ocClass }}">{{ $ocLabel }}</span>
                    @if ($review->propagation_status === 'propagation_pending')
                        <span class="badge badge-review">Propagation pending</span>
                    @endif
                @endif
            </div>
            @include('v2.partials.question_card', ['q' => $q])
        </div>

        <div class="flagq-side">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);">
                {{ $review->reports->count() }} {{ \Illuminate\Support\Str::plural('report', $review->reports->count()) }}
            </div>

            @forelse ($review->reports as $flag)
                <div class="flag-item">
                    <div class="flex items-center" style="justify-content:space-between;gap:8px;">
                        <span style="font-size:13px;font-weight:700;">{{ $flag->reasonLabel() }}</span>
                        <span class="badge {{ $flag->level === 'teacher' ? 'badge-review' : 'badge-soft' }}" style="font-size:10px;">{{ ucfirst($flag->level) }}</span>
                    </div>
                    @if ($flag->note)
                        <div style="font-size:12.5px;color:var(--text-soft);margin-top:5px;line-height:1.5;">{{ $flag->note }}</div>
                    @endif
                    @if ($flag->screenshot_path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($flag->screenshot_path) }}" target="_blank" rel="noopener"
                           style="display:inline-block;margin-top:8px;">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($flag->screenshot_path) }}" alt="screenshot"
                                 style="max-width:100%;border:1px solid var(--border);border-radius:8px;" loading="lazy">
                        </a>
                    @endif
                    <div style="font-size:11.5px;color:var(--text-faint);margin-top:8px;">
                        @if ($flag->level === 'teacher')
                            {{ $flag->teacher?->name ?? 'Teacher' }}
                        @else
                            {{ $flag->student?->name ?? 'Student' }}@if ($flag->student?->roll_number) (Roll {{ $flag->student->roll_number }})@endif
                        @endif
                        @if ($flag->school) · {{ $flag->school->name }}@endif · {{ $flag->created_at?->diffForHumans() }}
                    </div>
                </div>
            @empty
                <div style="font-size:12.5px;color:var(--text-faint);">No individual reports are linked to this review.</div>
            @endforelse

            @if ($status === 'open')
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
                    <a href="{{ route('v2.super_admin.question_bank.edit', [$q, 'quality_review_id' => $review->id]) }}" class="btn btn-ghost btn-sm" style="justify-content:center;">
                        <x-icon name="edit" size="13"/> Correct question (new version)…
                    </a>
                    <form method="POST" action="{{ route('v2.super_admin.question_flags.correct', $review) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;">
                            <x-icon name="check" size="13"/> Mark correct (matches source)
                        </button>
                    </form>
                    <p style="font-size:11px;color:var(--text-faint);margin:2px 0 0;line-height:1.5;">
                        “Mark correct” keeps the question unchanged and returns it to the pool. “Correct question” lets you edit it and choose a cosmetic or material outcome.
                    </p>
                </div>
            @else
                <div style="font-size:12px;color:var(--text-soft);border-top:1px solid var(--border);padding-top:10px;line-height:1.6;">
                    @if ($review->resultingVersion)
                        Corrected to <a href="{{ route('v2.super_admin.question_bank.versions', $q) }}" style="color:var(--accent);">v{{ $review->resultingVersion->version_number }}</a>.
                    @endif
                    @if ($review->reviewed_at) Reviewed {{ $review->reviewed_at->diffForHumans() }}.@endif
                    @if ($review->propagation_status === 'propagation_pending')
                        <div style="margin-top:6px;color:var(--text-faint);">Affected historical exams will be updated when propagation runs.</div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@empty
    <div style="padding:48px;text-align:center;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);color:var(--text-faint);font-size:14px;">
        @if ($status === 'open')
            No open reviews. Nothing is waiting on Support right now. 🎉
        @else
            Nothing decided yet.
        @endif
    </div>
@endforelse
@endsection
