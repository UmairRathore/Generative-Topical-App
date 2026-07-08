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
        // capped. 300/min survives the most image-dense result pages while
        // making bulk corpus pulls slow (anti-scraping audit, P1).
        RateLimiter::for('secure-image', fn (Request $r) => Limit::perMinute(300)->by((string) $r->query('u', $r->ip())));

        // Auth routes - 5 attempts / 15 min per the security spec, keyed by IP+email.
        RateLimiter::for('v2-login', fn (Request $r) => Limit::perMinutes(15, 5)->by($r->ip().'|'.$r->input('email')));

        // A key that survives across guards: the authenticated V2 actor, else IP.
        $actorKey = function (Request $r): string {
            $a = v2_actor();

            return $a ? $a['guard'].'#'.$a['id'] : 'ip#'.$r->ip();
        };

        // Full-question reveals (review + studio). A revising student rarely
        // needs more; a scraper iterating their whole bank hits the wall fast.
        // Burst cap first (30 / 10 min), then the hourly ceiling (120/h).
        RateLimiter::for('mistake-view', fn (Request $r) => [
            Limit::perMinutes(10, 30)->by($actorKey($r)),
            Limit::perHour(120)->by($actorKey($r)),
        ]);

        // Display-only asset fetches from the studio/review AJAX endpoint.
        RateLimiter::for('mistake-asset', fn (Request $r) => Limit::perHour(120)->by($actorKey($r)));

        // AI tutor: per-minute pacing + a soft daily cost cap with a friendly
        // message (never payment-flavoured).
        $aiDaily = fn (string $msg) => fn ($request, array $headers) => response()->json(
            ['ok' => false, 'error' => $msg], 429, $headers
        );
        RateLimiter::for('ai-message', fn (Request $r) => [
            Limit::perMinute(10)->by($actorKey($r)),
            Limit::perDay(300)->by($actorKey($r))
                ->response($aiDaily("You've used today's AI tutor time - it resets tomorrow. Your chats are saved.")),
        ]);
        RateLimiter::for('ai-quiz', fn (Request $r) => [
            Limit::perMinute(5)->by($actorKey($r)),
            Limit::perDay(60)->by($actorKey($r))
                ->response($aiDaily("You've reached today's quiz limit - it resets tomorrow. Try reviewing your earlier quizzes.")),
        ]);
    }
}
