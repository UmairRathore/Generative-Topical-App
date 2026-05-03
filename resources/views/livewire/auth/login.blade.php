<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;
    public string $tab = 'student';

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) return;
        event(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());
        throw ValidationException::withMessages(['email' => __('auth.throttle', [
            'seconds' => $seconds, 'minutes' => ceil($seconds / 60),
        ])]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="grid" style="min-height: 100vh; grid-template-columns: 1.05fr 1fr; background: var(--ivory-deep);">
    {{-- LEFT brand panel --}}
    <div class="relative flex flex-col" style="background: linear-gradient(135deg, var(--emerald-900), var(--emerald-800) 70%); color: var(--ivory); padding: 60px 70px; overflow: hidden;">
        <div class="grain absolute" style="inset: 0;"></div>
        <div class="absolute" style="right: -120px; bottom: -120px; opacity: 0.06;"><x-crest size="500" variant="mono-light"/></div>
        <div class="relative flex items-center" style="z-index: 1; gap: 12px;">
            <x-crest size="36" variant="mono-light"/>
            <div class="serif" style="font-size: 20px; font-weight: 600;">Generative Topical</div>
        </div>
        <div class="relative" style="margin-top: auto; z-index: 1;">
            <div class="uppercase-eyebrow" style="color: var(--accent);">Cambridge MCQ Practice · Pakistan</div>
            <h1 class="serif" style="font-size: 52px; font-weight: 600; line-height: 1.05; margin-top: 12px; letter-spacing: -0.02em;">
                Past papers, <em style="color: var(--accent); font-style: italic;">perfected.</em>
            </h1>
            <p style="font-size: 17px; color: rgba(250,247,239,0.8); line-height: 1.55; max-width: 480px; margin-top: 18px;">
                A premium MCQ generator built with Cambridge teachers in Lahore, Karachi and Islamabad. Generate 40-question papers, review weak topics, give every student room to grow.
            </p>
            <div class="flex" style="gap: 32px; margin-top: 36px; padding-top: 24px; border-top: 1px solid rgba(212,164,55,0.25);">
                @foreach([['50K+', 'Questions'], ['120+', 'Schools'], ['9', 'Subjects']] as [$n, $l])
                    <div>
                        <div class="serif" style="font-size: 28px; font-weight: 600; color: var(--accent);">{{ $n }}</div>
                        <div style="font-size: 11px; color: rgba(250,247,239,0.6); text-transform: uppercase; letter-spacing: 0.08em; margin-top: 2px;">{{ $l }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RIGHT form --}}
    <div class="flex items-center justify-center" style="padding: 40px 60px;">
        <div style="width: 100%; max-width: 420px;">
            <div class="uppercase-eyebrow">Sign in</div>
            <h2 class="serif" style="font-size: 32px; font-weight: 600; margin-top: 8px; letterspacing: -0.01em;">Welcome back</h2>
            <p style="color: var(--text-soft); margin-top: 6px; font-size: 14px;">Choose your account type to continue.</p>

            {{-- role tabs --}}
            <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 24px; padding: 4px; background: var(--soft-surface); border-radius: 8px;">
                @foreach([['student', 'Student', 'user'], ['teacher', 'Teacher', 'edit'], ['admin', 'Admin', 'shield']] as [$k, $l, $i])
                    <button type="button" wire:click="$set('tab', '{{ $k }}')"
                            class="flex items-center justify-center"
                            style="padding: 10px 12px; border-radius: 6px; border: 0; gap: 6px;
                                   background: {{ $tab === $k ? 'var(--surface)' : 'transparent' }};
                                   box-shadow: {{ $tab === $k ? 'var(--shadow-sm)' : 'none' }};
                                   font-size: 13px; font-weight: 600;
                                   color: {{ $tab === $k ? 'var(--emerald-800)' : 'var(--text-soft)' }};">
                        <x-icon :name="$i" size="13"/>{{ $l }}
                    </button>
                @endforeach
            </div>

            <form wire:submit="login" style="margin-top: 28px;">
                <label class="label">Email or registration number</label>
                <input class="input" type="email" wire:model="email"
                       placeholder="{{ $tab === 'student' ? 'ayesha.f@isl.edu.pk' : ($tab === 'teacher' ? 's.iqbal@isl.edu.pk' : 'admin@gt.pk') }}"
                       autofocus required autocomplete="email"/>
                @error('email')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

                <div class="flex justify-between items-baseline" style="margin-top: 16px;">
                    <label class="label" style="margin-bottom: 0;">Password</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate style="font-size: 11px; color: var(--gold-700); text-decoration: none;">Forgot password?</a>
                    @endif
                </div>
                <input class="input" type="password" wire:model="password" required autocomplete="current-password" style="margin-top: 6px;"/>
                @error('password')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

                <label class="flex items-center" style="gap: 8px; margin-top: 14px; font-size: 13px; color: var(--text-soft);">
                    <input type="checkbox" wire:model="remember" style="accent-color: var(--emerald-800);"/>
                    Keep me signed in on this device
                </label>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 22px; padding: 14px 20px; font-size: 14px;">
                    Sign in as {{ ucfirst($tab) }}
                </button>
            </form>

            <hr class="divider" style="margin: 26px 0;"/>
            <div class="flex" style="gap: 8px;">
                <button class="btn btn-ghost" style="flex: 1;"><x-icon name="shield" size="13"/>SSO with school</button>
                <button class="btn btn-ghost" style="flex: 1;">Use access code</button>
            </div>
            <p class="text-center" style="margin-top: 24px; font-size: 13px; color: var(--text-soft);">
                New to Generative Topical?
                <a href="{{ route('register') }}" wire:navigate style="color: var(--gold-700); font-weight: 600; text-decoration: none;">Create an account →</a>
            </p>
            <a href="{{ route('home') }}" wire:navigate class="btn btn-ghost btn-sm" style="margin-top: 16px; width: 100%;">
                <x-icon name="chev-l" size="12"/>Back to homepage
            </a>
        </div>
    </div>
</div>
