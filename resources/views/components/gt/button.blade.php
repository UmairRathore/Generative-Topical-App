@props([
    'variant' => 'primary', // primary | secondary | ghost | danger | gold
    'size' => 'md',         // sm | md | lg
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
    $sizes = [
        'sm' => 'text-sm px-3 py-1.5',
        'md' => 'text-sm px-4 py-2.5',
        'lg' => 'text-base px-5 py-3',
    ];
    $variants = [
        'primary'   => 'bg-brand-emerald text-white hover:bg-brand-forest focus-visible:ring-brand-forest shadow-sm',
        'secondary' => 'bg-white text-brand-emerald border border-brand-border hover:border-brand-emerald/50 hover:shadow-sm',
        'ghost'     => 'text-brand-emerald hover:bg-brand-emerald/5',
        'danger'    => 'bg-status-error text-white hover:brightness-110 focus-visible:ring-status-error',
        'gold'      => 'bg-brand-gold text-brand-emerald hover:bg-brand-gold-soft shadow-sm',
    ];
    $cls = $base.' '.$sizes[$size].' '.$variants[$variant];
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>
        @if($icon)<flux:icon :name="$icon" class="size-4" variant="micro" />@endif
        <span>{{ $slot }}</span>
        @if($iconTrailing)<flux:icon :name="$iconTrailing" class="size-4" variant="micro" />@endif
    </a>
@else
    <button {{ $attributes->merge(['class' => $cls, 'type' => 'button']) }}>
        @if($icon)<flux:icon :name="$icon" class="size-4" variant="micro" />@endif
        <span>{{ $slot }}</span>
        @if($iconTrailing)<flux:icon :name="$iconTrailing" class="size-4" variant="micro" />@endif
    </button>
@endif
