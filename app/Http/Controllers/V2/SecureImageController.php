<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\SignedImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| Secure image serving
|--------------------------------------------------------------------------
| The single endpoint every question/option/diagram image is fetched through.
| It (1) verifies the HMAC token + expiry, (2) binds the URL to the logged-in
| viewer (a leaked link is useless without that user's session), (3) silently
| flags automation/headless signals for review, and (4) streams the file from
| the configured disk - presigning an S3 URL when the disk isn't local.
*/
class SecureImageController extends Controller
{
    public function show(Request $request): Response
    {
        // 1. Token integrity + expiry + path allow-list.
        $path = SignedImage::verify($request->query());
        abort_if($path === null, 403, 'Invalid or expired image link.');

        // 2. Viewer binding - the token's user must be the authenticated V2 actor.
        $actor = v2_actor();
        abort_if($actor === null, 403);
        abort_unless((string) $actor['id'] === (string) $request->query('u', ''), 403);

        // 3. Silent automation/headless detection (serve anyway; flag for review).
        $this->flagAutomation($request, $actor);

        // 4. Serve from the configured disk.
        $disk = (string) config('secureimages.disk', 'public');

        if ($disk !== 'public') {
            $remote = Storage::disk($disk);
            abort_unless($remote->exists($path), 404);

            // Presigned, short-lived redirect (S3 / R2 fallback - config-only switch).
            if (method_exists($remote, 'temporaryUrl')) {
                return redirect()->away($remote->temporaryUrl($path, now()->addSeconds((int) config('secureimages.ttl'))));
            }

            return $remote->response($path);
        }

        $public = Storage::disk('public');
        abort_unless($public->exists($path), 404);

        return $public->response($path, basename($path), [
            'Cache-Control' => 'private, max-age=60, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Header + nothing-blocking heuristics for headless browsers / scrapers.
     * Per the security spec this NEVER blocks (no signal to the attacker) - it
     * just records an anomaly so a session can be reviewed.
     */
    private function flagAutomation(Request $request, array $actor): void
    {
        $ua = (string) $request->userAgent();
        $signals = [];

        if ($ua === '' || preg_match('/HeadlessChrome|PhantomJS|Selenium|puppeteer|playwright|python-requests|curl|wget|Go-http|node-fetch/i', $ua)) {
            $signals[] = 'user-agent';
        }
        if ($request->header('Accept-Language') === null) {
            $signals[] = 'no-accept-language';
        }
        // Real browsers send Sec-Fetch-* for sub-resource (image) requests.
        if ($request->header('Sec-Fetch-Site') === null && $request->header('Sec-Fetch-Dest') === null) {
            $signals[] = 'no-sec-fetch';
        }

        if (count($signals) >= 2) {
            Log::channel(config('logging.default'))->warning('secure-image: automation signals', [
                'actor' => $actor['role'].'#'.$actor['id'],
                'signals' => $signals,
                'ua' => $ua,
                'ip' => $request->ip(),
            ]);
        }
    }
}
