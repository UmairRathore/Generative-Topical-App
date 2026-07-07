# 19 — Audit Logging

> Code-grounded reference. Verify against source before acting.

**Siblings:** [16 — Notifications](./16-notifications.md) ·
[17 — Secure Images](./17-secure-images.md) ·
[18 — Import & Data Pipeline](./18-import-and-data-pipeline.md)

---

## Purpose

An append-only trail of privileged platform actions (exam create/release, student
create/enroll, quality-review decisions, propagation, subject/class edits, …) plus
a separate record of every login attempt. Intended as the super-admin's "who did
what, when, from where" record.

**Status: Partially implemented.** The **write path is live and used across many
controllers**; the **super-admin viewer UI is a static stub** — it does not query
`v2_audit_logs` (see [Current Implementation](#current-implementation)).

---

## Users / Roles

- **Written by:** any V2 actor performing an audited action (super-admin,
  school-admin, teacher, student — whoever the `AuditLogger` resolves). Falls back
  to `actor_type = 'System'`, `actor_id = 0` when no guard is authenticated.
- **Read by (intended):** super-admin, via `v2/super_admin/audit`. **Currently the
  viewer shows a hardcoded empty table** (see below).
- **Login attempts** are written by every role's `AuthController`.

---

## Current Implementation

### Audit write path

**Model** — `app/Models/V2/AuditLog.php` (`$table = 'v2_audit_logs'`,
`$timestamps = false`). Static helper:

```php
AuditLog::log(Model $actor, string $action, ?Model $target = null, ?array $details = null): static
```

Stores `class_basename($actor)` / `$actor->getKey()`, the action string,
optional `class_basename($target)` / key, JSON `details`, and `request()->ip()`.

**Service** — `app/Services/V2/AuditLogger.php`. Preferred entry point:

```php
AuditLogger::record(string $action, ?Model $target = null, ?array $details = null, ?Model $actor = null): AuditLog
```

`record()` resolves to the container instance and calls `log()`, which **auto-detects
the actor** across guards (`v2_super_admin` → `v2_school_admin` → `v2_teacher` →
`v2_student`; note `v2_branch_admin` is **not** in this fallback chain) and falls
back to `'System'`/`0`. Same field mapping as the model.

**Callers (verified — non-exhaustive):**

- `Http/Controllers/V2/Teacher/ExamController.php` — `exam.created`,
  `exam.created_custom`, `exam.released`, `exam.results_released` /
  `exam.results_hidden`, `exam.question_sent_for_review`.
- `Http/Controllers/V2/SchoolAdmin/StudentController.php` — `student.created`,
  `student.updated`, `student.{status}`, `student.password_reset`,
  `student.enrolled`, `student.unenrolled`.
- `SchoolAdmin/{ClassController,SubjectController,TeacherController,GradeController}`,
  `SuperAdmin/{QuestionBankController,QuestionFlagController,SubjectController}`,
  `Teacher/QuestionFlagController`, `Student/{QuestionFlagController,ExamController}`.
- `Services/V2/PropagationService.php` — `quality_review.propagated`.

Action naming convention: dotted `noun.verb` (e.g. `exam.released`,
`student.enrolled`, `quality_review.propagated`).

### Audit viewer (stub — not wired)

`routes/v2.php` line ~133:

```php
Route::view('audit', 'v2.super_admin.audit.index')->name('audit.index');
// stub - audit viewer not built yet
```

`resources/views/v2/super_admin/audit/index.blade.php` renders a fixed table with
columns **Actor · Action · Target · IP · When** and a single hardcoded row
"No audit entries yet." **It does not query `v2_audit_logs`.** There is no
controller — it's a `Route::view`. So: rows are being recorded, but the UI does not
surface them yet. **Partially implemented.**

### Login attempts

**Model** — `app/Models/V2/LoginAttempt.php` (`$table = 'v2_login_attempts'`,
`$timestamps = false`). Fields `email`, `ip_address`, `guard`, `successful`
(bool), `attempted_at`.

Written by every `Http/Controllers/V2/*/AuthController.php` on both failure and
success, e.g. `SuperAdmin/AuthController::login()`:

```php
LoginAttempt::create([...'successful' => false]); // on failed attempt()
LoginAttempt::create([...'successful' => true]);  // on success
```

**Record-only — no enforcement.** A grep for reads of `v2_login_attempts` /
`LoginAttempt::where` finds **none**: there is no lockout / throttle-by-history /
brute-force detection consuming this data. It is purely an audit record today.
(Laravel's route `throttle` middleware may rate-limit login endpoints separately,
but it does not read this table.)

---

## Data Model

**`v2_audit_logs`** — `database/migrations/2026_06_13_000006_create_v2_audit_logs_table.php`:

| Column | Notes |
| --- | --- |
| `id` | PK |
| `actor_type` (string 50) | `class_basename` of the actor (e.g. `Teacher`, `SchoolAdmin`) or `System` |
| `actor_id` (unsigned big) | actor key, or `0` for System |
| `action` (string) | dotted action name |
| `target_type` (string 50, null) | `class_basename` of the affected model |
| `target_id` (unsigned big, null) | affected model key |
| `details` (json, null) | free-form context payload |
| `ip_address` (string 45, null) | `request()->ip()` |
| `created_at` (timestamp, `useCurrent`) | **no `updated_at`** — append-only |

Indexes: `(actor_type, actor_id)`, `created_at`.

> **Field-name correction vs. the module brief:** the columns are
> `actor_type`/`actor_id` and `target_type`/`target_id` with a `details` JSON blob
> and `ip_address` — **not** `user_type`/`user_id` or a `changes` column. Verify
> against the migration before writing queries.

**`v2_login_attempts`** — `…000007_create_v2_login_attempts_table.php`:
`id`, `email`, `ip_address` (45), `guard` (50), `successful` (bool, default false),
`attempted_at` (`useCurrent`). Indexes `(email, ip_address)`, `attempted_at`.

Both tables are **append-only** (no `updated_at`, no update/delete paths in code).

---

## Core Flows

1. **Audited action.** Controller/service performs the mutation, then calls
   `AuditLogger::record('noun.verb', $target, $details)`. Actor + IP captured
   automatically.
2. **Login attempt.** `AuthController::login()` records one row per attempt
   (success or failure) with the guard name.
3. **Review (intended).** Super-admin opens `v2/super_admin/audit` — **currently a
   static empty table**; wiring it to `AuditLog::latest()->paginate()` is the
   pending work.

---

## Inputs

- Actor (auto-resolved from guards or passed explicitly), action string, optional
  target model + `details` array.
- Login credentials + guard (for `LoginAttempt`).

## Outputs

- Append-only rows in `v2_audit_logs` / `v2_login_attempts`.

---

## Dependencies

- `Illuminate\Support\Facades\Auth` (actor resolution), `request()->ip()`.
- Every audited controller/service listed above.

---

## Security / Access Rules

- Tables are write-only in the codebase (append-only; no update/delete).
- The viewer route is inside the super-admin route group (auth enforced by that
  group), but the view exposes nothing yet.
- `details` may carry ids (e.g. `class_id`, `question_id`, student email on
  `student.created`) — treat as sensitive; scope any future viewer to super-admin.

---

## Existing AI-Relevant Context

- The `action` + `details` shape is a clean, structured event stream — a good
  substrate for future usage/behaviour analytics.
- The `System`/`0` actor fallback distinguishes automated writes from human ones.

## AI Opportunities

- **Recommended for AI (future):** an **AI-usage / cost log** should follow this
  same append-only pattern (actor, action, JSON details, timestamp, IP) but live in
  its **own dedicated table** — do **not** overload `v2_audit_logs` with token/cost
  records. Mirror the `AuditLogger::record()` ergonomics for a new `AiUsageLogger`.
- Anomaly summaries over `v2_login_attempts` (failed-attempt clustering) are a
  natural read-side AI feature — the write side already captures the data.

## AI Risks

- Audit rows are a **security artifact**; an AI feature must never mutate or delete
  them, and must not surface another tenant's rows (respect school/branch scoping in
  any future viewer).
- Do not infer a lockout/security posture that isn't implemented — login attempts
  are currently **recorded but not enforced**.

## Future Improvements

- **Not implemented:** the audit **viewer** (wire the stub blade to query
  `v2_audit_logs` with actor/action/date filters + pagination).
- **Not implemented:** any consumer of `v2_login_attempts` (lockout, brute-force
  alerting, IP reputation).
- **Not implemented:** a dedicated AI-usage/cost audit table (recommended above).
- `v2_branch_admin` is absent from the `AuditLogger` actor-resolution fallback —
  branch-admin actions would log as `System` unless the actor is passed explicitly.
  Verify whether that's intentional.
