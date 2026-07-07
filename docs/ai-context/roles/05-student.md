# 05 — Student

> Code-grounded reference. Verify against source before acting.

**Role siblings:** [01 — Super Admin](./01-super-admin.md) · [02 — School Admin](./02-school-admin.md) · [03 — Branch Admin](./03-branch-admin.md) · [04 — Teacher](./04-teacher.md) · [06 — Parent (not implemented)](./06-parent-not-implemented.md)

**Related modules:** [09 — Exam Attempts / Checking / Results](../modules/09-exam-attempts-checking-results.md) · [12 — Mistake Bank](../modules/12-mistake-bank.md) · [13 — Learning Hub / Worked Solutions / Assets](../modules/13-learning-hub-worked-solutions-assets.md) · [14 — Interactive Widgets](../modules/14-interactive-widgets.md) · [15 — Notes Module](../modules/15-notes-module.md) · [16 — Notifications](../modules/16-notifications.md) · [`NEXT_SESSION_AI_TUTOR_PROMPT`](../NEXT_SESSION_AI_TUTOR_PROMPT.md)

**This is the primary AI-tutor user.** The per-student learning context (mistakes, mistake events, topics, notes, attempts) is the richest and most consequential surface in V2. Read the AI-risk section carefully.

---

## Identity

| Property | Value | Source |
|----------|-------|--------|
| Guard | `v2_student` (session) | `config/auth.php` |
| Provider / model | `v2_students` → `App\Models\V2\Student` | `config/auth.php` |
| Table | `v2_students` | `app/Models/V2/Student.php:20` |
| Tenancy scope | **`school_id`** (global scope; `branch_id` when a branch admin queries) + **class-level M2M** | `Student::booted()` global scope, `app/Models/V2/Student.php:50-67` |
| Classes | M2M via `v2_student_enrollments` (`status` pivot) | `Student::classes()`, `app/Models/V2/Student.php:84-89` |
| Route prefix | `/v2/student/...`, name prefix `v2.student.` | `routes/v2.php:327` |

A student is scoped to their school by the global scope and reaches exams only through **active enrollments** (`Student::classes()->wherePivot('status','active')`). Their "primary grade" is derived from their active enrollments' classes (`Student::primaryGrade()`), falling back to the denormalized `grade` column. `StudentMistake`, `StudentEnrollment`, and `Exam` carry the same guard-keyed global scope, so a student can only ever touch their own school's data — and ownership checks (`authorizeMistake()` 403s on a foreign mistake) add a per-record gate on top.

Auth mechanics: throttled login, `LoginAttempt` logging. **Note:** the student route group does **not** wrap `must_change_password` or `session_version` middleware the way the admin/teacher groups do (`routes/v2.php:334-378`) — the login/dashboard is direct.

## Screens they use

Blade views under `resources/views/v2/student/`:

| Area | Views |
|------|-------|
| Dashboard | `dashboard/index.blade.php` |
| Stats | `stats/index.blade.php` |
| Exams | `exams/{index,take,result,result_pending}.blade.php` |
| Learning Hub | `learning_hub/{index,review}.blade.php` |
| Notifications | `notifications.blade.php` (route `v2.student.notifications.index`) |
| Auth | `auth/login.blade.php` |

Inertia / React "Learning Studio" (`resources/js/learn/`):
- `Pages/Solution.jsx` — the worked-solution **studio** (dynamic tabs per available asset: question / sim / solution / flashcards / memcards / flow / json; "Add to notes" bridge).
- `Pages/Notes.jsx` — Notion-style block editor shell (sidebar tree/search + BlockNote editor).
- `studio/{Flashcards,Flow}.jsx`, `WidgetRenderer.jsx`, `lib/{markdown,tex}.jsx`, and `notes/blocks/*` (Callout, Mermaid, Widget, QuestionFigure, MistakeBlock, AssetSnapshot, …).

Livewire (`app/Livewire/Student/`): **NotificationBell**, **NotificationIndex** (bell + full page). *(Note: `PaperRunner`, `PracticeRunner`, `TestAttempt`, `ResultSummary`, `SubjectShow` also live in `app/Livewire/Student/` but are **V1** components — the V2 exam flow is the `ExamController` Blade path below. Do not confuse them.)*

## Capabilities (controllers + actions)

Controllers in `app/Http/Controllers/V2/Student/`:

- **DashboardController@index** — `ExamService::studentDashboard()` (overall stats, per-subject coverage, upcoming exams).
- **StatsController@index** — per-topic mastery, score trend (last 10), monthly activity, per-subject performance, per-test history.
- **ExamController** — `index` (exams across active classes; per-topic performance only shown once released), `take` (start/resume an attempt against the **frozen** question version), `submit`, `result`. See below + [module 09](../modules/09-exam-attempts-checking-results.md).
- **LearningHubController** — the Mistake Bank "My Mistakes": `index`, `review`, `studio` (Inertia), `updateStatus`, `asset`. See below + [module 12](../modules/12-mistake-bank.md).
- **QuestionFlagController@store** — student **soft-flags** a question (during/after an exam) with a reason + optional screenshot. Creates a `level=student`, `status=open` `QuestionFlag`, **does not void**, and notifies the exam's teacher(s). This is the student's entry into the Student → Teacher → Support chain.
- **Notes controllers** (Notion-style, Inertia/React — see [module 15](../modules/15-notes-module.md)):
  - `NotesController` — index shell + `show` a page.
  - `NotesPageController` — `store`/`update` (autosave with optimistic concurrency → 409 on stale version)/`meta` (rename/pin/favorite/archive/tag)/`destroy`/`outline`.
  - `NotesTreeController` — `index` (sidebar tree) + `search`.
  - `NotesImportController@store` — bridge that imports a **released** mistake's asset/widget/mistake into a note as blocks (auto-tags from the mistake's curriculum).
  - `NotesUploadController` — `store`/`show` for pasted/uploaded images (private disk, owner-only signed serving, throttled).
  - `NotesVersionController` — `index` (autosave history) + `restore`.
- **AuthController** — showLogin/login/logout.

### Taking an exam & the Mistake Bank capture

`take` renders the **frozen** questions; `submit` auto-marks against that frozen version, inserts rows into `v2_exam_answers`, sets the attempt `submitted`, records an audit log, and — **post-commit, wrapped in try/catch so a failure can never break submission** — calls `MistakeBankService::syncFromAttempt()` (`app/Services/V2/ExamService.php:219-225`). Every **wrong, non-voided** answer becomes one persistent `v2_student_mistakes` row (one per student+question, `mistake_count` incremented on repeats, idempotent per attempt), plus an append-only `v2_student_mistake_events` row.

### The release gate (the single most important rule)

- `result` returns `exams/result_pending.blade.php` until the exam's `results_released_at` is set by the teacher; only then does it show scores/answers (`exams/result.blade.php`).
- The Mistake Bank captures immediately but **stays hidden** until the mistake's `latest_exam_id` exam is released (`MistakeBankService::releasedQuery()`). `review`/`studio`/`asset` `abort(404)` unless the exam's results are released.
- **A student never sees a mistake, worked solution, or asset for an exam whose results the teacher has not released.** This gate must survive into any AI feature. See [module 12](../modules/12-mistake-bank.md) §Security.

### Learning Hub / Studio assets

`studio` is an Inertia `Solution` page that surfaces the question's approved learning assets as tabs (worked_solution, option_explanation, flashcards, memcards, mermaid, revision_notes, common_mistakes, interactive_widget). Assets are **display-only** (never generated at request time in Phase 1) and **double-gated**: (a) release gate above, (b) asset status must be `approved`/`edited` (super-admin approval — see [01 — Super Admin](./01-super-admin.md) and [module 13](../modules/13-learning-hub-worked-solutions-assets.md)). Opening review/studio marks the mistake `reviewed` and records `asset_viewed` events. `updateStatus` transitions the mistake (`mastered` / `reset` / `archive`), student-initiated only.

## Data visible vs editable

- **Visible (own data only):** their own exams (active enrollments), attempts, released results, their own Mistake Bank + events + approved assets (release-gated), their own Notes, and their own notifications. **Never** another student's mistakes, answers, or scores.
- **Editable:** their **exam answers** (during an open attempt), **mistake mastery status** (mastered/reset/archive), their **Notes** (create/edit/version/import/upload), and **soft flags** on questions. **Not editable:** scores/marking (auto), the question bank, other students' anything, the release gate.

## Core journeys

1. **Take an exam** → `exams.index` → `exams.take` → answer → `exams.submit` → `exams.result` (pending until teacher releases).
2. **Revise mistakes** → after release → `learning_hub.index` (grouped by subject→topic, filters/mastery) → `learning_hub.review` or `learning_hub.studio` → mark mastered.
3. **Build notes** → from a mistake/asset in the studio → "Add to notes" → `notes.pages.import` → edit in the Notes editor (autosave, versions).
4. **Report a bad question** → during/after an exam → `exams.flag` (soft, routed to the teacher).

## Notifications received

Students have a real inbox (bell + page) via `app/Livewire/Student/NotificationBell.php` + `NotificationIndex.php`, `type = update` only (`Student` is an update-only notifiable). Real-time release hooks fire on `exam_released`, `results_released`, `score_adjusted`; a daily `v2:student-exam-reminders` command adds exam reminders. See [module 16 — Notifications](../modules/16-notifications.md).

## Role-specific AI opportunities

**The Mistake Bank row is the prime AI-tutor context** (`docs/ai-context/NEXT_SESSION_AI_TUTOR_PROMPT.md`). One `StudentMistake` + its `question` relation exposes, join-free, exactly the tutor payload: stem, options, correct answer, the student's own wrong answer, diagram references, subject/topic/subtopic, `mistake_count`/`review_count`/mastery status, full event history, and the question's **approved** assets.

- **Grounded per-mistake tutor** — answer "why is C right and my B wrong?" using **only** the mistake row + approved assets; save the answer to Notes. *Recommended for AI.* See [module 12](../modules/12-mistake-bank.md) §AI Opportunities.
- **Adaptive next-question** — pick a similar unmastered question from the same subtopic. *Future.*
- **Notes summarization** — condense a topic's mistakes into a revision note. *Future.*

## AI risks (read before building anything for this role)

- **Never expose another student's data.** A tutor must inherit **all three** gates: ownership (`authorizeMistake` — per-student), school isolation (global scope), and the **release gate**. A mistake row is per-student; cohort/analytics features must not leak one student's answers to a peer.
- **The release gate must hold.** Do not let an AI feature read a mistake, result, or asset whose exam results are unreleased — that bypasses the teacher's release decision.
- **Do not invent marks, topics, or answers.** The `correct_option` on the mistake row is authoritative; approved assets are the only trusted grounding. The AI must not contradict them or free-generate syllabus facts (per the tutor spec's safety rules).
- **Do not write official records.** Mistake status transitions are student-initiated; AI must not silently mark things mastered, alter scores, or resolve flags.
- **Display-only assets.** Phase 1 serves stored, human-approved assets. An AI feature that *generates* content must route through the super-admin approval gate, not straight to the student.

## Future Improvements

- **AI tutor grounded in the mistake context** — *recommended / future* (spec in `NEXT_SESSION_AI_TUTOR_PROMPT.md`).
- **`practiced` status / active-recall practice mode** — constant exists, no writer (Phase 2). See [module 12](../modules/12-mistake-bank.md).
- **`confidence` capture** — column + event constant exist, no writer wired.
- Teacher/admin views of the student Mistake Bank — not implemented.
