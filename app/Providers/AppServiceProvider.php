<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Secure-image backstop: key by the token's viewer (NOT IP) so a whole
        // classroom behind one NAT IP is never throttled, while a single
        // compromised account that tries to bulk-pull the corpus still gets
        // capped. The doc keeps velocity limits off normal student test-taking.
        RateLimiter::for('secure-image', fn (Request $r) => Limit::perMinute(1200)->by((string) $r->query('u', $r->ip())));

        // Auth routes - 5 attempts / 15 min per the security spec, keyed by IP+email.
        RateLimiter::for('v2-login', fn (Request $r) => Limit::perMinutes(15, 5)->by($r->ip().'|'.$r->input('email')));
    }
}
