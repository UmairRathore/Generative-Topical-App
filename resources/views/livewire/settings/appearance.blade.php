<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] class extends Component {}; ?>

<x-settings.layout heading="Display" subheading="Choose how Generative Topical looks on your device.">
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 12px;" x-data>
        @foreach([['light', 'sun', 'Light', 'Bright ivory canvas'], ['dark', 'moon', 'Dark', 'Emerald-on-charcoal'], ['system', 'globe', 'System', 'Match your OS']] as [$value, $icon, $label, $desc])
            <button type="button"
                    @click="$flux.appearance = '{{ $value }}'"
                    class="text-left" style="padding: 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface); cursor: pointer;"
                    x-bind:style="$flux.appearance === '{{ $value }}' ? 'border: 1.5px solid var(--emerald-800); background: var(--emerald-50);' : ''">
                <div class="flex items-center justify-center" style="width: 36px; height: 36px; border-radius: 8px; background: var(--emerald-50); color: var(--emerald-800); margin-bottom: 12px;">
                    <x-icon :name="$icon" size="18"/>
                </div>
                <div class="serif" style="font-size: 16px; font-weight: 600;">{{ $label }}</div>
                <div style="font-size: 12px; color: var(--text-soft); margin-top: 2px;">{{ $desc }}</div>
            </button>
        @endforeach
    </div>

    <p style="margin-top: 18px; font-size: 12px; color: var(--text-faint);">
        Tip: dark mode flips the brand accent from emerald to gold for contrast.
    </p>
</x-settings.layout>
