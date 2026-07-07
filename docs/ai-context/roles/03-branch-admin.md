# 03 — Branch Admin (Campus)

> Code-grounded reference. Verify against source before acting.

**Role siblings:** [01 — Super Admin](./01-super-admin.md) · [02 — School Admin](./02-school-admin.md) · [04 — Teacher](./04-teacher.md) · [05 — Student](./05-student.md) · [06 — Parent (not implemented)](./06-parent-not-implemented.md)

**Related modules:** [10 — Analytics & Statistics](../modules/10-analytics-and-statistics.md) · [11 — Reports (current system)](../modules/11-reports-current-system.md) — the AI touchpoint · [`NEXT_SESSION_AI_TUTOR_PROMPT`](../NEXT_SESSION_AI_TUTOR_PROMPT.md)

---

## Identity

| Property | Value | Source |
|----------|-------|--------|
| Guard | `v2_branch_admin` (session) | `config/auth.php` |
| Provider / model | `v2_branch_admins` → `App\Models\V2\BranchAdmin` | `config/auth.php` |
| Table | `v2_branch_admins` | `app/Models/V2/BranchAdmin.php:14` |
| Tenancy scope | **`branch_id`** — a single campus within a school | Guard-keyed global scopes on `Teacher`/`Student`/`Exam` etc. filter by `branch_id` when `v2_branch_admin` is active (`app/Models/V2/Teacher.php:60-62`, `Student.php:63-65`) |
| Route prefix | `/v2/branch/...`, name prefix `v2.branch.` | `routes/v2.php:233` |

The branch admin is the narrowest admin: one **campus**. `BranchAdmin` itself carries a `school`-keyed global scope (so a *school admin* sees only their school's branch admins), but when a branch admin is logged in the **data models** (`Teacher`, `Student`, `Exam`) filter by `branch_id`. Because `Exam` has no branch column of its own in some paths, `AnalyticsController` adds **explicit manual `abort_unless(... === $this->branchId())`** checks on route-model-bound records (e.g. `studentPaper` re-verifies `student.branch_id`, `exam.school_id`, and the class's `branch_id`).

Shared helpers in `BranchAdmin\BaseController`: `admin()`, `branch()`, `branchId()`, `schoolId()`.

Auth mechanics: throttled login, `LoginAttempt` logging, `must_change_password` first-login gate (`v2.must_change_password:v2_branch_admin`). Note the branch dashboard route name is `v2.branch.index` (not `.dashboard`).

## Screens they use

Blade views under `resources/views/v2/branch_admin/`:

| Area | Views | Notes |
|------|-------|-------|
| Analytics / dashboard | `analytics/{index,grades,grade,subjects,subject,topics,teacher,class,student,paper}.blade.php` | Same drill-down shape as school admin, scoped to one branch |
| Report PDF | `report/pdf.blade.php` | dompdf template (A4, DejaVu Sans) rendered on demand |
| Flagged questions | **shared** `resources/views/v2/admin/flagged_questions.blade.php` (`showBranch=false`, `scopeLabel='branch'`) | Read-only audit |
| Auth | `auth/{login,change_password}.blade.php` | |

The **student progress report generate form + saved-reports table** are hosted on the branch-admin `analytics/student.blade.php` page (that view belongs to Stats & Analytics but surfaces this role's report actions).

## Capabilities (controllers + actions)

Controllers in `app/Http/Controllers/V2/BranchAdmin/`:

- **AnalyticsController** — branch-scoped read-only drill-down: `index` (dashboard), `grades`, `grade`, `subjects`, `subject`, `topics`, `teacher`, `classDetail`, `student`, `studentPaper`. Stats via `ExamService`. See [module 10](../modules/10-analytics-and-statistics.md).
- **ReportController** — the **distinguishing capability**: generate + render **per-student progress reports**.
  - `generate(Student $student, …, ReportService $reports)` — `abort_unless(student.branch_id === branchId())`; resolves a duration window (presets `week … 10months`, or a custom from/to range); calls `ReportService::build(...)`; persists a `Report` row (JSON `payload` + denormalized `overall_avg`, `tests_count`, `source`, `period_*`, `range_*`). **The PDF is never stored** — only the JSON.
  - `pdf(Report $report)` — `abort_unless(report.branch_id === branchId())`; re-hydrates the stored JSON and streams a dompdf A4 download (`report-{student}-{date}.pdf`). Nothing cached.
- **FlaggedQuestionController@index** — **read-only** reported-questions audit via `ReviewsEscalations`: `reportedQuestions(null, $branch_id)`. Branch admins are **not in the approval chain** (Student → Teacher → Support Team); observability only, no branch column, no actions.
- **AuthController** — login/logout/change-password.

### The AI touchpoint — `ReportService` (`app/Services/V2/ReportService.php`)

This is the **only place a customer-facing AI/LLM call runs in V2**, and it is **OpenAI, not Claude** (`config('services.openai.model', 'gpt-4o-mini')`).

- **Input to the model:** the student's **first name only** plus aggregate per-subject / per-topic / per-subtopic accuracy numbers and the subject's `syllabus_topics` list (to constrain recommendations to real syllabus prerequisites). No full name, email, roll number, or answer text leaves the server. Enforced first-name-only privacy is called out in the code comment (`ReportService.php:15-17`).
- **Prompt / tone:** system prompt = *"concise, encouraging academic mentor … Plain, parent-friendly language. Always return valid JSON"* (`ReportService.php:87`); user prompt asks for a **"parent-friendly"** 2–3 sentence summary + per-subject comments naming strongest/weakest topics and 1–3 subtopics to focus on (`ReportService.php:76`). `temperature=0.5`, 30s timeout, `response_format: json_object`.
- **Fallback:** if no API key, the call fails, or JSON parsing fails, a **deterministic stats-driven template** (`fallback()`) writes the narrative instead; `source` is recorded as `openai` / `template` / `none` (no tests). The user never sees an error.
- Full details in [module 11 — Reports (current system)](../modules/11-reports-current-system.md) (AI-context summary) and the deeper [`docs/modules/09-reports-pdf.md`](../../modules/09-reports-pdf.md) (dompdf + OpenAI specifics).

**Key nuance for AI agents:** the report is written in *parent-friendly* tone but is **delivered to the branch admin, not to any parent** — there is no parent login. See [06 — Parent (not implemented)](./06-parent-not-implemented.md).

## Data visible vs editable

- **Visible (within `branch_id`):** the campus's grades, subjects, classes, teachers, students, exams, attempts, analytics, saved reports, and the reported-questions audit. **Never** another branch's or another school's data.
- **Editable:** nothing in the org hierarchy (no grade/teacher/student CRUD here — that is school admin). The branch admin's only **write** is **generating a report** (persists a `Report` row) — everything else is read-only.

## Core journeys

1. **Generate a progress report** → Analytics → `student` page → pick a duration/range → `POST students/{student}/reports` (`report.generate`) → OpenAI-or-template narrative → `Report` row saved → download via `GET reports/{report}/pdf` (`report.pdf`).
2. **Campus performance review** → dashboard (`index`) → grade/subject/teacher/class → student → `studentPaper`.
3. **Audit reported questions on the campus** → `flagged_questions.index` (read-only).

## Notifications received

No branch-admin notification bell exists (only teacher/student portals have `NotificationBell`/`NotificationIndex`). Branch admins do not receive in-app `v2_notifications`. See [module 16 — Notifications](../modules/16-notifications.md).

## Role-specific AI opportunities

- **The student progress report is already the flagship AI feature** (OpenAI narrative). *Implemented in code.* Recommended hardening: move it behind the same grounding discipline as the tutor spec — never invent syllabus facts, constrain to `syllabus_topics` (already done), keep first-name-only privacy.
- **Provider migration** — if the platform standardizes on Claude, this OpenAI call is the single customer-facing swap point; keep the deterministic fallback and the JSON contract. *Recommended for AI.*
- **Report Q&A** — let a branch admin ask follow-ups ("which topic should we prioritize school-wide?") grounded in the same aggregate stats. *Future.*

## AI risks

- **Tenancy leakage** — a branch admin must **never** see another branch's students. The report actions re-verify `branch_id` on both `generate` and `pdf`; any AI feature must inherit that check and not read across branches.
- **PII minimization** — the existing report deliberately sends only first name + numbers to the LLM. Do **not** widen the payload (no full names, no answer text, no other students).
- **No hallucinated syllabus** — recommendations are constrained to the subject's real `syllabus_topics`; an AI feature must keep that constraint and must not invent marks or topics.
- **Parent-friendly ≠ parent-delivered** — do not build auto-email-to-parent flows assuming a parent identity; there is none (see [06](./06-parent-not-implemented.md)).

## Future Improvements

- Report **list / regenerate / delete / email / schedule** endpoints — **not implemented** (listing is inline on the student analytics page; PDF is regenerated on demand). See module 09 §13.
- Branch-admin **notification inbox** — not implemented.
- School-wide (cross-branch) report rollups — not implemented at branch scope.
