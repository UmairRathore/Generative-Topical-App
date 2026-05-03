<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $institution = '';
    public string $city = 'Lahore';
    public string $password = '';
    public string $role_choice = 'student';

    public function register(): void
    {
        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name'  => ['required', 'string', 'max:120'],
            'email'      => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'   => ['required', 'string', 'confirmed:password', Rules\Password::defaults()],
            'role_choice'=> ['required', 'in:student,teacher,admin'],
        ]);

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => match($validated['role_choice']) {
                'teacher' => UserRole::Teacher,
                'admin' => UserRole::Admin,
                default => UserRole::Student,
            },
        ]);

        event(new Registered($user));
        Auth::login($user);
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex items-center justify-center" style="min-height: 100vh; padding: 60px 20px;">
    <div style="width: 100%; max-width: 540px; background: var(--surface); border-radius: 14px; padding: 44px; box-shadow: var(--shadow-md); border: 1px solid var(--border);">
        <div class="flex items-center" style="gap: 12px; margin-bottom: 24px;">
            <x-crest size="32"/>
            <div class="serif" style="font-size: 19px; font-weight: 600;">Generative Topical</div>
        </div>
        <div class="uppercase-eyebrow">Create account</div>
        <h1 class="serif" style="font-size: 32px; font-weight: 600; margin-top: 8px; letter-spacing: -0.015em;">Start your 14-day free trial</h1>
        <p style="color: var(--text-soft); margin-top: 6px; font-size: 14px;">No card required. Cancel anytime.</p>

        <form wire:submit="register">
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 24px;">
                <div>
                    <label class="label">First name</label>
                    <input class="input" wire:model="first_name" placeholder="Ayesha" required autofocus/>
                    @error('first_name')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Last name</label>
                    <input class="input" wire:model="last_name" placeholder="Farooq" required/>
                    @error('last_name')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="label" style="margin-top: 14px;">Email</label>
            <input class="input" type="email" wire:model="email" placeholder="you@school.edu.pk" required autocomplete="email"/>
            @error('email')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

            <label class="label" style="margin-top: 14px;">I am a</label>
            <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 8px;">
                @foreach([['student', 'Student'], ['teacher', 'Teacher'], ['admin', 'School Admin']] as [$k, $l])
                    <button type="button" wire:click="$set('role_choice', '{{ $k }}')"
                            class="chip {{ $role_choice === $k ? 'chip-active' : '' }}"
                            style="padding: 10px 12px; font-size: 13px; justify-content: center;">{{ $l }}</button>
                @endforeach
            </div>

            <label class="label" style="margin-top: 14px;">School / institution</label>
            <input class="input" wire:model="institution" placeholder="Lahore Grammar School"/>

            <label class="label" style="margin-top: 14px;">City</label>
            <select class="select" wire:model="city">
                @foreach(['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Other'] as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>

            <label class="label" style="margin-top: 14px;">Password</label>
            <input class="input" type="password" wire:model="password" placeholder="At least 8 characters" required autocomplete="new-password"/>
            @error('password')<p style="color: var(--error); font-size: 12px; margin-top: 4px;">{{ $message }}</p>@enderror

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 24px; padding: 14px 20px; font-size: 14px;">
                Create account &amp; start trial
            </button>
        </form>

        <p class="text-center" style="margin-top: 18px; font-size: 12px; color: var(--text-faint); line-height: 1.55;">
            By creating an account, you agree to our Terms of Service and Privacy Policy.
        </p>
        <p class="text-center" style="margin-top: 14px; font-size: 13px; color: var(--text-soft);">
            Already have an account? <a href="{{ route('login') }}" wire:navigate style="color: var(--gold-700); font-weight: 600;">Sign in</a>
        </p>
    </div>
</div>
