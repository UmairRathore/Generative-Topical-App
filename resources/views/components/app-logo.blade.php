@props(['variant' => 'auto'])
{{-- variant: auto = emerald plate (light surface), on-dark = transparent (emerald sidebars), mark = icon only --}}
@php
    $isDark = $variant === 'on-dark';
    $isMark = $variant === 'mark';
@endphp

<div class="flex items-center gap-2.5">
    <div @class([
        'flex aspect-square size-9 items-center justify-center rounded-lg',
        'bg-brand-emerald ring-1 ring-brand-emerald/40' => ! $isDark,
    ])>
        <x-app-logo-icon class="size-7" />
    </div>
    @unless($isMark)
        <div class="grid leading-tight">
            <span @class([
                'font-display text-base font-semibold tracking-tight',
                'text-white' => $isDark,
                'text-brand-emerald' => ! $isDark,
            ])>Generative Topical</span>
            <span @class([
                'text-[11px] uppercase tracking-[0.2em] font-medium',
                'text-brand-gold-soft' => $isDark,
                'text-brand-gold' => ! $isDark,
            ])>Cambridge MCQ Platform</span>
        </div>
    @endunless
</div>
