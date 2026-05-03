@props(['score' => 0, 'total' => 100, 'inverted' => false])
@php
    $pct = $total > 0 ? $score / $total : 0;
    $r = 80;
    $c = 2 * M_PI * $r;
    $offset = $c * (1 - $pct);
@endphp
<div class="relative" style="width: 200px; height: 200px;">
    <svg viewBox="0 0 200 200" style="width: 100%; height: 100%; transform: rotate(-90deg);">
        <circle cx="100" cy="100" r="{{ $r }}" fill="none" stroke="{{ $inverted ? 'rgba(255,255,255,0.1)' : 'var(--slate-100)' }}" stroke-width="14"/>
        <circle cx="100" cy="100" r="{{ $r }}" fill="none" stroke="var(--accent)" stroke-width="14" stroke-linecap="round"
                stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $offset }}"/>
    </svg>
    <div class="absolute flex flex-col items-center justify-center" style="inset: 0;">
        <div class="serif" style="font-size: 56px; font-weight: 600; color: {{ $inverted ? 'var(--ivory)' : 'var(--text)' }}; line-height: 1;">
            {{ round($pct * 100) }}<span style="font-size: 22px; color: {{ $inverted ? 'rgba(250,247,239,0.6)' : 'var(--text-faint)' }};">%</span>
        </div>
        <div style="font-size: 12px; color: {{ $inverted ? 'rgba(250,247,239,0.7)' : 'var(--text-faint)' }}; margin-top: 4px;">{{ $score }} / {{ $total }} correct</div>
    </div>
</div>
