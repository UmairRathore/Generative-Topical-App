# 00 — System Overview

> Code-grounded reference. Verify against source before acting.

Navigational entry point for the V2 platform (TopicalEd / cambpast-app). Read this first, then
jump to the module docs below. Every claim here is grounded in source; verify before acting.

---

## Stack

- **Backend**: Laravel 11 (PHP), `bootstrap/app.php` app configuration (no `Kernel.php`).
- **Two front-end presentation layers** (see below): Livewire 3 + Blade role portals, and an
  Inertia + React "Learning Studio" with standalone React **widget islands**.
- **Auth**: five session guards (V2) defined in `config/auth.php`; a separate legacy `web` guard.
- **DB**: MySQL (dev runs on `php8.4` for `pdo_mysql`; the bare `php` is 8.5 without it).
- **Build**: Vite (`vite.config.js`) with four entries — legacy CSS/JS, the widget islands
  runtime, and the Learning Studio app.

## V2-only scope (read this)

All current development is **V2**, routed from `routes/v2.php` under the `/v2` prefix (mounted in
`bootstrap/app.php:12-15`). V2 has its own five guard models (`app/Models/V2/*`), its own tables
(all `v2_*`), and its own views (`resources/views/v2/*`). **Do not modify V1.**

### Legacy V1 vs V2

A **legacy V1** app still exists and is still wired:
- `routes/web.php` mounts legacy routes on the default `web` guard using the single
  `app/Models/User.php` model + `app/Enums/UserRole.php` role enum
  (`Student|Teacher|Admin|SuperAdmin` — **no parent**).
- It uses the legacy middleware `EnsureUserIsAdmin` / `EnsureUserIsTeacher`
  (`app/Http/Middleware/EnsureUserIsAdmin.php`, `EnsureUserIsTeacher.php`), which operate on
  `$request->user()` (the `web`/`User` guard) and call `User::isAdmin()`/`isTeacher()`.
- Its Blade lives at the **top level** of `resources/views/` (`admin/`, `teacher/`, `student/`,
  `welcome.blade.php`, `dashboard.blade.php`) and its Livewire components under
  `app/Livewire/{Admin,Student,Teacher,Actions}` are referenced by `routes/web.php`.

> **Unclear from codebase which legacy V1 pieces are live in production.** The V1 routes/views/
> Livewire are still registered (`routes/web.php` is active), and some `app/Livewire/*` classes are
> shared/referenced by both worlds, so V1 is not obviously dead code. When touching anything under
> the top-level `resources/views/{admin,teacher,student}`, `app/Livewire/*`, `routes/web.php`,
> `routes/auth.php`, or `app/Models/User.php`, treat it as **legacy V1** and confirm scope before
> editing. Everything under `V2\`, `v2/`, `v2_*`, and `routes/v2.php` is V2.

## No parent role — everywhere

There is **no parent/guardian role** in either system. V2 has exactly five roles
(`v2_super_admin`, `v2_school_admin`, `v2_branch_admin`, `v2_teacher`, `v2_student` —
`config/auth.php:44-115`, mirrored in `v2_actor()` at `app/Support/helpers.php:12-30`). V1 has
exactly four (`app/Enums/UserRole.php`). If a task asks for parent features, they do not exist and
must be built. See [modules/01-roles-permissions-tenancy.md](./modules/01-roles-permissions-tenancy.md).

## The two presentation layers

### Layer 1 — Livewire + Blade role portals (server-rendered)
The admin/teacher/student **portals** (login, dashboards, management CRUD, exams, analytics).
- Views: `resources/views/v2/{super_admin, school_admin, branch_admin, teacher, student, layouts,
  partials}/…`.
- Controllers: `app/Http/Controllers/V2/{SuperAdmin, SchoolAdmin, BranchAdmin, Teacher, Student}/…`.
- Livewire components: `app/Livewire/{Admin, Student, Teacher}/…` (e.g. `Student/NotificationBell`,
  `Teacher/TestGenerator`). Note some of these classes are shared with / originate from V1 —
  confirm before assuming a component is V2-only.
- Routed from `routes/v2.php`; each role behind its own guard.

### Layer 2 — Inertia / React "Learning Studio" (SPA) + widget islands
The interactive learning surface (worked solutions, notes, asset review) plus embeddable
science/maths widgets.
- Root Inertia view: `resources/views/learn.blade.php` (loads `resources/js/learn/app.jsx`,
  includes `v2.partials.theme`).
- Inertia middleware: `app/Http/Middleware/HandleInertiaRequests.php` — `$rootView = 'learn'`,
  `share()` passes only `appName` today (**no auth/user prop is shared** — the Studio is currently
  unauthenticated).
- React pages: `resources/js/learn/Pages/{Home, Notes, Solution, AssetReview}.jsx`.
- Widget islands: `resources/js/widgets/` — `index.jsx` bootstrap, `registry.js`
  (type → lazy component map, ~30+ widgets like `calculator`, `ray_diagram`, `circuit_network`),
  `WidgetHost.jsx`, mounted on `[data-widget="<type>"]` via a global `CambWidgets` API. See
  [modules/14-interactive-widgets.md](./modules/14-interactive-widgets.md).
- Routes: `routes/v2.php:401-438` under `/v2/learn`. Comment states **auth is added later** — the
  Mistake Bank (Livewire) hands off into the Studio via a gateway button; the `solution-demo`
  route is dev-only sample data. So Layer 2 is **partially wired** to the authenticated portal.

## Core entity glossary

| Term | Table | Model | Note |
|------|-------|-------|------|
| Super admin | `v2_super_admins` | `V2\SuperAdmin` | platform staff; unscoped; no `session_version` |
| School admin | `v2_school_admins` | `V2\SchoolAdmin` | tenant admin; scoped by `school_id` |
| Branch admin | `v2_branch_admins` | `V2\BranchAdmin` | campus admin; scoped by `branch_id` |
| Teacher | `v2_teachers` | `V2\Teacher` | scoped by `school_id`; optional `branch_id` |
| Student | `v2_students` | `V2\Student` | scoped by `school_id`; optional `branch_id` |
| School | `v2_schools` | `V2\School` | root tenant; licence caps `max_teachers`/`max_students` |
| Branch | `v2_branches` | `V2\Branch` | campus under a school |
| Grade | `v2_grades` | `V2\Grade` | per-school grade level |
| Subject | `v2_subjects` | `V2\Subject` | **platform-wide**; enabled per school via `v2_school_subjects` |
| Class | `v2_classes` | `V2\SchoolClass` | grade + subject + section (note model≠table name) |
| Enrollment | `v2_student_enrollments` | `V2\StudentEnrollment` | student↔class M2M (`status`) |
| Class-teacher | `v2_class_teachers` | `V2\ClassTeacher` | teacher↔class M2M (`is_primary`) |
| Mistake | `v2_student_mistakes` | `V2\StudentMistake` | one row per (student, question) |
| Login attempt | `v2_login_attempts` | `V2\LoginAttempt` | every login, success + fail |
| Audit log | `v2_audit_logs` | `V2\AuditLog` | management-action trail |

The full V2 model set (~41 classes) also includes the question bank and exam engine
(`Question`, `QuestionOption`, `QuestionImage`, `QuestionVersion`, `Paper`, `Exam`,
`ExamQuestion`, `ExamAttempt`, `ExamAnswer`), quality review (`QuestionFlag`, `QualityReview`,
`QualityPropagation*`), learning (`QuestionLearningAsset`, `StudentMistakeEvent`), notes
(`Notes*`), and platform (`Report`, `Notification`, `Topic`, `Subtopic`).

## Tenancy in one paragraph

Multi-tenant isolation is enforced by **Eloquent global scopes** in the models' `booted()`
methods (not middleware). Each scope reads the authenticated guard and adds a `where school_id`
(or `branch_id`) filter; super admin is unscoped. Two scope shapes exist (multi-guard vs
school-admin-only), which is a real sharp edge. Full detail:
[modules/01-roles-permissions-tenancy.md](./modules/01-roles-permissions-tenancy.md).

## Module map

Role / structure / auth (this series):
- [modules/01-roles-permissions-tenancy.md](./modules/01-roles-permissions-tenancy.md) — the five
  roles, guards, and global-scope tenancy; the no-parent fact.
- [modules/02-school-branch-class-structure.md](./modules/02-school-branch-class-structure.md) —
  school → branch → grade/subject → class → enrollment/class-teacher schemas & flows.
- [modules/03-auth-onboarding-credentials.md](./modules/03-auth-onboarding-credentials.md) —
  per-role login, bulk credentialing, temp passwords, forced-change, session invalidation, audit.

Question bank & exams (existing docs):
- [modules/04-question-bank.md](./modules/04-question-bank.md) — questions/options/images.
- [modules/05-question-versioning-and-quality-review.md](./modules/05-question-versioning-and-quality-review.md)
  — versioning + quality-review queue.
- [modules/07-quality-propagation-engine.md](./modules/07-quality-propagation-engine.md) — material-error propagation.
- [modules/08-exam-builder.md](./modules/08-exam-builder.md) · [modules/09-exam-attempts-checking-results.md](./modules/09-exam-attempts-checking-results.md).
- [modules/18-import-and-data-pipeline.md](./modules/18-import-and-data-pipeline.md) — corpus import.

Learning layer (existing docs):
- [modules/12-mistake-bank.md](./modules/12-mistake-bank.md) — "My Mistakes" revision spine.
- [modules/13-learning-hub-worked-solutions-assets.md](./modules/13-learning-hub-worked-solutions-assets.md)
  — per-question learning-asset pool.
- [modules/14-interactive-widgets.md](./modules/14-interactive-widgets.md) — React widget islands.
- [modules/15-notes-module.md](./modules/15-notes-module.md) — Notion-style notes.

Platform / cross-cutting (existing docs):
- [modules/10-analytics-and-statistics.md](./modules/10-analytics-and-statistics.md) · [modules/11-reports-current-system.md](./modules/11-reports-current-system.md).
- [modules/16-notifications.md](./modules/16-notifications.md) · [modules/17-secure-images.md](./modules/17-secure-images.md) · [modules/19-audit-logging.md](./modules/19-audit-logging.md).

Other AI-context material:
- [NEXT_SESSION_AI_TUTOR_PROMPT.md](./NEXT_SESSION_AI_TUTOR_PROMPT.md) — data-grounded AI-tutor plan.

Older top-level design docs (broader, pre-this-series; cross-check against source before trusting):
`../ARCHITECTURE.md`, `../DATABASE_SCHEMA_DRAFT.md`, `../V2_MODULES.md`,
`../modules/{01-auth-identity, 02-roles-access-control, 03-org-hierarchy, 04-user-management}.md`,
`../learning-hub.md`, `../quality-review-system.md`.

## Where the key wiring lives

- Route → guard mapping and middleware: `routes/v2.php`; legacy `routes/web.php` + `routes/auth.php`.
- Guards/providers: `config/auth.php`.
- Middleware aliases + Inertia + unauth-redirect map: `bootstrap/app.php`.
- Rate limiters (`v2-login`, `secure-image`): `app/Providers/AppServiceProvider.php`.
- Cross-guard identity helper: `v2_actor()` in `app/Support/helpers.php`.
- Shared secure-image endpoint (any V2 guard): `app/Http/Controllers/V2/SecureImageController.php`.
