<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }
        Auth::user()->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex items-center justify-center" style="min-height: 100vh; padding: 60px 20px;">
    <div style="width: 100%; max-width: 460px; background: var(--surface); border-radius: 14px; padding: 44px; box-shadow: var(--shadow-md); border: 1px solid var(--border); text-align: center;">
        <x-crest size="48" style="margin: 0 auto;"/>
        <div class="uppercase-eyebrow" style="justify-content: center; margin-top: 18px;">Almost there</div>
        <h1 class="serif" style="font-size: 28px; font-weight: 600; margin-top: 8px;">Check your inbox</h1>
        <p style="color: var(--text-soft); margin-top: 8px; font-size: 14px;">We've sent a verification link to your email address.</p>

        @if (session('status') == 'verification-link-sent')
            <div style="margin-top: 18px; padding: 12px 14px; border: 1px solid #BBF7D0; background: var(--success-soft); color: #15803D; border-radius: 8px; font-size: 13px;">
                A new verification link has just been sent.
            </div>
        @endif

        <button wire:click="sendVerification" class="btn btn-primary" style="width: 100%; margin-top: 22px; padding: 14px 20px;">
            Resend verification email <x-icon name="send" size="14"/>
        </button>

        <button wire:click="logout" type="button" style="margin-top: 14px; background: transparent; border: 0; font-size: 13px; color: var(--text-soft); text-decoration: underline; text-decoration-color: var(--gold-700);">
            Log out
        </button>
    </div>
</div>
