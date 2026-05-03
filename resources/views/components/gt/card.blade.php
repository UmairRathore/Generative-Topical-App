@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-6',
    'accent' => false,
])
<div {{ $attributes->merge([
    'class' => 'rounded-xl bg-white border border-brand-border shadow-sm'
        . ($accent ? ' border-l-4 border-l-brand-gold' : ''),
]) }}>
    @if($title || $subtitle || isset($header))
        <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-brand-border">
            <div>
                @if($title)<h3 class="font-display text-lg font-semibold text-brand-emerald">{{ $title }}</h3>@endif
                @if($subtitle)<p class="text-sm text-brand-slate mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($header){{ $header }}@endisset
        </div>
    @endif
    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="px-6 py-4 border-t border-brand-border bg-brand-surface rounded-b-xl">
            {{ $footer }}
        </div>
    @endisset
</div>
