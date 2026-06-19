@extends('v2.layouts.auth')

@section('content')
<div class="grid" style="min-height: 100vh; grid-template-columns: 1.05fr 1fr; background: var(--ivory-deep);">

    <div class="relative flex flex-col" style="background: linear-gradient(135deg, var(--emerald-900), var(--emerald-800) 70%); color: var(--ivory); padding: 60px 70px; overflow: hidden;">
        <div class="grain absolute" style="inset: 0;"></div>
        <div class="absolute" style="right: -120px; bottom: -120px; opacity: 0.06;"><x-crest size="500" variant="mono-light"/></div>
        <div class="relative flex items-center" style="z-index: 1; gap: 12px;">
            <x-crest size="36" variant="mono-light"/>
            <div class="serif" style="font-size: 20px; font-weight: 600;">Generative Topical</div>
        </div>
        <div class="relative" style="margin-top: auto; z-index: 1;">
            <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent);">For Educators</div>
            <h1 class="serif" style="font-size: 44px; font-weight: 600; line-height: 1.05; margin-top: 12px; letter-spacing: -0.02em;">
                Teacher <em style="color: var(--accent); font-style: italic;">Portal</em>
            </h1>
            <p style="font-size: 16px; color: rgba(250,247,239,0.75); line-height: 1.6; max-width: 420px; margin-top: 16px;">
                Build topical tests, track student progress, and manage your classes - all in one place.
            </p>
        </div>
    </div>

    <div class="flex items-center justify-center" style="padding: 40px 60px;">
        <div style="width: 100%; max-width: 400px;">
            <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Teacher Sign In</div>
            <h2 class="serif" style="font-size: 30px; font-weight: 600; margin-top: 8px; letter-spacing: -0.01em;">Welcome back</h2>
            <p style="color: var(--text-soft); margin-top: 6px; font-size: 14px;">Sign in with your school-issued credentials.</p>

            @if($errors->any())
                <div style="margin-top: 16px; padding: 12px 14px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; font-size: 13px; color: #991b1b;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('v2.teacher.login.post') }}" style="margin-top: 28px;">
                @csrf
                <label class="label">Email address</label>
                <input class="input" type="email" name="email" value="{{ old('email') }}"
                       placeholder="teacher@school.edu.pk" autofocus required autocomplete="email"/>

                <div style="margin-top: 18px;">
                    <label class="label">Password</label>
                    <input class="input" type="password" name="password" required autocomplete="current-password" style="margin-top: 6px;"/>
                </div>

                <label class="flex items-center" style="gap: 8px; margin-top: 14px; font-size: 13px; color: var(--text-soft);">
                    <input type="checkbox" name="remember" style="accent-color: var(--emerald-800);"/>
                    Keep me signed in
                </label>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 22px; padding: 14px 20px; font-size: 14px;">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
