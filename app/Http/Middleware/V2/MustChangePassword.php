<?php

namespace App\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MustChangePassword
{
    public function handle(Request $request, Closure $next, string $guard, string $changeRoute): Response
    {
        $user = auth($guard)->user();

        if ($user && $user->must_change_password && ! $request->routeIs($changeRoute)) {
            return redirect()->route($changeRoute);
        }

        return $next($request);
    }
}
