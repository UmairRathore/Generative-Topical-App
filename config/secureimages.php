<?php

/*
|--------------------------------------------------------------------------
| Secure (signed) image serving
|--------------------------------------------------------------------------
| Question / option / diagram crops are served through a signed-URL endpoint
| (see App\Support\SignedImage + App\Http\Controllers\V2\SecureImageController)
| instead of a public static path. Each URL carries an HMAC token bound to the
| viewer + an expiry, so harvested URLs are useless and cannot be regenerated
| without a platform session. See docs/IMPORTER_AND_DESIGN.md.
*/
return [

    // Disk the originals live on. 'public' today; switch to 's3' (config/filesystems.php)
    // with no code change — the controller presigns S3 URLs when this is not 'public'.
    'disk' => env('SECURE_IMAGE_DISK', 'public'),

    // HMAC secret for the URL token. Defaults to the app key.
    'secret' => env('SECURE_IMAGE_SECRET', env('APP_KEY')),

    // Token lifetime (seconds). The security spec wants 60s for the high-frequency
    // pool browser; student exams keep a page open for the whole sitting, so the
    // default is generous enough to survive a long exam + lazy-loaded images while
    // still expiring leaked URLs. The viewer-binding (user id + role) is the real
    // anti-scraping control. Tighten per-surface via SignedImage::url($p, ['ttl'=>60]).
    'ttl' => (int) env('SECURE_IMAGE_TTL', 21600), // 6 hours

    // Only paths under these prefixes may ever be served (defence-in-depth against
    // path traversal / arbitrary file reads).
    'allowed_prefixes' => ['v2/questions/'],
];
