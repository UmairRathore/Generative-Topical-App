@extends('v2.layouts.auth')

@section('content')
<div class="flex items-center justify-center" style="min-height: 100vh; background: var(--ivory-deep);">
    <div style="width: 100%; max-width: 420px; padding: 40px;">
        <div class="flex items-center gap-3" style="margin-bottom: 28px;">
            <x-crest size="32" variant="mono-dark"/>
            <div class="serif" style="font-size: 18px; font-weight: 600;">Generative Topical</div>
        </div>

        <div style="padding: 6px 12px; background: rgba(var(--accent-rgb),0.1); border: 1px solid rgba(var(--accent-rgb),0.3); border-radius: 6px; font-size: 12px; font-weight: 600; color: var(--gold-700); text-transform: uppercase; letter-spacing: 0.08em; display: inline-flex; margin-bottom: 16px;">
            Action Required
        </div>

        <h2 class="serif" style="font-size: 28px; font-weight: 600; letter-spacing: -0.01em;">Set your password</h2>
        <p style="color: var(--text-soft); margin-top: 8px; font-size: 14px; line-height: 1.5;">
            For security, please set a new password before accessing your dashboard.
        </p>

        @if($errors->any())
            <div style="margin-top: 16px; padding: 12px 14px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; font-size: 13px; color: #991b1b;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('v2.branch.change_password.post') }}" style="margin-top: 28px;">
            @csrf
            <label class="label">New password</label>
            <input class="input" type="password" name="password" required autofocus autocomplete="new-password"/>

            <div style="margin-top: 16px;">
                <label class="label">Confirm new password</label>
                <input class="input" type="password" name="password_confirmation" required autocomplete="new-password" style="margin-top: 6px;"/>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 22px; padding: 14px 20px; font-size: 14px;">
                Set password & continue
            </button>
        </form>
    </div>
</div>
@endsection
