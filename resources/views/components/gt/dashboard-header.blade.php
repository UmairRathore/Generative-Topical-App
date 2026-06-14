@props([
    'eyebrow' => null,
    'title' => '',
    'subtitle' => null,
])
<header class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        @if($eyebrow)
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate mb-1">{{ $eyebrow }}</p>
        @endif
        <h1 class="font-display text-3xl md:text-4xl font-semibold tracking-tight text-brand-emerald">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-sm text-brand-charcoal/70 mt-1 max-w-2xl">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
