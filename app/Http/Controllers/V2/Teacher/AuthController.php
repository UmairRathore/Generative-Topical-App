<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Models\V2\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('v2.teacher.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $attempted = [
            'email'        => $request->email,
            'ip_address'   => $request->ip(),
            'guard'        => 'v2_teacher',
            'attempted_at' => now(),
        ];

        if (! Auth::guard('v2_teacher')->attempt($credentials, $request->boolean('remember'))) {
            LoginAttempt::create(array_merge($attempted, ['successful' => false]));
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        LoginAttempt::create(array_merge($attempted, ['successful' => true]));

        $user = Auth::guard('v2_teacher')->user();
        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        $request->session()->regenerate();

        if ($user->must_change_password) {
            return redirect()->route('v2.teacher.change_password');
        }

        return redirect()->route('v2.teacher.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('v2_teacher')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('v2.teacher.login');
    }

    public function showChangePassword()
    {
        return view('v2.teacher.auth.change_password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        Auth::guard('v2_teacher')->user()->update([
            'password'             => $request->password,
            'must_change_password' => false,
        ]);

        return redirect()->route('v2.teacher.dashboard')
            ->with('success', 'Password changed successfully.');
    }
}
