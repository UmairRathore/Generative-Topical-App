<?php

namespace App\Services\V2;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;

class LoginRateLimiter
{
    public function __construct(protected RateLimiter $limiter) {}

    public function tooManyAttempts(string $email, string $guard): bool
    {
        return $this->limiter->tooManyAttempts($this->key($email, $guard), 5);
    }

    public function increment(string $email, string $guard): void
    {
        $this->limiter->hit($this->key($email, $guard), 60 * 15);
    }

    public function clear(string $email, string $guard): void
    {
        $this->limiter->clear($this->key($email, $guard));
    }

    public function availableIn(string $email, string $guard): int
    {
        return $this->limiter->availableIn($this->key($email, $guard));
    }

    private function key(string $email, string $guard): string
    {
        return Str::transliterate(Str::lower($email) . '|' . $guard . '|' . request()->ip());
    }
}
