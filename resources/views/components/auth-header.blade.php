@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1.5 text-center">
    <h1 class="font-display text-2xl font-semibold tracking-tight text-brand-emerald">{{ $title }}</h1>
    <p class="text-sm text-brand-charcoal/70">{{ $description }}</p>
</div>
