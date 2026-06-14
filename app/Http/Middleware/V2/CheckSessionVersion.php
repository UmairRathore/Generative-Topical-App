<?php

namespace App\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionVersion
{
    public function handle(Request $request, Closure $next, string $guard, string $loginRoute): Response
    {
        $user = auth($guard)->user();

        if ($user && isset($user->session_version)) {
            $key            = "v2_sv_{$guard}";
            $storedVersion  = $request->session()->get($key);

            if ($storedVersion === null) {
                $request->session()->put($key, $user->session_version);
            } elseif ((int) $storedVersion !== (int) $user->session_version) {
                auth($guard)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route($loginRoute)
                    ->with('error', 'Your account session has been invalidated. Please sign in again.');
            }
        }

        return $next($request);
    }
}
