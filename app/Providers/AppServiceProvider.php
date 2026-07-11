<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        // Already-signed-in users who hit any login/guest page are sent to
        // THEIR OWN portal dashboard (not bounced to a generic page). Covers
        // the student case: an authenticated student opening /v2/student/login
        // (or the app root) lands on the student dashboard.
        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            $dashboards = [
                'v2_super_admin'  => 'v2.super_admin.dashboard',
                'v2_school_admin' => 'v2.school.dashboard',
                'v2_branch_admin' => 'v2.branch.index',
                'v2_teacher'      => 'v2.teacher.dashboard',
                'v2_student'      => 'v2.student.dashboard',
            ];
            foreach ($dashboards as $guard => $dashboard) {
                if (Route::has($dashboard) && auth()->guard($guard)->check()) {
                    return route($dashboard);
                }
            }

            return route('v2.student.login');
        });

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
        // NOTE: the quiz limiter (5/min, 60/day) lives in
        // AiTutorController::guardQuizBudget so the "Quiz me" button (/quiz) and
        // a typed "quiz me" (via /messages) share one bucket - a route limiter
        // could only cover the button path.
    }
}
