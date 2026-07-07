# 17 — Secure Images

> Code-grounded reference. Verify against source before acting.

**Siblings:** [16 — Notifications](./16-notifications.md) ·
[18 — Import & Data Pipeline](./18-import-and-data-pipeline.md) ·
[19 — Audit Logging](./19-audit-logging.md) ·
[15 — Notes Module](./15-notes-module.md) (consumer)

---

## Purpose

Every question / option / diagram image in V2 is served through a single
signed-URL endpoint instead of a public static path. Each URL carries an
HMAC-SHA256 token that is **bound to the viewer** and **expires**, so a harvested
link is useless without that user's live session and cannot be regenerated
off-platform. This is the platform's anti-scraping / question-bank-protection
control.

**Status: Implemented in code.**

---

## Users / Roles

Open to **any** authenticated V2 guard (super-admin, school-admin, branch-admin,
teacher, student). The token encodes the viewer's `id` + `role`; the controller
then re-checks that the token's `u` (user id) matches the currently
authenticated V2 actor (`v2_actor()`). There is no unauthenticated access.

---

## Current Implementation

Three pieces:

1. **`app/Support/SignedImage.php`** — token mint + verify.
   - `url(string $path, array $ctx = []): string` — builds the signed URL bound to
     the current viewer (`v2_actor()`), with query params
     `p` (path), `u` (user id), `r` (role), `e` (exam_id), `q` (question_id),
     `x` (expiry epoch), `s` (HMAC). Empty context fields are stripped. Expiry =
     `time() + max(30, ttl)` where `ttl` defaults to `config('secureimages.ttl')`.
   - `sign($path, $u, $r, $e, $q, $x): string` —
     `hash_hmac('sha256', "{u}|{r}|{e}|{q}|{x}|{path}", config('secureimages.secret'))`.
     **Token = HMAC-SHA256( user_id | role | exam_id | question_id | expiry | path ).**
   - `verify(array $p): ?string` — returns the verified path or `null`. Checks:
     non-empty path + sig; `hash_equals` on the recomputed HMAC (constant-time);
     `x >= time()` (not expired); path has no `..`; path starts with an allowed
     prefix. Defence-in-depth against tampering, replay-after-expiry, and path
     traversal.

2. **`app/Http/Controllers/V2/SecureImageController.php`** (`show`):
   1. `SignedImage::verify($request->query())` → 403 on `null`.
   2. **Viewer binding:** `abort_unless((string)$actor['id'] === (string)$request->query('u'))`
      — a leaked link tied to user A is 403 for user B.
   3. **Silent automation detection** (`flagAutomation`) — heuristics on
      `User-Agent` (HeadlessChrome/Selenium/puppeteer/playwright/curl/wget/… ),
      missing `Accept-Language`, missing `Sec-Fetch-*`. **Never blocks** (no signal
      to the attacker); logs a `warning` when ≥ 2 signals fire.
   4. **Serve:** from `config('secureimages.disk')`. If not `public` and the disk
      supports `temporaryUrl()`, redirects to a short-lived presigned S3/R2 URL;
      otherwise streams. On `public`, streams via `Storage::disk('public')->response()`
      with headers `Cache-Control: private, max-age=60, no-store` and
      `X-Content-Type-Options: nosniff`.

3. **`config/secureimages.php`:**
   - `disk` — `env('SECURE_IMAGE_DISK', 'public')`. S3/R2 is a **config-only**
     switch (controller presigns automatically).
   - `secret` — `env('SECURE_IMAGE_SECRET', env('APP_KEY'))`.
   - `ttl` — `env('SECURE_IMAGE_TTL', 21600)` = **6 hours** default. (Comment: a
     student exam keeps a page open for the whole sitting; tighten per-surface via
     `SignedImage::url($p, ['ttl' => 60])`.)
   - `allowed_prefixes` — `['v2/questions/']`. Only paths under these prefixes are
     ever served.

**Route** — `routes/v2.php`:

```php
Route::get('img', [SecureImageController::class, 'show'])
    ->middleware('throttle:secure-image')->name('v2.secure_image');
```

**Rate limiter** — `app/Providers/AppServiceProvider.php`:

```php
RateLimiter::for('secure-image',
    fn (Request $r) => Limit::perMinute(1200)->by((string) $r->query('u', $r->ip())));
```

1200/min keyed on the token's user id (falls back to IP) — high enough for a
lazy-loaded exam page, low enough to blunt bulk pulls.

**Helper** — `simg(?string $path, array $ctx = []): string` in
`app/Support/helpers.php`. Wraps `SignedImage::url`; returns `''` for a null path.
**Use `simg()` instead of `asset('storage/'.$path)` everywhere an image is shown.**

**`v2_actor()`** (same helper file) resolves the current V2 user across all five
guards as `['id','role','guard']`, or `null`.

---

## Data Model

No dedicated table. The token is **stateless** (self-verifying HMAC). Image paths
live on the configured disk under `v2/questions/…` (see
[module 18](./18-import-and-data-pipeline.md) for where files originate). Path
strings are stored on `v2_question_images.image_path` (web-relative, e.g.
`v2/questions/<paper_stem>/<file>`).

---

## Core Flows

1. **Question render.** A blade partial calls `simg($image->image_path, [...])` →
   viewer-bound URL → browser requests `v2/img?...` → controller verifies + streams.
2. **Learning Hub solution figures.**
   `app/Http/Controllers/V2/Student/LearningHubController.php` mints
   `SignedImage::url($im->image_path, ['question_id' => $qid])` for the question
   diagram(s) shown in the review studio.
3. **Notes `question_figure` blocks** (links [module 15](./15-notes-module.md)).
   `app/Services/V2/NotesDocumentService::figureUrls()` mints **fresh** signed URLs
   on every view — **nothing is stored in the note** — and only for questions the
   student still reaches through a **released** mistake (access re-check via
   `StudentMistake` + `results_released_at`). Loses access → figure won't resolve,
   by design.
4. **Admin asset review.**
   `app/Http/Controllers/V2/SuperAdmin/QuestionAssetReviewController.php` signs the
   question images for the review UI.

**Blade consumers of `simg()`** (verified): `v2/partials/question_card.blade.php`,
`question_stem.blade.php`, `answer_review.blade.php`,
`v2/student/exams/take.blade.php`, and the super-admin question-bank
`_uploader`/`form` blades.

---

## Inputs

- A storage-relative image path (must start with an allowed prefix).
- Optional context: `exam_id`, `question_id`, `ttl`, `actor` override.
- The authenticated V2 session (for the viewer-binding re-check on serve).

## Outputs

- A signed URL string (mint side).
- A streamed image response or a presigned redirect (serve side).
- A log `warning` line on automation signals (see [module 19](./19-audit-logging.md)
  for the broader logging story — note this is a plain log channel, **not** the
  `v2_audit_logs` table).

---

## Dependencies

- `config/secureimages.php`, `config/filesystems.php` (disk).
- `v2_actor()` / `simg()` helpers, `Storage`, `RateLimiter`.
- The image files produced by the import/asset pipeline
  ([module 18](./18-import-and-data-pipeline.md)).

---

## Security / Access Rules

- **Token integrity:** constant-time `hash_equals`; secret defaults to `APP_KEY`.
- **Viewer binding:** token `u` must equal the authenticated actor id → leaked
  links are dead for anyone else.
- **Expiry:** 6 h default; per-surface override to 60 s.
- **Path allow-list + no `..`:** only `v2/questions/` paths, blocks traversal.
- **Rate limit:** 1200/min per user.
- **Silent automation flagging:** logs but never blocks (deliberate — no oracle for
  the attacker).
- **Response headers:** `no-store`, `private`, `nosniff` on the public disk path.

---

## Existing AI-Relevant Context

Question figures are already addressed by a **stable pair**: `question_id` +
`image_path`. The signed URL is a derived, ephemeral artifact.

## AI Opportunities

- **Recommended for AI:** when assembling multimodal context for a tutor/report
  agent, pass image **references** (`question_id` + `image_path`) and let the
  renderer mint URLs at display time — the same pattern `NotesDocumentService`
  already uses.

## AI Risks

- **Signed URLs are viewer-bound and expiring.** An AI context payload must **never
  bake a signed URL** — it is scoped to one user + a short window and will 403 for
  the model's fetcher or after expiry. Persisting one in a cache/note is both broken
  and a leak vector. Always carry `{question_id, image_path}` and sign on render.
- Do not widen `allowed_prefixes` or raise `ttl` to make an AI pipeline convenient
  without re-reviewing the anti-scraping posture.

## Future Improvements

- **Partially implemented:** S3/R2 serving path exists (`temporaryUrl`) but the
  default disk is `public`; production hardening (moving originals off the public
  disk) is a config change, not a code change.
- Automation detection is log-only; escalation/blocking is `Not implemented`
  (intentional per the security spec, but a future review queue could consume the
  warnings).
