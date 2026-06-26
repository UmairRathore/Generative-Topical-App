{{-- Shared result header: circular % badge + a strip of stat chips.
     Props: $title, $subtitle, $submittedLine (plain strings), $attempt, $breakdown. --}}
@php
    $pct = $attempt->percentage;
    $tone = $pct >= 60 ? 'var(--ok)' : ($pct >= 40 ? 'var(--warn)' : 'var(--bad)');
    $chips = [
        ['Score', $attempt->score.' / '.$attempt->total_questions, 'var(--text)'],
        ['Percentage', $pct.'%', $tone],
        ['Correct', $breakdown['correct'], 'var(--ok)'],
        ['Wrong', $breakdown['wrong'], 'var(--bad)'],
        ['Unattempted', $breakdown['unattempted'], 'var(--text-soft)'],
        ['Time taken', $attempt->time_taken ?? '-', 'var(--text)'],
    ];
    if (! empty($breakdown['voided'])) {
        $chips[] = ['Excluded', $breakdown['voided'], 'var(--text-soft)'];
    }
@endphp
<div class="rs-strip" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); margin-bottom: 18px;">
    <div class="flex items-center gap-6 rs-hero" style="flex-wrap: wrap;">
        <div style="flex: none; width: 92px; height: 92px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 4px solid {{ $tone }};">
            <div class="serif" style="font-size: 25px; font-weight: 700; color: {{ $tone }};">{{ $pct }}%</div>
        </div>
        <div style="min-width: 0;">
            <h2 class="serif" style="font-size: 23px; font-weight: 600;">{{ $title }}</h2>
            @if (! empty($subtitle))<div style="font-size: 13.5px; color: var(--text-soft); margin-top: 3px;">{{ $subtitle }}</div>@endif
            @if (! empty($submittedLine))<div style="font-size: 12px; color: var(--text-faint); margin-top: 3px;">{{ $submittedLine }}</div>@endif
        </div>
    </div>

    <div class="flex items-center" style="flex-wrap: wrap; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border);">
        @foreach ($chips as [$label, $val, $color])
            <div style="flex: 1 1 120px; min-width: 108px; background: var(--soft-surface); border: 1px solid var(--border); border-radius: 10px; padding: 10px 13px;">
                <div style="font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-faint);">{{ $label }}</div>
                <div style="font-size: 18px; font-weight: 700; margin-top: 3px; color: {{ $color }};">{{ $val }}</div>
            </div>
        @endforeach
    </div>
</div>
