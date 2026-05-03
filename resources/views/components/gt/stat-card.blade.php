@props([
    'label' => '',
    'value' => '',
    'delta' => null,
    'deltaTone' => 'success',
    'icon' => null,
    'tone' => 'emerald', // emerald | gold | neutral
])
@php
    $iconBg = [
        'emerald' => 'bg-brand-emerald/10 text-brand-emerald border-brand-emerald/20',
        'gold'    => 'bg-brand-gold/15 text-brand-emerald border-brand-gold/40',
        'neutral' => 'bg-brand-surface text-brand-slate border-brand-border',
    ][$tone];
    $deltaCls = [
        'success' => 'text-status-success',
        'error'   => 'text-status-error',
        'warning' => 'text-status-warning',
        'neutral' => 'text-brand-slate',
    ][$deltaTone] ?? 'text-brand-slate';
@endphp

<div class="rounded-xl bg-white border border-brand-border p-5 shadow-sm">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-[11px] uppercase tracking-[0.2em] text-brand-slate font-semibold">{{ $label }}</p>
            <p class="mt-2 font-display text-3xl font-semibold text-brand-emerald">{{ $value }}</p>
            @if($delta)
                <p class="mt-1 text-xs {{ $deltaCls }} font-medium">{{ $delta }}</p>
            @endif
        </div>
        @if($icon)
            <div class="size-10 rounded-lg border flex items-center justify-center {{ $iconBg }}">
                <flux:icon :name="$icon" class="size-5" variant="outline" />
            </div>
        @endif
    </div>
</div>
