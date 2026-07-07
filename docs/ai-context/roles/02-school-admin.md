# 02 — School Admin

> Code-grounded reference. Verify against source before acting.

**Role siblings:** [01 — Super Admin](./01-super-admin.md) · [03 — Branch Admin](./03-branch-admin.md) · [04 — Teacher](./04-teacher.md) · [05 — Student](./05-student.md) · [06 — Parent (not implemented)](./06-parent-not-implemented.md)

**Related modules:** [01 — Roles, Permissions & Tenancy](../modules/01-roles-permissions-tenancy.md) · [10 — Analytics & Statistics](../modules/10-analytics-and-statistics.md) · [16 — Notifications](../modules/16-notifications.md)

---

## Identity

| Property | Value | Source |
|----------|-------|--------|
| Guard | `v2_school_admin` (session) | `config/auth.php` |
| Provider / model | `v2_school_admins` → `App\Models\V2\SchoolAdmin` | `config/auth.php` |
| Table | `v2_school_admins` | `app/Models/V2/SchoolAdmin.php:14` |
| Tenancy scope | **`school_id`** — all branches within one school | `SchoolAdmin::booted()` global scope, `app/Models/V2/SchoolAdmin.php:44-52` |
| Route prefix | `/v2/school/...`, name prefix `v2.school.` | `routes/v2.php:143` |

**Tenancy is enforced two ways** and both must hold:
1. **Global scope** — `SchoolAdmin::booted()` adds `where('school_id', <admin>->school_id)`; the same pattern on `BranchAdmin`, `Teacher`, `Student`, `StudentMistake`, `Exam` etc. keys off the active portal guard, so any query a school admin runs is auto-filtered to their school.
2. **Explicit re-checks** in controllers — `authorizeGrade()` / `authorizeTeacher()` / `authorizeStudent()` / `authorizeClass()` `abort(403)` unless the record's `school_id === $this->schoolId()`, and cross-record writes add `abort_if(grade.school_id !== schoolId())`-style guards (belt-and-braces because route-model-bound records could otherwise be tampered).

Shared helpers live in `SchoolAdmin\BaseController`: `admin()`, `school()`, `schoolId()`.

Auth mechanics: login throttled (`throttle:v2-login`), `LoginAttempt` logged, `must_change_password` first-login gate (`v2.must_change_password` middleware), **plus `v2.session_version:v2_school_admin` middleware** — deactivating or password-resetting a user increments `session_version` to force logout everywhere (`routes/v2.php:155-158`). School admin is the only admin role that also carries the session-version check on its own group.

## Screens they use

Blade views under `resources/views/v2/school_admin/`:

| Area | Views |
|------|-------|
| Dashboard | `dashboard/index.blade.php` |
| Analytics | `analytics/{index,grades,grade,subjects,subject,topics,teacher,class,student,paper}.blade.php` |
| Grades | `grades/{index,create,edit}.blade.php` |
| Subjects | `subjects/index.blade.php` (assign/remove act inline) |
| Classes | `classes/{index,create,edit,show}.blade.php` |
| Teachers | `teachers/{index,create,bulk_create,edit,show,credentials}.blade.php` |
| Students | `students/{index,create,bulk_create,edit,show,credentials}.blade.php` |
| Flagged questions | **shared** `resources/views/v2/admin/flagged_questions.blade.php` (`showBranch=true`, `scopeLabel='school'`) |
| Auth | `auth/{login,change_password}.blade.php` |

The `credentials` views display the **plaintext temp password once** to the admin after create / bulk-create / reset — the only time it is shown.

## Capabilities (controllers + actions)

Controllers in `app/Http/Controllers/V2/SchoolAdmin/` (all extend `BaseController`):

- **DashboardController@index** — teacher/student/class/grade counts + audit log for this school.
- **AnalyticsController** — read-only drill-down: `index`, `grades`, `grade`, `subjects`, `subject`, `topics`, `teacher`, `classDetail`, `student`, `studentPaper`. Stats come from `ExamService` (this controller does not compute them). Every leaf method re-asserts `school_id`. See [module 10](../modules/10-analytics-and-statistics.md).
- **GradeController** — `index/create/store/edit/update/toggle` (manage the school's grade list; `toggle` flips `is_active`).
- **SubjectController** — `index/assign/remove` (assign a global `Subject` to a grade via `SchoolSubject`; grade must belong to the school).
- **ClassController** — `index/create/store/show/edit/update/assignTeacher/removeTeacher` (M2M teacher assignment via the `ClassTeacher` pivot / `v2_class_teachers`).
- **TeacherController** — full lifecycle: `index/create/store/bulkCreate/bulkStore/show/edit/update/toggle/resetPassword`. **bulkStore** creates up to 50 teachers in a transaction; `store`/`bulkStore` check the school's teacher-license limit. Credentials via `TempPasswordGenerator` (see below).
- **StudentController** — `index/create/store/bulkStore/show/edit/update/toggle/resetPassword/enroll/unenroll`. **bulkStore** up to 100 students; enrollment is M2M via `StudentEnrollment` / `v2_student_enrollments`; license-limited.
- **FlaggedQuestionController@index** — **read-only** reported-questions audit via the `ReviewsEscalations` trait: `reportedQuestions($school_id, null)`. School admins are **not in the approval chain** (Student → Teacher → Support Team); this is observability only, with no actions.
- **AuthController** — login/logout/change-password.

### Credential generation (`TempPasswordGenerator`, `app/Services/V2/TempPasswordGenerator.php`)

- `generate(int $length = 12)`: random string from a 52-char visually-unambiguous alphabet (omits `0 O l 1 I`) via `random_int`.
- `generateBatch($count, $length = 12)`: unique batch for bulk create.
- New users are stored **bcrypted** with `must_change_password = true`; the plaintext is surfaced once on the `credentials` view and never stored in the clear. Deactivation / password reset **increments `session_version`** to force logout. See [module 01 — Roles, Permissions & Tenancy](../modules/01-roles-permissions-tenancy.md).

## Data visible vs editable

- **Visible (within `school_id`):** all branches, grades, subjects, classes, teachers, students, exams, attempts, analytics, and the reported-questions audit. **Never** another school's data — the global scope forbids it.
- **Editable:** grades, subject-to-grade assignments, classes + teacher assignments, teachers (CRUD + reset + toggle), students (CRUD + reset + toggle + enroll/unenroll). **Not editable:** the question bank (super-admin only), exams (teacher-owned), quality-review outcomes (super-admin), student progress reports (branch-admin — see [03](./03-branch-admin.md)).

## Core journeys

1. **Onboard a cohort** → Grades (`grades.create`) → Subjects (`subjects.assign`) → Classes (`classes.create`, `assign-teacher`) → bulk-create teachers (`teachers.bulk_create` → `bulk_store` → `credentials`) → bulk-create students (`students.bulk_create` → `bulk_store`) → enroll students (`students.enroll`).
2. **Reset a locked-out user** → teacher/student `resetPassword` → new temp password shown once → `session_version` bump logs them out.
3. **Investigate performance** → Analytics → grade/subject/teacher/class → student → `studentPaper`.
4. **Audit a reported question** → `flagged_questions.index` (read-only, all branches).

## Notifications received

No school-admin notification bell exists in the routes/views (only teacher and student have `NotificationBell`/`NotificationIndex`). School admins observe the reported-question audit but receive no in-app `v2_notifications`. See [module 16 — Notifications](../modules/16-notifications.md); the plumbing of record is `app/Services/V2/NotificationService.php` + `app/Models/V2/Notification.php`.

## Role-specific AI opportunities

- **Bulk-import assistant** — parse a messy roster CSV/spreadsheet into validated teacher/student create payloads (name/email/roll/class), flagging duplicates and license-limit overruns before `bulkStore`. *Recommended for AI.*
- **School-health narrative** — summarize the analytics drill-down into a plain-language brief for the school admin (mirroring the branch-admin student report but at school scope). *Future.*
- **Onboarding wizard** — infer grades→subjects→classes structure from a syllabus description. *Future.*

## AI risks

- **Tenancy leakage is the primary risk.** A school admin must **never** see another school's data. Any AI feature must run *inside* the authenticated `v2_school_admin` request so the `school_id` global scope applies, or must pass an explicit `school_id` filter and re-verify it. Do not reuse super-admin (unscoped) queries here.
- **Do not leak credentials.** Temp passwords are shown once and are bcrypted at rest; an AI feature must not log, echo, or transmit plaintext passwords.
- **Respect the license limits** enforced in `store`/`bulkStore` — an AI bulk tool must not bypass them.
- **Read-only means read-only** — the flagged-question audit has no actions; do not add write paths for admins into the quality-review chain.

## Future Improvements

- School-admin **notification inbox** — not implemented.
- **AI roster import** — not implemented.
- School-scoped progress-report narratives — not implemented (branch-admin has the student-level one).
