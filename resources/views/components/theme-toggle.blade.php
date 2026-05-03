@props([
    'variant' => 'auto', // auto = light surface (public/marketing); dark = on-dark surface (sidebar/test header)
])
@php
    $isOnDark = $variant === 'dark';
    $base = 'inline-flex items-center justify-center rounded-md border transition cursor-pointer';
    $cls  = $isOnDark
        ? 'border-transparent text-white/70 hover:text-white hover:bg-white/10'
        : 'border-brand-border text-brand-slate hover:text-brand-emerald hover:border-brand-emerald/40 bg-white';
@endphp
<button
    type="button"
    x-data
    x-on:click="$flux.appearance = ($flux.appearance === 'dark' ? 'light' : 'dark')"
    {{ $attributes->merge(['class' => $base.' '.$cls]) }}
    style="width: 32px; height: 32px;"
    :title="$flux.appearance === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
    :aria-label="$flux.appearance === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
>
    <span x-show="$flux.appearance !== 'dark'" x-cloak>
        <x-icon name="moon" size="15"/>
    </span>
    <span x-show="$flux.appearance === 'dark'" x-cloak>
        <x-icon name="sun" size="15"/>
    </span>
</button>
