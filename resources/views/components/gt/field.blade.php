@props([
    'label' => null,
    'name',
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'autocomplete' => null,
    'required' => false,
    'autofocus' => false,
    'hint' => null,
    'trailing' => null,
])
@php
    $errorMsg = $errors->first($name);
@endphp

<div class="grid gap-1.5">
    @if($label)
        <label for="{{ $name }}" class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-slate">
            {{ $label }}
        </label>
    @endif

    <div class="relative">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($required) required @endif
            @if($autofocus) autofocus @endif
            value="{{ $value }}"
            {{ $attributes->merge([
                'class' => 'w-full rounded-md border bg-white px-3 py-2.5 text-sm text-brand-charcoal placeholder:text-brand-slate/60 transition focus:outline-none focus:ring-2 focus:ring-brand-emerald/30 '
                    . ($errorMsg ? 'border-status-error focus:border-status-error' : 'border-brand-border focus:border-brand-emerald')
                    . ($trailing ? ' pr-10' : '')
            ]) }}
        />
        @if($trailing)
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 text-brand-slate">{{ $trailing }}</div>
        @endif
    </div>

    @if($errorMsg)
        <p class="text-xs text-status-error">{{ $errorMsg }}</p>
    @elseif($hint)
        <p class="text-xs text-brand-slate">{{ $hint }}</p>
    @endif
</div>
