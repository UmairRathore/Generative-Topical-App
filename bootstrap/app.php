<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/v2.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Inertia (Learning Studio / React). Passes through non-Inertia
        // requests untouched, so the Livewire side of the app is unaffected.
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin'                  => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'teacher'                => \App\Http\Middleware\EnsureUserIsTeacher::class,
            'v2.must_change_password' => \App\Http\Middleware\V2\MustChangePassword::class,
            'v2.session_version'      => \App\Http\Middleware\V2\CheckSessionVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Each portal has its own login page. Send an unauthenticated guest to the
        // login for the guard they actually failed - not the generic /login.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 401);
            }

            $logins = [
                'v2_super_admin'  => 'v2.super_admin.login',
                'v2_school_admin' => 'v2.school.login',
                'v2_branch_admin' => 'v2.branch.login',
                'v2_teacher'      => 'v2.teacher.login',
                'v2_student'      => 'v2.student.login',
            ];

            foreach ($e->guards() as $guard) {
                if (isset($logins[$guard]) && \Illuminate\Support\Facades\Route::has($logins[$guard])) {
                    return redirect()->guest(route($logins[$guard]));
                }
            }

            return redirect()->guest(\Illuminate\Support\Facades\Route::has('login') ? route('login') : '/');
        });
    })->create();
