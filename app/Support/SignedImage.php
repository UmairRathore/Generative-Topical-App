<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Signed image URLs
|--------------------------------------------------------------------------
| Builds + verifies the cryptographic token that gates every question image.
| Token = HMAC-SHA256( user_id | role | exam_id | question_id | expires | path ),
| mirroring the Content-Security spec. The URL is bound to the viewer (so a
| harvested link is useless without that user's session) and expires.
*/
class SignedImage
{
    /** Build a signed URL for an image path, bound to the current viewer. */
    public static function url(string $path, array $ctx = []): string
    {
        $actor = $ctx['actor'] ?? v2_actor();
        $u = (string) ($actor['id'] ?? '0');
        $r = (string) ($actor['role'] ?? 'guest');
        $e = (string) ($ctx['exam_id'] ?? '');
        $q = (string) ($ctx['question_id'] ?? '');
        $ttl = (int) ($ctx['ttl'] ?? config('secureimages.ttl'));
        $x = time() + max(30, $ttl);

        $params = [
            'p' => $path,
            'u' => $u,
            'r' => $r,
            'e' => $e,
            'q' => $q,
            'x' => $x,
            's' => self::sign($path, $u, $r, $e, $q, $x),
        ];

        return route('v2.secure_image').'?'.http_build_query(array_filter($params, fn ($v) => $v !== ''));
    }

    /** Recompute the HMAC for a token's fields. */
    public static function sign(string $path, string $u, string $r, string $e, string $q, int $x): string
    {
        $payload = implode('|', [$u, $r, $e, $q, $x, $path]);

        return hash_hmac('sha256', $payload, (string) config('secureimages.secret'));
    }

    /**
     * Validate the request's token. Returns the verified image path, or null if
     * the signature is wrong / expired / the path is outside an allowed prefix.
     */
    public static function verify(array $p): ?string
    {
        $path = (string) ($p['p'] ?? '');
        $u = (string) ($p['u'] ?? '');
        $r = (string) ($p['r'] ?? '');
        $e = (string) ($p['e'] ?? '');
        $q = (string) ($p['q'] ?? '');
        $x = (int) ($p['x'] ?? 0);
        $sig = (string) ($p['s'] ?? '');

        if ($path === '' || $sig === '') {
            return null;
        }
        if (! hash_equals(self::sign($path, $u, $r, $e, $q, $x), $sig)) {
            return null;
        }
        if ($x < time()) {
            return null; // expired
        }
        if (str_contains($path, '..')) {
            return null;
        }
        $ok = false;
        foreach ((array) config('secureimages.allowed_prefixes', []) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $ok = true;
                break;
            }
        }

        return $ok ? $path : null;
    }
}
