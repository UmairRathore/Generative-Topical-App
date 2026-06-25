@extends('v2.layouts.super_admin')
@section('page_title', 'Propagate correction')

@php
    $q = $review->question;
    $c = $preview['counts'];
    $selected = $preview['selected'];
@endphp

@section('content')
<style>
    .pg-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px 20px;margin-bottom:16px;}
    .pg-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;}
    .pg-metric{background:var(--soft-surface);border:1px solid var(--border);border-radius:10px;padding:12px 14px;}
    .pg-metric .n{font-size:24px;font-weight:700;line-height:1;}
    .pg-metric .l{font-size:11px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;margin-top:6px;}
    .vrow{display:flex;align-items:flex-start;gap:10px;border:1px solid var(--border);border-radius:10px;padding:11px 13px;margin-bottom:8px;background:var(--bg);}
    .vrow.dim{opacity:.6;}
    table.bt{width:100%;border-collapse:collapse;font-size:12.5px;}
    table.bt th,table.bt td{padding:7px 10px;border-bottom:1px solid var(--border);text-align:left;}
    table.bt th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-faint);}
</style>

<a href="{{ route('v2.super_admin.question_flags.index', ['status' => 'decided']) }}" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-soft);text-decoration:none;margin-bottom:14px;">
    <x-icon name="chev-l" size="14"/> Back to Quality Reviews
</a>

<div style="margin-bottom:18px;">
    <div style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--text-faint);">Material error · Quality review #{{ $review->id }}</div>
    <h2 class="serif" style="font-size:24px;font-weight:600;margin-top:4px;">Propagate correction across history</h2>
    <p style="color:var(--text-soft);margin-top:4px;font-size:13.5px;max-width:680px;">
        {{ $q->subject?->name }} · {{ $q->source_paper }} · Q{{ $q->question_number }}.
        Voiding targets the exact <strong>question versions</strong> students saw — the corrected version
        @if ($preview['corrected_number']) (v{{ $preview['corrected_number'] }}) @endif and any later version are never voided.
        Past exams that used a selected faulty version are excluded from marks, rankings &amp; analytics and recomputed; released-exam students whose score changes are notified.
    </p>
</div>

{{-- Version selection — GET form recalculates the blast radius for the ticked set. --}}
<div class="pg-card">
    <div style="font-size:13px;font-weight:700;margin-bottom:4px;">Faulty versions to void</div>
    <p style="font-size:12px;color:var(--text-soft);margin:0 0 12px;">All versions before the correction are pre-selected. Untick any that were actually fine, then recalculate.</p>
    <form method="GET" action="{{ route('v2.super_admin.question_flags.propagate', $review) }}">
        @foreach ($preview['versions'] as $v)
            <label class="vrow {{ $v['selectable'] ? '' : 'dim' }}" style="cursor:{{ $v['selectable'] ? 'pointer' : 'default' }};">
                <input type="checkbox" name="versions[]" value="{{ $v['id'] }}" style="margin-top:3px;flex:none;"
                       @checked($v['selected']) @disabled(! $v['selectable'])>
                <div style="flex:1;min-width:0;">
                    <div class="flex items-center" style="gap:8px;flex-wrap:wrap;">
                        <span class="badge badge-emerald" style="font-weight:700;">v{{ $v['version_number'] }}</span>
                        @if ($v['is_corrected'])<span class="badge badge-pass">Corrected — never voided</span>@endif
                        @if ($v['quality_review_id'])<span class="badge badge-review">QR #{{ $v['quality_review_id'] }}</span>@endif
                        <span style="font-size:12px;color:var(--text-soft);">{{ $v['change_summary'] }}</span>
                    </div>
                    <div style="font-size:11.5px;color:var(--text-faint);margin-top:4px;">
                        {{ $v['created_at']?->format('j M Y') }} · used by {{ $v['exams_count'] }} exam(s) · {{ $v['attempts_count'] }} attempt(s)
                    </div>
                </div>
            </label>
        @endforeach
        <button type="submit" class="btn btn-ghost btn-sm" style="margin-top:8px;"><x-icon name="refresh" size="13"/> Recalculate blast radius</button>
    </form>
</div>

{{-- Blast radius --}}
<div class="pg-card">
    <div style="font-size:13px;font-weight:700;margin-bottom:12px;">Blast radius for the selected versions</div>
    <div class="pg-metrics">
        <div class="pg-metric"><div class="n">{{ $c['schools'] }}</div><div class="l">Schools</div></div>
        <div class="pg-metric"><div class="n">{{ $c['branches'] }}</div><div class="l">Branches</div></div>
        <div class="pg-metric"><div class="n">{{ $c['teachers'] }}</div><div class="l">Teachers</div></div>
        <div class="pg-metric"><div class="n">{{ $c['exams'] }}</div><div class="l">Exams</div></div>
        <div class="pg-metric"><div class="n">{{ $c['attempts'] }}</div><div class="l">Attempts</div></div>
        <div class="pg-metric"><div class="n">{{ $c['would_void'] }}</div><div class="l">Pivots to void</div></div>
        <div class="pg-metric"><div class="n">{{ $c['expected_notifications'] }}</div><div class="l">Student notifications</div></div>
    </div>
    @if ($c['already_teacher'] || $c['already_quality'])
        <p style="font-size:12px;color:var(--text-faint);margin:12px 0 0;">
            Already excluded (left untouched): {{ $c['already_teacher'] }} teacher-local void(s){{ $c['already_quality'] ? ', '.$c['already_quality'].' from a prior propagation' : '' }}.
        </p>
    @endif
</div>

{{-- Sample before/after --}}
@if (! empty($preview['sample']))
    <div class="pg-card">
        <div style="font-size:13px;font-weight:700;margin-bottom:10px;">Sample score changes ({{ count($preview['sample']) }} shown)</div>
        <table class="bt">
            <thead><tr><th>Exam</th><th>Attempt</th><th>Before</th><th>After</th><th>Released</th></tr></thead>
            <tbody>
                @foreach ($preview['sample'] as $s)
                    <tr>
                        <td>{{ \Illuminate\Support\Str::limit($s['exam_title'] ?? ('#'.$s['exam_id']), 32) }}</td>
                        <td>#{{ $s['attempt_id'] }}</td>
                        <td>{{ $s['score_before'] }}/{{ $s['total_before'] }}</td>
                        <td><strong>{{ $s['score_after'] }}/{{ $s['total_after'] }}</strong></td>
                        <td>{{ $s['released'] ? 'yes — will notify' : 'no' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Confirm — posts exactly the versions that produced THIS preview. --}}
<form method="POST" action="{{ route('v2.super_admin.question_flags.propagate.confirm', $review) }}"
      onsubmit="return confirm('Queue propagation? This voids {{ $c['would_void'] }} exam-question(s) across {{ $c['exams'] }} exam(s) and recomputes them. Released-exam students whose score changes will be notified.');">
    @csrf
    @foreach ($selected as $id)
        <input type="hidden" name="versions[]" value="{{ $id }}">
    @endforeach
    <button type="submit" class="btn btn-primary" @disabled($c['would_void'] === 0 && $c['already_teacher'] === 0 && $c['already_quality'] === 0)>
        <x-icon name="check" size="14"/> Confirm &amp; queue propagation
    </button>
    @if ($c['would_void'] === 0)
        <span style="font-size:12px;color:var(--text-faint);margin-left:10px;">No new pivots to void for the current selection.</span>
    @endif
</form>
@endsection
