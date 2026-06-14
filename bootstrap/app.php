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
        $middleware->alias([
            'admin'                  => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'teacher'                => \App\Http\Middleware\EnsureUserIsTeacher::class,
            'v2.must_change_password' => \App\Http\Middleware\V2\MustChangePassword::class,
            'v2.session_version'      => \App\Http\Middleware\V2\CheckSessionVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
