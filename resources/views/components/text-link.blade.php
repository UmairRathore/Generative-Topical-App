<a
    {{ $attributes->merge(['class' => 'text-sm font-medium text-brand-emerald hover:text-brand-forest underline decoration-brand-gold/60 underline-offset-2 transition']) }}
    wire:navigate
>
    {{ $slot }}
</a>
