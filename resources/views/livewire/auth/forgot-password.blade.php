<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);
        Password::sendResetLink($this->only('email'));
        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex items-center justify-center" style="min-height: 100vh; padding: 60px 20px;">
    <div style="width: 100%; max-width: 460px; background: var(--surface); border-radius: 14px; padding: 44px; box-shadow: var(--shadow-md); border: 1px solid var(--border);">
        <div class="flex items-center" style="gap: 12px; margin-bottom: 24px;">
            <x-crest size="32"/>
            <div class="serif" style="font-size: 19px; font-weight: 600;">Generative Topical</div>
        </div>
        <div class="uppercase-eyebrow">Reset password</div>
        <h1 class="serif" style="font-size: 28px; font-weight: 600; margin-top: 8px;">Forgot your password?</h1>
        <p style="color: var(--text-soft); margin-top: 6px; font-size: 14px;">Enter your email and we'll send you a reset link.</p>

        @if(session('status'))
            <div style="margin-top: 18px; padding: 12px 14px; border: 1px solid #BBF7D0; background: var(--success-soft); color: #15803D; border-radius: 8px; font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        <form wire:submit="sendPasswordResetLink" style="margin-top: 24px;">
            <label class="label">Email address</label>
            <input class="input" type="email" wire:model="email" placeholder="you@school.edu.pk" autofocus required/>
            @error('email')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 22px; padding: 14px 20px;">
                Email reset link <x-icon name="send" size="14"/>
            </button>
        </form>

        <p class="text-center" style="margin-top: 24px; font-size: 13px; color: var(--text-soft);">
            Remembered it?
            <a href="{{ route('login') }}" wire:navigate style="color: var(--gold-700); font-weight: 600;">Back to sign in</a>
        </p>
    </div>
</div>
