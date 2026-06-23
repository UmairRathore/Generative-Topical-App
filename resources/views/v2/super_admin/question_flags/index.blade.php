@extends('v2.layouts.super_admin')
@section('page_title', 'Question Flags')

@php
    $sessionLabels = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];
    $statusBadge = [
        'active'       => ['badge-pass', 'Active'],
        'draft'        => ['badge-soft', 'Draft'],
        'under_review' => ['badge-review', 'Under review'],
        'archived'     => ['badge-blocker', 'Archived'],
    ];
    $tabs = ['open' => 'Open', 'resolved' => 'Fixed', 'dismissed' => 'Dismissed'];
@endphp

@section('content')
<style>
    .flagq{display:flex;border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface);overflow:hidden;margin-bottom:18px;}
    .flagq-main{flex:1;min-width:0;padding:20px 22px;border-right:1px solid var(--border);}
    .flagq-side{width:320px;flex:none;padding:18px 20px;background:var(--soft-surface);display:flex;flex-direction:column;gap:12px;}
    .flag-item{border:1px solid var(--border);border-radius:10px;background:var(--bg);padding:11px 13px;}
    .ftab{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:20px;}
    .ftab a{padding:8px 16px;font-size:12.5px;font-weight:600;text-decoration:none;color:var(--text-soft);background:var(--bg);}
    .ftab a.on{background:var(--primary,#061C30);color:#fff;}
    .ftab a + a{border-left:1px solid var(--border);}
    @media (max-width:880px){.flagq{flex-direction:column;}.flagq-main{border-right:0;border-bottom:1px solid var(--border);}.flagq-side{width:auto;}}
</style>

<div class="flex items-start justify-between" style="margin-bottom:6px;gap:16px;flex-wrap:wrap;">
    <div>
        <h2 class="serif" style="font-size:24px;font-weight:600;">Question Flags</h2>
        <p style="color:var(--text-soft);font-size:13px;margin-top:4px;max-width:620px;">
            Teacher-reported questions. A flagged question is pulled from the live pool automatically. Fix it in the
            <a href="{{ route('v2.super_admin.question_bank.index') }}" style="color:var(--accent);">Question Bank</a>
            and set its status back to <strong>active</strong>, then close the flag here.
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

@forelse ($groups as $group)
    @php
        $q = $group->first()->question;
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
            </div>
            @include('v2.partials.question_card', ['q' => $q])
        </div>

        <div class="flagq-side">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);">
                {{ $group->count() }} {{ \Illuminate\Support\Str::plural('report', $group->count()) }}
            </div>

            @foreach ($group as $flag)
                <div class="flag-item">
                    <div style="font-size:13px;font-weight:700;">{{ $flag->reasonLabel() }}</div>
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
                        {{ $flag->teacher?->name ?? 'Teacher' }}@if ($flag->school) · {{ $flag->school->name }}@endif · {{ $flag->created_at?->diffForHumans() }}
                    </div>
                </div>
            @endforeach

            @if ($status === 'open')
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
                    <a href="{{ route('v2.super_admin.question_bank.edit', $q) }}" class="btn btn-ghost btn-sm" style="justify-content:center;">
                        <x-icon name="edit" size="13"/> Fix in Question Bank
                    </a>

                    @if ($q->status !== 'active')
                        <form method="POST" action="{{ route('v2.super_admin.question_bank.status', $q) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;color:var(--ok);">
                                <x-icon name="check" size="13"/> Restore to pool (set active)
                            </button>
                        </form>
                    @endif

                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('v2.super_admin.question_flags.resolve', $q) }}" style="flex:1;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="outcome" value="resolved">
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;"><x-icon name="check" size="13"/> Mark fixed</button>
                        </form>
                        <form method="POST" action="{{ route('v2.super_admin.question_flags.resolve', $q) }}" style="flex:1;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="outcome" value="dismissed">
                            <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">Dismiss</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@empty
    <div style="padding:48px;text-align:center;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);color:var(--text-faint);font-size:14px;">
        @if ($status === 'open')
            No open flags. Teachers haven't reported any questions — or you've cleared them all. 🎉
        @else
            Nothing here.
        @endif
    </div>
@endforelse
@endsection
