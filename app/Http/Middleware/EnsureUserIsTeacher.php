<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isTeacher() && ! $user->isAdmin())) {
            abort(403, 'Teacher access required.');
        }

        return $next($request);
    }
}
