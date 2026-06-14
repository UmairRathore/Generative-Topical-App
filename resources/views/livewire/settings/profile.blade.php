<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] class extends Component {
    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);
        $user->fill($validated);
        if ($user->isDirty('email')) $user->email_verified_at = null;
        $user->save();
        $this->dispatch('profile-updated', name: $user->name);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();
        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));
            return;
        }
        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<x-settings.layout heading="Profile" subheading="Your name and details visible to teachers and classmates.">
    <div class="flex items-center" style="gap: 18px; margin-bottom: 24px;">
        <div class="flex items-center justify-center serif" style="width: 78px; height: 78px; border-radius: 50%; background: var(--emerald-800); color: var(--ivory); font-size: 26px; font-weight: 600;">
            {{ auth()->user()->initials() }}
        </div>
        <div>
            <button type="button" class="btn btn-ghost btn-sm">Upload new photo</button>
            <p style="font-size: 11px; color: var(--text-faint); margin-top: 6px;">JPG or PNG · max 4MB</p>
        </div>
    </div>

    <form wire:submit="updateProfileInformation">
        <label class="label">Name</label>
        <input class="input" wire:model="name" required autofocus autocomplete="name"/>
        @error('name')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

        <label class="label" style="margin-top: 14px;">Email</label>
        <input class="input" type="email" wire:model="email" required autocomplete="email"/>
        @error('email')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <div style="margin-top: 14px; padding: 12px 14px; border: 1px solid #FDE68A; background: var(--warning-soft); color: #B45309; border-radius: 8px; font-size: 12px;">
                Your email address is unverified.
                <button type="button" wire:click.prevent="resendVerificationNotification" style="margin-left: 6px; background: transparent; border: 0; text-decoration: underline; color: inherit; font-weight: 600;">
                    Resend verification email
                </button>
                @if (session('status') === 'verification-link-sent')
                    <p style="margin-top: 6px; color: var(--success); font-weight: 500;">A new verification link has been sent.</p>
                @endif
            </div>
        @endif

        <div class="flex items-center" style="gap: 10px; margin-top: 28px;">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <x-action-message on="profile-updated" style="font-size: 12px; color: var(--success);">Saved.</x-action-message>
        </div>
    </form>

    <hr class="divider" style="margin: 28px 0 20px;"/>
    <livewire:settings.delete-user-form/>
</x-settings.layout>
