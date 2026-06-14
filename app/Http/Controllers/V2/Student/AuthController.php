<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('v2.student.auth.login');
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
            'guard'        => 'v2_student',
            'attempted_at' => now(),
        ];

        if (! Auth::guard('v2_student')->attempt($credentials, $request->boolean('remember'))) {
            LoginAttempt::create(array_merge($attempted, ['successful' => false]));
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        LoginAttempt::create(array_merge($attempted, ['successful' => true]));

        $user = Auth::guard('v2_student')->user();
        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        $request->session()->regenerate();

        return redirect()->route('v2.student.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('v2_student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('v2.student.login');
    }
}
