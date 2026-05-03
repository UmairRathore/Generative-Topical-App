<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PasswordReset) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', __($status));
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex items-center justify-center" style="min-height: 100vh; padding: 60px 20px;">
    <div style="width: 100%; max-width: 460px; background: var(--surface); border-radius: 14px; padding: 44px; box-shadow: var(--shadow-md); border: 1px solid var(--border);">
        <div class="flex items-center" style="gap: 12px; margin-bottom: 24px;">
            <x-crest size="32"/>
            <div class="serif" style="font-size: 19px; font-weight: 600;">Generative Topical</div>
        </div>
        <div class="uppercase-eyebrow">Reset password</div>
        <h1 class="serif" style="font-size: 28px; font-weight: 600; margin-top: 8px;">Choose a new password</h1>
        <p style="color: var(--text-soft); margin-top: 6px; font-size: 14px;">Pick something strong — at least 8 characters.</p>

        <form wire:submit="resetPassword" style="margin-top: 24px;">
            <label class="label">Email</label>
            <input class="input" type="email" wire:model="email" required autocomplete="email"/>
            @error('email')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

            <label class="label" style="margin-top: 14px;">New password</label>
            <input class="input" type="password" wire:model="password" required autocomplete="new-password"/>
            @error('password')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

            <label class="label" style="margin-top: 14px;">Confirm new password</label>
            <input class="input" type="password" wire:model="password_confirmation" required autocomplete="new-password"/>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 22px; padding: 14px 20px;">Reset password</button>
        </form>
    </div>
</div>
