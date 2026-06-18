@extends('v2.layouts.branch_admin')
@section('page_title', 'Topics')

@section('content')
<a href="{{ route('v2.branch.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 16px;">
    <x-icon name="chev-l" size="12"/> Back to Analytics
</a>

<h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 3px;">Topics</h2>
<p style="color: var(--text-soft); font-size: 13px; margin-bottom: 20px;">Answer-weighted accuracy per topic, and how single-topic vs mixed exams compare.</p>

{{-- Single vs mixed exam split --}}
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 22px;">
    @foreach ([['Single-topic exams', $split['single']], ['Mixed exams', $split['mixed']]] as [$label, $s])
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-weight: 600;">{{ $label }}</div>
            <div class="flex items-baseline gap-2" style="margin-top: 6px;">
                <span class="serif" style="font-size: 24px; font-weight: 600;">{{ $s['avg'] !== null ? $s['avg'].'%' : '—' }}</span>
                <span style="font-size: 12.5px; color: var(--text-soft);">avg · {{ $s['exams'] }} {{ \Illuminate\Support\Str::plural('exam', $s['exams']) }} · {{ $s['submissions'] }} subs</span>
            </div>
        </div>
    @endforeach
</div>

{{-- Topic bars --}}
<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px;">Accuracy by topic — whole school</div>
    @include('v2.partials.topic_bars', ['stats' => $topicStats, 'empty' => 'No submissions yet.'])
</div>
@endsection
