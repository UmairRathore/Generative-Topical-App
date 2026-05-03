<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }
        Auth::user()->update(['password' => Hash::make($validated['password'])]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('password-updated');
    }
}; ?>

<x-settings.layout heading="Password & security" subheading="Use a long, unique password to keep your account secure.">
    <form wire:submit="updatePassword">
        <label class="label">Current password</label>
        <input class="input" type="password" wire:model="current_password" required autocomplete="current-password"/>
        @error('current_password')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

        <label class="label" style="margin-top: 14px;">New password</label>
        <input class="input" type="password" wire:model="password" required autocomplete="new-password"/>
        @error('password')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

        <label class="label" style="margin-top: 14px;">Confirm new password</label>
        <input class="input" type="password" wire:model="password_confirmation" required autocomplete="new-password"/>

        <div class="flex items-center" style="gap: 10px; margin-top: 28px;">
            <button type="submit" class="btn btn-primary">Update password</button>
            <x-action-message on="password-updated" style="font-size: 12px; color: var(--success);">Saved.</x-action-message>
        </div>
    </form>
</x-settings.layout>
