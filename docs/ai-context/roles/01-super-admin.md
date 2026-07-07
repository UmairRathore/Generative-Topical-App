# 01 — Super Admin (Platform Operator)

> Code-grounded reference. Verify against source before acting.

**Role siblings:** [02 — School Admin](./02-school-admin.md) · [03 — Branch Admin](./03-branch-admin.md) · [04 — Teacher](./04-teacher.md) · [05 — Student](./05-student.md) · [06 — Parent (not implemented)](./06-parent-not-implemented.md)

**Related modules:** [04 — Question Bank](../modules/04-question-bank.md) · [08 — Exam Builder](../modules/08-exam-builder.md) · [10 — Analytics & Statistics](../modules/10-analytics-and-statistics.md) · [13 — Learning Hub / Assets](../modules/13-learning-hub-worked-solutions-assets.md) · [16 — Notifications](../modules/16-notifications.md) · [`quality-review-system.md`](../../quality-review-system.md)

---

## Identity

| Property | Value | Source |
|----------|-------|--------|
| Guard | `v2_super_admin` (session driver) | `config/auth.php` (guards) |
| Provider / model | `v2_super_admins` → `App\Models\V2\SuperAdmin` | `config/auth.php` (providers) |
| Table | `v2_super_admins` | `app/Models/V2/SuperAdmin.php:12` |
| Tenancy scope | **NONE — platform-wide / unscoped** | `SuperAdmin` has **no** `booted()` global scope (contrast `SchoolAdmin`/`Teacher`/`Student`) |
| Route prefix | `/v2/super-admin/...`, name prefix `v2.super_admin.` | `routes/v2.php:66` |

The Super Admin is the platform operator (Anthropic/TopicalEd staff, not a customer). Unlike every other V2 role, `SuperAdmin` defines **no global scope** — it owns no `school_id`/`branch_id` column and reads across all tenants. The other role models (`SchoolAdmin`, `BranchAdmin`, `Teacher`, `Student`) each add a `school`/`branch` global scope in `booted()` that keys off the **currently-authenticated portal guard**; because the super-admin guard is not one of those guards, those scopes are inert while a super admin is logged in, so super-admin controllers see raw, cross-tenant data. This is the one role that is *supposed* to see every tenant.

Auth mechanics (shared across all V2 admin portals):
- Login throttled `throttle:v2-login`; `LoginAttempt` rows recorded on success/failure (`AuthController::login`).
- `must_change_password` boolean forces a password change on first login via the `v2.must_change_password:v2_super_admin,v2.super_admin.change_password` middleware wrapping the authenticated route group (`routes/v2.php:78`).
- No `session_version` invalidation on this guard (school/branch/teacher have it; super admin does not).

## Screens they use

Blade views under `resources/views/v2/super_admin/`:

| Screen | View | Route name |
|--------|------|------------|
| Dashboard | `dashboard/index.blade.php` | `v2.super_admin.dashboard` |
| Platform stats | `stats/index.blade.php` | `v2.super_admin.stats` |
| Schools list / detail | `schools/index.blade.php`, `schools/show.blade.php` | `schools.index`, `schools.show` |
| Schools create / edit | `schools/create.blade.php`, `schools/edit.blade.php` | **stubs — not built** (`Route::view` placeholders, `routes/v2.php:93,95`) |
| Branch detail | `branches/show.blade.php` | `schools.branches.show` |
| Grade (platform + drill) | `grades/platform.blade.php`, `grades/show.blade.php` | `grades.index`, `…grades.show` |
| Subject (platform + drill) | `subjects/platform.blade.php`, `subjects/show.blade.php` | `subjects.index`, `subjects.show` |
| Topic rollup | `topics/show.blade.php` | `topics.index` |
| Teacher / Class / Student drill | `teachers/show.blade.php`, `classes/show.blade.php`, `students/show.blade.php`, `students/paper.blade.php` | `…teachers.show` … `…student_paper` |
| Question bank list / form / versions | `question_bank/index.blade.php`, `question_bank/form.blade.php`, `question_bank/versions.blade.php`, `question_bank/_uploader.blade.php` | `question_bank.*` |
| Learning-asset review | Inertia `AssetReview` page (`resources/js/learn/Pages/AssetReview.jsx`) | `question_bank.learning_assets` |
| Quality-review queue | `question_flags/index.blade.php` | `question_flags.index` |
| Propagation preview | `question_propagation/preview.blade.php` | `question_flags.propagate` |
| Audit viewer | `audit/index.blade.php` | **stub — not built** (`routes/v2.php:133`) |

The drill-down hierarchy is deliberately routed **through a branch**: `schools/{school}/branches/{branch}/…` so grade/subject/teacher/class/student leaves always resolve inside a school→branch path (`routes/v2.php:99-107`).

## Capabilities (controllers + actions)

Controllers in `app/Http/Controllers/V2/SuperAdmin/`. All are platform-wide; detail routes enforce **hierarchy integrity** with `abort_unless($branch->school_id === $school->id)`-style guards (not tenancy — the super admin may see all, but the URL path must be internally consistent).

- **DashboardController@index** — platform rollup: schools/teachers/students/exams/submissions counts, per-school performance, top grades/subjects/topics across all schools, recent `AuditLog`. → `dashboard/index`.
- **StatsController@index** — deeper platform analytics: active schools/branches/teachers/students/questions/exams/submissions/reports, MRR + 12-month MRR trend, growth, school engagement, 30-day daily activity, top topics, hardest questions. → `stats/index`.
- **SchoolController@index / @show** — all schools with counts + performance + MRR; per-school detail via `ExamService`. School is the **root tenant table** (no scope). *School create/edit are stubs.*
- **BranchController@show** — branch detail within a school (validates `branch->school_id === school->id`).
- **GradeController@platform / @show** — platform grade rollup (grouped by grade name across schools) + per-grade drill.
- **SubjectController@platform / @show / @showPlatform / @toggle** — subject catalog (global, no `school_id`); `toggle` flips `is_active` platform-wide and writes an `AuditLog` row.
- **TopicController@platform** — answer-weighted per-topic accuracy grouped by subject.
- **TeacherController@show / ClassController@show / StudentController@show / @paper** — leaf drill-downs; `@paper` renders one student's graded exam paper.
- **QuestionBankController** — **full CRUD on the global question pool** (`index`, `create`, `store`, `edit`, `update`, `destroy` (soft-delete), `restore`, `setStatus`, `versions`). Every save records an **immutable `QuestionVersion` snapshot** (`recordVersion()`); active questions cannot be deleted (must be demoted first); options/images synced; new questions attach to a per-subject "Custom" `Paper` for the FK. This is the only role with question-bank write access. See [module 04](../modules/04-question-bank.md).
- **QuestionAssetReviewController** — review + approval of AI learning assets (`review`, `updateStatus`, `update`, `approveAll`). Generation is **external** (Claude Code / offline pipeline); this UI only approves/edits/rejects. See [module 13](../modules/13-learning-hub-worked-solutions-assets.md).
- **QuestionFlagController** — the **Quality Review queue** (`index`, `markCorrect`). Each flagged question has exactly one open `QualityReview`; teacher/student `QuestionFlag` rows attach to it.
- **QuestionPropagationController** — Phase-3 material-error propagation (`preview`, `confirm`): version-targeted, computes blast radius, dispatches an idempotent `PropagateMaterialCorrection` job.
- **AuthController** — login/logout/change-password.

### Quality-review workflow (the super-admin-only chain)

The escalation chain is **Student → Teacher → Support Team (super admin)**; admins (school/branch) are observers only. Super-admin outcomes on a `QualityReview`:

1. **Correct** — `markCorrect()`: question already matches the Cambridge source. Sets `outcome=correct`, `status=decided`, restores the question to `active` if it was `under_review`, closes all open/escalated flags, notifies the reporting teacher(s) via `NotificationService::notifyReviewDecision`. No new version, no propagation.
2. **Cosmetic** — via the "Correct Question" link into the question-bank editor (`?quality_review_id=…`); `finalizeReview()` on save records `outcome=cosmetic`, closes the review, records a new version. No propagation.
3. **Material** — same editor flow, `outcome=material`, marks `propagation_status=propagation_pending`. Then **QuestionPropagationController** previews the blast radius (schools/branches/teachers/exams/attempts affected by prior versions) and `confirm()` dispatches the propagation job that voids historical pivots keyed by `question_version_id`. The corrected version can never be selected as a propagation target (safeguard).

See [`quality-review-system.md`](../../quality-review-system.md) for the full state machine.

## Data visible vs editable

See [module 01 — Roles, Permissions & Tenancy](../modules/01-roles-permissions-tenancy.md) for the guard/scope matrix across all roles.

- **Visible:** everything — all schools, branches, grades, subjects, topics, teachers, classes, students, exams, attempts, question bank, versions, flags, reviews, audit logs, MRR/revenue. No tenancy filter.
- **Editable:** the **global question bank** (CRUD + status + versions), **learning-asset approval status/content**, **subject `is_active` toggle**, **quality-review outcomes + propagation**. Not editable here: schools/branches/grades/teachers/students CRUD (those are school-admin functions; super-admin school create/edit are **stubs**).

## Core journeys

1. **Triage a reported question** → `question_flags.index` → open review → decide *Correct* (`markCorrect`) OR click *Correct Question* → edit in bank → save with outcome *Cosmetic*/*Material* → if Material, `question_flags.propagate` preview → confirm → propagation job runs.
2. **Approve AI learning assets** → question bank → *Learning assets* → Inertia `AssetReview` → per-asset approve/edit/reject or `approveAll`.
3. **Add/fix a bank question** → `question_bank.create`/`edit` → save → immutable version recorded.
4. **Platform oversight** → dashboard / stats → drill school → branch → grade/subject/teacher/class → student → paper.

## Notifications received

Super admins are the **sender/resolver** side, not primarily a recipient: `NotificationService` (`app/Services/V2/NotificationService.php`) pushes teacher/student notifications on their decisions (`notifyReviewDecision`, `notifyTeachersOfPropagation`). There is **no super-admin notification bell** in the routes/views (contrast teacher/student, who each have `NotificationBell`/`NotificationIndex` Livewire components). See [module 16 — Notifications](../modules/16-notifications.md); the code of record is `NotificationService` + the `Notification` model (`app/Models/V2/Notification.php`, table `v2_notifications`).

## Role-specific AI opportunities

- **Cohort misconception analytics** — the super admin sees `selected_option` distributions across *all* students on a question; a distractor-taxonomy classifier could surface systematic misconceptions platform-wide. *Recommended for AI / future.*
- **Assisted quality triage** — draft a correct/cosmetic/material recommendation for a flagged question from the flag reasons + question + Cambridge source, for the human to confirm. *Recommended for AI.*
- **Learning-asset generation** already lives off-platform (Claude Code) and only its *approval* is in-app — keep the human-approval gate. See [module 13](../modules/13-learning-hub-worked-solutions-assets.md).
- **Anomaly detection** in platform stats (engagement drops, suspicious score distributions). *Future.*

## AI risks

- **Tenancy leakage does not apply the same way here** — the super admin is *meant* to be cross-tenant. The risk is the inverse: any AI feature written for the super admin **must not be reused verbatim by a scoped role**, because it deliberately omits the `school_id`/`branch_id` filters. Never lift a super-admin query into a teacher/student/school-admin context.
- **Never let an AI silently change question data.** Question edits must go through `QuestionBankController` so an immutable `QuestionVersion` is recorded; propagation must go through the preview→confirm→idempotent-job path. Bypassing these breaks the audit trail and the frozen-version-per-exam guarantee.
- **Human approval must remain the gate** for learning assets and quality outcomes; AI drafts, humans decide.

## Future Improvements

- School **create/edit** screens (`schools/create`, `schools/edit`) — **stubs / not implemented**.
- **Audit viewer** (`audit/index`) — **stub / not implemented** (data exists in `AuditLog`).
- Super-admin **notification inbox** — not implemented (only teachers/students have one).
- Cohort-level misconception analytics — *future* (see `docs/ai-context/NEXT_SESSION_AI_TUTOR_PROMPT.md` and the roadmap memory note).
