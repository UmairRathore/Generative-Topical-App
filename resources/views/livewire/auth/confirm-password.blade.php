<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    public function confirmPassword(): void
    {
        $this->validate(['password' => ['required', 'string']]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        session(['auth.password_confirmed_at' => time()]);
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex items-center justify-center" style="min-height: 100vh; padding: 60px 20px;">
    <div style="width: 100%; max-width: 460px; background: var(--surface); border-radius: 14px; padding: 44px; box-shadow: var(--shadow-md); border: 1px solid var(--border);">
        <div class="flex items-center" style="gap: 12px; margin-bottom: 24px;">
            <x-crest size="32"/>
            <div class="serif" style="font-size: 19px; font-weight: 600;">Generative Topical</div>
        </div>
        <div class="uppercase-eyebrow">Secure area</div>
        <h1 class="serif" style="font-size: 28px; font-weight: 600; margin-top: 8px;">Confirm your password</h1>
        <p style="color: var(--text-soft); margin-top: 6px; font-size: 14px;">Re-enter your password to continue to this restricted area.</p>

        <form wire:submit="confirmPassword" style="margin-top: 24px;">
            <label class="label">Password</label>
            <input class="input" type="password" wire:model="password" required autofocus autocomplete="current-password"/>
            @error('password')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 22px; padding: 14px 20px;">Confirm</button>
        </form>
    </div>
</div>
