@props([
    'tone' => 'neutral', // neutral | emerald | gold | success | warning | error | info
    'size' => 'md',      // sm | md
    'icon' => null,
])
@php
    $tones = [
        'neutral' => 'bg-brand-surface text-brand-slate border border-brand-border',
        'emerald' => 'bg-brand-emerald/10 text-brand-emerald border border-brand-emerald/20',
        'gold'    => 'bg-brand-gold/15 text-brand-emerald border border-brand-gold/40',
        'success' => 'bg-status-success/10 text-status-success border border-status-success/30',
        'warning' => 'bg-status-warning/10 text-status-warning border border-status-warning/30',
        'error'   => 'bg-status-error/10 text-status-error border border-status-error/30',
        'info'    => 'bg-status-info/10 text-status-info border border-status-info/30',
    ];
    $sizes = [
        'sm' => 'text-[11px] px-2 py-0.5',
        'md' => 'text-xs px-2.5 py-1',
    ];
@endphp
<span {{ $attributes->merge(['class' =>
    'inline-flex items-center gap-1 rounded-full font-medium '
    . $tones[$tone] . ' ' . $sizes[$size]
]) }}>
    @if($icon)<flux:icon :name="$icon" class="size-3" variant="micro" />@endif
    {{ $slot }}
</span>
