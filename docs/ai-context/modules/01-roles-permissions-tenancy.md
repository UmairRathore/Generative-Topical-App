# 01 — Roles, Permissions & Tenancy

> Code-grounded reference. Verify against source before acting.

**Siblings:** [02 — School / Branch / Class Structure](./02-school-branch-class-structure.md) ·
[03 — Auth / Onboarding / Credentials](./03-auth-onboarding-credentials.md) ·
[00 — System Overview](../00-system-overview.md)

---

## Purpose

Defines **who can log in**, **what each role is scoped to see**, and **how multi-tenant
isolation is enforced**. The V2 platform is a per-school SaaS: many schools share one database
and one question bank, and every school's people/classes/results must be invisible to every
other school. Isolation is done almost entirely with **Eloquent global scopes on the models**,
not middleware.

Status: **Implemented in code** for all five roles and their scopes.

## Users / Roles

There are exactly **five** V2 roles, each a separate authentication guard + Eloquent model +
database table. Defined in `config/auth.php:44-115`.

| Role | Guard | Provider model | Table |
|------|-------|----------------|-------|
| Platform super admin | `v2_super_admin` | `app/Models/V2/SuperAdmin.php` | `v2_super_admins` |
| School admin | `v2_school_admin` | `app/Models/V2/SchoolAdmin.php` | `v2_school_admins` |
| Branch admin (campus) | `v2_branch_admin` | `app/Models/V2/BranchAdmin.php` | `v2_branch_admins` |
| Teacher | `v2_teacher` | `app/Models/V2/Teacher.php` | `v2_teachers` |
| Student | `v2_student` | `app/Models/V2/Student.php` | `v2_students` |

> **CRITICAL — there is NO parent role.** No guard, model, table, route prefix, or middleware
> for a parent/guardian exists anywhere in the codebase. The canonical multi-guard resolver
> `v2_actor()` (`app/Support/helpers.php:12-30`) enumerates **exactly** these five guards and
> nothing else. Do not assume a parent portal exists — if a task requires one, it must be
> built from scratch.

The role set is also mirrored in `bootstrap/app.php:39-45` (the unauthenticated-redirect map)
and in the exception handler that routes a failed guard to its own login page.

### Legacy V1 role system (separate, do not conflate)

A **legacy** role model exists and is unrelated to the five V2 guards: `app/Models/User.php`
authenticates on the default `web` guard and carries a single `role` enum
(`app/Enums/UserRole.php`: `Student`, `Teacher`, `Admin`, `SuperAdmin` — also **no parent**).
Its `isAdmin()`/`isTeacher()`/`isStudent()` methods (`app/Models/User.php:53-78`) back the
legacy middleware `EnsureUserIsAdmin` / `EnsureUserIsTeacher`
(`app/Http/Middleware/EnsureUserIsAdmin.php`, `EnsureUserIsTeacher.php`) used only in
`routes/web.php`. **All V2 work uses the five guards above; the `User`/`UserRole` system is V1.**
See [00 — System Overview](../00-system-overview.md#legacy-v1-vs-v2) for the split.

## Current Implementation

**Guards / providers**
- `config/auth.php:44-65` — five `session`-driver guards.
- `config/auth.php:95-115` — five `eloquent` providers pointing at the `App\Models\V2\*` models.
- `config/auth.php:16-19` — the framework default guard is still `web` (legacy `User`); V2 routes
  name their guard explicitly (`auth:v2_teacher`, etc.).

**Route application of guards** — `routes/v2.php`, one prefixed group per role:
- Super admin: `super-admin` prefix, `guest:v2_super_admin` / `auth:v2_super_admin` (`:66-136`).
- School admin: `school` prefix, `guest:v2_school_admin` / `auth:v2_school_admin` (`:143-226`).
- Branch admin: `branch` prefix, `guest:v2_branch_admin` / `auth:v2_branch_admin` (`:233-263`).
- Teacher: `teacher` prefix, `guest:v2_teacher` / `auth:v2_teacher` (`:270-320`).
- Student: `student` prefix, `guest:v2_student` / `auth:v2_student` (`:327-379`).

**Tenancy scopes (the core mechanism)** — `booted()` global scopes on the V2 models:
- `app/Models/V2/Student.php:50-67`
- `app/Models/V2/Teacher.php:50-64`
- `app/Models/V2/SchoolClass.php:34-48`
- `app/Models/V2/Branch.php:19-30`
- `app/Models/V2/SchoolAdmin.php:44-52`
- `app/Models/V2/BranchAdmin.php:43-52`
- `app/Models/V2/Grade.php:30-42`
- `app/Models/V2/SchoolSubject.php` (booted scope, `v2_school_admin` only)
- `app/Models/V2/ClassTeacher.php:25-33`
- `app/Models/V2/StudentEnrollment.php:21-35`
- `app/Models/V2/StudentMistake.php:56-67`

**Controller-level ownership checks** (defence in depth on top of scopes) — e.g.
`authorizeStudent()` / `authorizeTeacher()` / `authorizeClass()` abort 403 when
`->school_id !== $this->schoolId()` (`app/Http/Controllers/V2/SchoolAdmin/StudentController.php:235-238`,
`TeacherController.php:186-189`, `ClassController.php:129-132`). School-admin controllers anchor
tenancy via `app/Http/Controllers/V2/SchoolAdmin/BaseController.php` (`admin()`, `school()`, `schoolId()`).

**Cross-guard actor resolution** — `v2_actor()` (`app/Support/helpers.php:12-30`) returns
`['id','role','guard']` for whichever of the five guards is authenticated; used by the shared
secure-image endpoint (`app/Http/Controllers/V2/SecureImageController.php:31-33`).

## Data Model — tenancy keys

Tenancy is carried by two denormalized columns present across the org tables (see
[02 — School / Branch / Class Structure](./02-school-branch-class-structure.md) for full schemas):

| Table | `school_id` | `branch_id` | Isolation basis |
|-------|:-----------:|:-----------:|-----------------|
| `v2_super_admins` | — | — | none (platform-wide) |
| `v2_schools` | — | — | root tenant entity |
| `v2_school_admins` | ✓ | — | school |
| `v2_branch_admins` | ✓ | ✓ | school + branch |
| `v2_teachers` | ✓ | ✓ (nullable) | school (branch optional) |
| `v2_students` | ✓ | ✓ (nullable) | school (branch optional) |
| `v2_branches` | ✓ | — | school |
| `v2_grades` | ✓ | — | school |
| `v2_subjects` | — | — | **platform-wide** (enabled per school via `v2_school_subjects`) |
| `v2_school_subjects` | ✓ | — | school |
| `v2_classes` | ✓ | ✓ (nullable) | school (branch optional) |
| `v2_class_teachers` | ✓ (denorm) | — | school |
| `v2_student_enrollments` | ✓ (denorm) | ✓ (nullable) | school |
| `v2_student_mistakes` | ✓ (denorm) | — | school |
| `v2_audit_logs` / `v2_login_attempts` | — | — | none (security logs) |

## Core Flows — how a scope decides what a row-set contains

Each `booted()` scope inspects the currently-authenticated guard **in a fixed priority order**
and applies the matching `where`. Because scopes fire on *every* query, a school admin literally
cannot `Student::all()` outside their school.

**Two distinct scope shapes exist — this matters:**

1. **Multi-guard scopes** (school admin OR teacher OR student OR branch admin) — used on the
   "people/enrolment/results" tables. Example, `Student::booted()`
   (`app/Models/V2/Student.php:50-67`):
   - `v2_school_admin` → `where('school_id', admin.school_id)`
   - `v2_teacher` → `where('school_id', teacher.school_id)`
   - `v2_student` → `where('school_id', student.school_id)` (a student sees only their own school)
   - `v2_branch_admin` → `where('branch_id', branchAdmin.branch_id)` (**branch admin scopes by
     `branch_id`, not `school_id`**)
   - Same shape: `Teacher`, `SchoolClass`, `StudentEnrollment`.
   - `StudentMistake` (`:56-67`) uses only `v2_school_admin` / `v2_teacher` / `v2_student` (no
     branch-admin arm).

2. **School-admin-only scopes** — `Grade`, `Subject`(none), `SchoolSubject`, `ClassTeacher`
   scope **only** when `v2_school_admin` is the guard (e.g. `Grade::booted()`
   `app/Models/V2/Grade.php:30-42`; `ClassTeacher::booted()` `app/Models/V2/ClassTeacher.php:25-33`).
   **Implication:** when a teacher, branch admin, or student queries these models, the global
   scope adds **no** tenancy filter — those controllers must scope manually (e.g. via a
   `whereHas`/`where('school_id', …)` or by loading through an already-scoped parent). Treat this
   as a known sharp edge, not a bug to "fix" blindly.

**Super admin is unscoped.** `SuperAdmin` has no `booted()` scope
(`app/Models/V2/SuperAdmin.php`), and none of the scopes above match the `v2_super_admin` guard,
so a super admin sees every school's rows. Super-admin controllers therefore filter explicitly by
route parameter (e.g. `schools/{school}/branches/{branch}/…` in `routes/v2.php:99-107`).

## Inputs

- The **authenticated guard** (from the session) — the sole input to every tenancy scope.
- `school_id` / `branch_id` on the logged-in actor's own record.
- Route model bindings (`{student}`, `{class}`, `{exam}`) — re-checked by controller
  `authorize*()` helpers.

## Outputs

- Tenant-filtered Eloquent result sets for all downstream reads.
- 403 aborts on cross-tenant access attempts (controller ownership checks).
- Guard-specific login redirects for unauthenticated requests (`bootstrap/app.php:31-54`).

## Dependencies

- Laravel's multi-guard auth + Eloquent global scopes.
- `app/Models/Concerns/HasHashid.php` (`HasHashid` trait) — obfuscated public IDs on scoped
  models (used in route bindings so sequential integer IDs aren't exposed).
- `app/Support/helpers.php` (`v2_actor()`), autoloaded via `AppServiceProvider::register()`
  (`app/Providers/AppServiceProvider.php:15-18`).

## Security / Access Rules

- **No cross-tenant reads**: enforced at the model layer (global scopes) so a forgotten
  `where` in a controller still can't leak another school's data — *except* on the
  school-admin-only-scoped models (Grade/Subject/SchoolSubject/ClassTeacher) queried by
  non-school-admin guards. Prefer loading those through a scoped parent relationship.
- **Ownership re-checks**: mutating controllers additionally assert `school_id` equality and
  abort 403 (`abort_if($x->school_id !== $this->schoolId(), 403)`).
- **Super admin is intentionally global**; guard super-admin actions carefully — there is no
  scope safety net.
- **Session invalidation** via `session_version` (see
  [03 — Auth / Onboarding / Credentials](./03-auth-onboarding-credentials.md)) lets an admin
  forcibly log a user out (on deactivate / password reset).

## Existing AI-Relevant Context

- `v2_actor()` gives any AI-facing endpoint a uniform `{id, role, guard}` identity without
  re-implementing guard priority.
- The tenancy scopes mean an AI feature querying V2 models inside an authenticated request is
  **automatically** school-isolated — as long as it uses the scoped models and does not bypass
  them with raw queries or `withoutGlobalScope`.

## AI Opportunities

- **Recommended for AI:** a role-aware assistant surface can rely on `v2_actor()` for identity
  and on the global scopes for data isolation, so the same code path is safe for every role.
- **Recommended for AI:** cohort/misconception analytics for admins/teachers can read the already
  school-scoped `v2_student_mistakes` (see [../modules/12-mistake-bank.md](./12-mistake-bank.md)).

## AI Risks

- **Scope bypass**: any AI-generated query using `DB::table(...)`, `withoutGlobalScope`, or the
  unscoped super-admin path loses tenant isolation. Never do this in a per-school feature.
- **School-admin-only scopes**: an AI feature reading `Grade`/`Subject`/`SchoolSubject`/
  `ClassTeacher` for a teacher/branch/student guard gets **unfiltered** rows — it must scope
  manually.
- **Assuming a parent role** would be a hallucination — none exists.

## Future Improvements (future)

- **Not implemented / future:** a permissions/ability layer (Gates/Policies). Access today is
  guard membership + hardcoded `school_id` checks; there is no fine-grained per-action policy
  object.
- **Partially implemented:** branch-level scoping — present for branch admins (`branch_id`) and
  optional `branch_id` on teachers/students/classes, but many people-tables scope branch admins
  by `branch_id` while others (e.g. `StudentMistake`) omit the branch-admin arm entirely.
- **Recommended for AI:** normalizing the two scope shapes (multi-guard vs school-admin-only)
  into a shared trait would remove the sharp edge documented above.
