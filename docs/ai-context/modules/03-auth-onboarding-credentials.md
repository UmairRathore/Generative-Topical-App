# 03 — Auth / Onboarding / Credentials

> Code-grounded reference. Verify against source before acting.

**Siblings:** [01 — Roles, Permissions & Tenancy](./01-roles-permissions-tenancy.md) ·
[02 — School / Branch / Class Structure](./02-school-branch-class-structure.md) ·
[19 — Audit Logging](./19-audit-logging.md) ·
[00 — System Overview](../00-system-overview.md)

---

## Purpose

How each of the five V2 roles **logs in**, how admins **provision** teacher/student accounts in
bulk with temporary passwords, and the security controls around it: forced password change,
session invalidation, login-attempt logging, and audit logging.

Status: **Implemented in code** for all five roles' login + school-admin bulk onboarding.

## Users / Roles

- **Super admin / School admin / Branch admin / Teacher** — password-based login; **forced to
  change password on first login** (`must_change_password`).
- **Student** — password-based login; **NOT forced** to change password (no change-password
  route or controller method exists for students).
- **School admin** is the provisioner: creates teachers and students (single + bulk) and resets
  their passwords.

## Current Implementation

**Per-role auth controllers** (`app/Http/Controllers/V2/{role}/AuthController.php`):
- `SuperAdmin/AuthController.php` — `showLogin`, `login`, `logout`, `showChangePassword`,
  `changePassword`.
- `SchoolAdmin/AuthController.php` — same five methods.
- `BranchAdmin/AuthController.php` — same five methods (login view is `v2.branch_admin.auth.login`
  though the route prefix is `branch`).
- `Teacher/AuthController.php` — same five methods (read in full: `:1-79`).
- `Student/AuthController.php` — **only** `showLogin`, `login`, `logout` (no change-password;
  `:1-55`).

**Onboarding / credentials** (`app/Http/Controllers/V2/SchoolAdmin/`):
- `TeacherController.php` — `store`, `bulkCreate`, `bulkStore`, `resetPassword`, `toggle`.
- `StudentController.php` — `store`, `bulkStore`, `resetPassword`, `toggle`, `enroll`, `unenroll`.
- `app/Services/V2/TempPasswordGenerator.php` — temp password generation.

**Middleware** (`app/Http/Middleware/V2/`):
- `MustChangePassword.php` — redirects to a change-password route while `must_change_password`.
- `CheckSessionVersion.php` — logs a user out when their `session_version` changes.
- Aliases registered in `bootstrap/app.php:24-29`: `v2.must_change_password`,
  `v2.session_version`.

**Rate limiter** — `app/Providers/AppServiceProvider.php:32`:
`RateLimiter::for('v2-login', fn ($r) => Limit::perMinutes(15, 5)->by($r->ip().'|'.$r->input('email')))`
→ **5 attempts / 15 min per IP+email**, applied as `throttle:v2-login` on every login POST
(`routes/v2.php:70,147,237,274,331`).

**Security-log models/tables**: `app/Models/V2/LoginAttempt.php` (`v2_login_attempts`),
`app/Models/V2/AuditLog.php` (`v2_audit_logs`); `app/Services/V2/AuditLogger.php` wraps writes.

**Tables**: `v2_login_attempts` (`2026_06_13_000007…`), `v2_audit_logs` (`2026_06_13_000006…`);
`session_version` added to admin/teacher/student tables via `2026_06_13_000008…`, and present on
`v2_branch_admins` from `2026_06_18_000001…`.

## Data Model

### Credential columns (per person table)
Present on `v2_school_admins`, `v2_branch_admins`, `v2_teachers`, `v2_students` (super admin
differs — see note):
- `password` (bcrypt/hashed via `casts()`), `must_change_password` (boolean),
  `session_version` (int default 1), `last_login_at`, `last_login_ip`, `created_by`, `status`.
- Identifiers: teachers `employee_id` (nullable), students `roll_number` (nullable).
- **Uniqueness**: `v2_super_admins.email`, `v2_school_admins.email`, `v2_branch_admins.email`
  are **globally unique**; `v2_teachers` is unique on **`(email, school_id)`**; `v2_students.email`
  is **nullable with no unique constraint** (students unique on `(roll_number, school_id)`).
- **`must_change_password` default differs by table**: `true` for school-admin/branch-admin/
  teacher tables; **`false`** for `v2_students` (migration default) — matching the missing
  student change-password flow.
- `v2_super_admins` has **no `session_version` and no `school_id`** (platform-wide); its
  `must_change_password` defaults `false` (`app/Models/V2/SuperAdmin.php:15-33`,
  migration `2026_06_13_000001…`).

### `v2_login_attempts` (`app/Models/V2/LoginAttempt.php`)
`email`, `ip_address`, `guard`, `successful` (bool), `attempted_at`. `timestamps = false`.
Index on `(email, ip_address)` and `attempted_at`. Written on **every** login attempt
(success and failure) by all five auth controllers.

### `v2_audit_logs` (`app/Models/V2/AuditLog.php`)
`actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `details` (json), `ip_address`,
`created_at`. `timestamps = false`. Polymorphic (no FKs). Written by **onboarding/management**
actions, **not** by login. `AuditLogger::record($action, $target, $details, $actor)` resolves the
actor from the active guard when not passed (`AuditLogger.php:17-20` — note it checks
super_admin/school_admin/teacher/student, **not** branch_admin, falling back to `'System'`/id 0).
Full detail on the audit trail: [19 — Audit Logging](./19-audit-logging.md).

## Core Flows

### Login (all roles — `login()` in each AuthController)
1. Validate `email` + `password` (`required|email`, `required|string`).
2. `Auth::guard('v2_<role>')->attempt($credentials, remember)`.
3. On failure: write `v2_login_attempts` (`successful=false`) and throw `auth.failed`.
4. On success: write `v2_login_attempts` (`successful=true`); update `last_login_at` /
   `last_login_ip`; `session()->regenerate()`.
5. **Admins + teacher only**: if `must_change_password` → redirect to `…change_password`;
   else dashboard. **Student**: always redirect to `v2.student.dashboard` (no check;
   `Student/AuthController.php:44`).
6. Redirect targets: super admin `…dashboard`; school admin `v2.school.dashboard`; branch admin
   `v2.branch.index`; teacher `v2.teacher.dashboard`; student `v2.student.dashboard`.

### Forced password change (`MustChangePassword`)
`app/Http/Middleware/V2/MustChangePassword.php:11-20` — while `user->must_change_password` and the
request is not already the change-password route, redirect to that route. Applied per role in
`routes/v2.php` as `v2.must_change_password:<guard>,<change_route>` (super admin `:78`, school
admin `:156`, branch admin `:245`, teacher `:282`). **Not applied to students** (no such
middleware on the student group, `:334-378`). `changePassword()` validates
`min:8|confirmed`, sets `password` + `must_change_password=false`, redirects to dashboard
(e.g. `Teacher/AuthController.php:65-78`).

### Session invalidation (`CheckSessionVersion`)
`app/Http/Middleware/V2/CheckSessionVersion.php:11-32` — stores the user's `session_version` in
`v2_sv_<guard>` on first hit; if the DB value later differs, it logs the user out, invalidates the
session, and redirects to login with an error. **Applied only to the School Admin group**
(`routes/v2.php:155-158`, alongside `must_change_password`). It is **not** wired onto
super-admin / branch-admin / teacher / student groups even though those tables carry
`session_version` — so incrementing `session_version` only force-logs-out a **school admin** via
middleware today. (Teachers/students still get a new version bumped on deactivate/reset, which
takes effect on their *next* session-version check — but no middleware currently performs that
check for them.) Treat this as a partial implementation, not a bug to silently "complete".

### Bulk onboarding (school admin)
**Teachers** — `TeacherController::bulkStore()` (`:63-108`):
- Validates `teachers` array `min:1|max:50`; each row `name`, `email`
  (`distinct:ignore_case|unique:v2_teachers,email`), optional `employee_id`.
- Enforces licence cap: `remaining = school.max_teachers - Teacher::count()`; rejects if the
  batch exceeds it.
- In a DB transaction, per row: generate a temp password, `bcrypt` it, create the teacher with
  `school_id`, `must_change_password=true`, `status='active'`, `created_by=admin.id`, audit
  `teacher.created`.
- Returns the `credentials` view listing each `{teacher, plainPassword}` (the **only** time the
  plaintext is shown).

**Students** — `StudentController::bulkStore()` (`:75-119`): same shape, array `min:1|max:100`,
`unique:v2_students,email`, optional `roll_number`, cap `max_students`. **Bulk create does NOT
auto-enroll** into a class (single `store()` does, if `class_id` is supplied).

**Single create** — `TeacherController::store()` / `StudentController::store()`: force
`must_change_password=true`, licence-checked (`checkTeacherLimit`/`checkStudentLimit` abort 422),
audit `*.created`, show credentials once.

**Reset password** — `resetPassword()` (teacher `:159-177`, student `:169-187`): new temp
password, `must_change_password=true`, **`increment('session_version')`** (kicks existing
sessions), audit `*.password_reset`, re-show credentials with `isReset=true`.

**Deactivate** — `toggle()`: on `→ inactive`, `increment('session_version')`; audit
`*.active` / `*.inactive`.

### Temp passwords (`TempPasswordGenerator`)
`app/Services/V2/TempPasswordGenerator.php` — 12 chars by default from the ambiguity-free
alphabet `ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789` (omits `0 O l 1 I`), via
`random_int` (CSPRNG). `generateBatch()` exists but controllers generate per-record so each
account gets a distinct password.

## Inputs

- Login: `email`, `password`, optional `remember`.
- Onboarding: name/email/(employee_id|roll_number) rows; optional `class_id` (single student).
- Change password: `password` + `password_confirmation` (`min:8|confirmed`).

## Outputs

- Authenticated session (guard-scoped); `last_login_*` updated.
- Created accounts with hashed passwords + one-time plaintext shown in the `credentials` view.
- `v2_login_attempts` rows (every attempt); `v2_audit_logs` rows (every management action).

## Dependencies

- Laravel auth guards / `Hash` / `RateLimiter`.
- `app/Services/V2/TempPasswordGenerator.php`, `app/Services/V2/AuditLogger.php`.
- `app/Http/Controllers/V2/SchoolAdmin/BaseController.php` (`admin()`, `schoolId()`).
- Middleware aliases from `bootstrap/app.php`.

## Security / Access Rules

- Brute-force: `throttle:v2-login` (5 / 15 min per IP+email) on all login POSTs.
- Plaintext passwords exist **only** transiently in the create/reset response view; stored values
  are always bcrypt (`casts()['password'] => 'hashed'`).
- Cross-tenant onboarding blocked: created accounts always get the acting admin's `school_id`;
  `authorize*()` checks abort 403 on foreign records.
- Forced first-login password change for admins + teachers (not students).
- `session_version` bump is the deactivate/reset kill-switch — but see the **partial**
  middleware coverage note above.

## Existing AI-Relevant Context

- No AI runs in this module today. Login/onboarding is deterministic.
- `v2_login_attempts` + `v2_audit_logs` are a ready-made behavioural signal source (failed-login
  patterns, provisioning activity) for future analytics.

## AI Opportunities

- **Recommended for AI:** anomaly detection over `v2_login_attempts` (impossible-travel,
  credential-stuffing bursts) — data + guard tagging already present.
- **Recommended for AI:** summarise `v2_audit_logs` into a human-readable admin activity feed
  (would also unblock the stubbed audit viewer, see [02](./02-school-branch-class-structure.md)).

## AI Risks

- **Never** surface `session_version`, password hashes, or the one-time plaintext in an
  AI-generated response.
- The AuditLogger actor fallback omits `branch_admin` (`AuditLogger.php:17-20`) — a branch-admin
  action would log as `System`/0; don't rely on audit actor for branch-admin attribution without
  passing `$actor` explicitly.
- Assuming students are force-changed or that `session_version` middleware protects every role
  would be wrong (see flows above).

## Future Improvements (future)

- **Partially implemented:** `session_version` middleware is only mounted on the school-admin
  group; extending `v2.session_version` to super-admin/branch-admin/teacher/student groups would
  make the deactivate/reset kill-switch effective for them via middleware.
- **Not implemented / future:** email-based password reset / invite links (all onboarding is
  admin-issued temp passwords shown once).
- **Not implemented / future:** super-admin account self-service (`v2_super_admins` has no
  `session_version`, no created_by, no forced change by default).
- **Not implemented / future:** audit-log viewer UI (`super-admin/audit` stub, `routes/v2.php:133`).
