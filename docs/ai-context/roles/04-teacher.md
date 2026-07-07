# 04 — Teacher

> Code-grounded reference. Verify against source before acting.

**Role siblings:** [01 — Super Admin](./01-super-admin.md) · [02 — School Admin](./02-school-admin.md) · [03 — Branch Admin](./03-branch-admin.md) · [05 — Student](./05-student.md) · [06 — Parent (not implemented)](./06-parent-not-implemented.md)

**Related modules:** [01 — Roles, Permissions & Tenancy](../modules/01-roles-permissions-tenancy.md) · [08 — Exam Builder](../modules/08-exam-builder.md) · [09 — Exam Attempts / Checking / Results](../modules/09-exam-attempts-checking-results.md) · [10 — Analytics & Statistics](../modules/10-analytics-and-statistics.md) · [16 — Notifications](../modules/16-notifications.md)

---

## Identity

| Property | Value | Source |
|----------|-------|--------|
| Guard | `v2_teacher` (session) | `config/auth.php` |
| Provider / model | `v2_teachers` → `App\Models\V2\Teacher` | `config/auth.php` |
| Table | `v2_teachers` | `app/Models/V2/Teacher.php:20` |
| Tenancy scope | **`school_id`** (global scope) + **class-level M2M** | `Teacher::booted()` global scope, `app/Models/V2/Teacher.php:50-64` |
| Classes | M2M via `v2_class_teachers` (`is_primary` pivot) | `Teacher::classes()`, `app/Models/V2/Teacher.php:81-86` |
| Route prefix | `/v2/teacher/...`, name prefix `v2.teacher.` | `routes/v2.php:270` |

A teacher is scoped to their **school** by the global scope, then further narrowed to **only the classes they are assigned to** via the `v2_class_teachers` pivot (`Teacher::classes()` with `withPivot('is_primary')`). They own the exams they create (`created_by`) and can only build exams for their own classes (`store()` validates `class_id` with `Rule::in($classIds)`). Student and class analytics are narrowed further to the teacher's own `subject_ids` so they never see another teacher's subject data.

Auth mechanics: throttled login, `LoginAttempt` logging, `must_change_password` first-login gate. `session_version` is incremented by admins on deactivation/reset to force logout.

## Screens they use

Blade views under `resources/views/v2/teacher/`:

| Area | Views |
|------|-------|
| Dashboard | `dashboard/index.blade.php` |
| Stats | `stats/index.blade.php` |
| Exams | `exams/{index,create,custom,show,student_paper}.blade.php` + partial `exams/_selected_cards.blade.php` |
| Classes / students | `classes/{index,show}.blade.php`, `students/show.blade.php` |
| Notifications | `notifications.blade.php` (route `v2.teacher.notifications.index`, a `Route::view`) |
| Auth | `auth/{login,change_password}.blade.php` |

Livewire components (`app/Livewire/Teacher/`):
- **TestGenerator** — multi-step guided random-test wizard (allocate a question count per topic). *Phases 1–2 note: `generate()` currently flashes a message and is a persistence placeholder* — the shipped generation path is the `ExamController` random generator below.
- **QuestionPicker** — search/filter the bank and hand-pick questions for a custom exam.
- **NotificationBell** — header bell: unread count, up to 8 *attention* items (student-weakness flags, worst-first) + up to 8 *update* items (exam released / results released / score adjusted). Actions: `markAllRead`, `markRead`, `markHandled` (sets `resolved_at`), `snooze` (+7d).
- **NotificationIndex** — full paginated page with `attention`/`updates` tabs and class/subject/status/read filters.

## Capabilities (controllers + actions)

Controllers in `app/Http/Controllers/V2/Teacher/`:

- **DashboardController@index** — teacher's classes + `ExamService` overview (teacherOverview, teacherTopicStats, examKindSplit).
- **StatsController@index** — merged overview + engagement, worst-first topics, score distribution, 15-day trend, per-student performance with `needsAttention` flag (<50% avg or 0 attempts). See [module 10](../modules/10-analytics-and-statistics.md).
- **ExamController** — the full exam lifecycle (detailed below).
- **ClassController** — `index` (classes + counts), `show` (class topic stats + per-student-per-topic matrix; teacher must be in the pivot), `student` (one student, narrowed to the teacher's subjects).
- **QuestionFlagController@store** — teacher reports a **bank** question (no exam). Creates a `level=teacher`, `status=open` `QuestionFlag`, **auto-hides** the question (`active → under_review`), and opens a `QualityReview`. Reasons: `incomplete_text | image_issue | wrong_answer | formatting | other`; optional note + screenshot.
- **AuthController** — login/logout/change-password.

### Exam lifecycle (ExamController)

**States:** `draft → released → (students take) → results released`. See [module 08](../modules/08-exam-builder.md) and [module 09](../modules/09-exam-attempts-checking-results.md).

**Build — two modes:**
- **Random generator** — `create` (form) → live preview via `generatePreview` (`ExamService::drawRandomIds`, filters to active questions that have options and match the class subject), per-question `swapPreview` (replace one slot with a fresh draw), `regeneratePreview` (redraw only non-kept slots) → `store` commits (`ExamService::generate`).
- **Custom selection** — `custom` (gallery/list, paginated 24) → `selectedCards` (drawer HTML for the picked set, order preserved) → `storeCustom` (`ExamService::createFromQuestions`).

**Release** — `release(Exam)`:
- **Pre-flight guard:** blocks release if any exam question maps to a flagged/`under_review` bank question (teacher must regenerate first).
- Sets `status=released`, `released_at`, `available_from` (now / +5 / +10 / scheduled), `available_until = available_from + duration`.
- Fires `NotificationService::announceExamRelease()` → students get an `exam_released` or `exam_scheduled` notification.

**Release results** — `releaseResults(Exam)`:
- **Only after the exam window closes** (`isExpired`). Sets `results_released_at` (or nulls it to re-hide).
- Fires `announceResults()` **only on first release** (re-hides don't re-notify). This `results_released_at` is the **same gate** that unlocks the student's result page, the Mistake Bank, and the Learning Hub (see [05 — Student](./05-student.md) and [module 12](../modules/12-mistake-bank.md)).

**Paper review** — `show(Exam)` (class roster + attempts + the flag-triage grid), `studentPaper(Exam, Student)` (read-only frozen-version replay of what the student saw).

### Student-flag triage & the void/escalate engine

Every question on `exams.show` that has open/voided/escalated student reports appears in a grid with a timeline, reason, screenshots, and the attached `QualityReview` outcome. The teacher has two actions:

- **`dismissFlags(Exam, Question)`** — mark all open student flags `dismissed` (`resolved_at`, `resolved_by`). The question stays intact and un-voided. No Support review.
- **`sendForReview(Exam, Question)`** — the **atomic escalation** (the teacher's entry into the quality-review chain):
  1. `ExamService::voidExamQuestion()` — sets `is_voided=true`, `void_reason` (= top student reason), `voided_by`, `voided_at`, then **recomputes every submitted attempt's score/total excluding the voided question**, returning the students whose visible score changed.
  2. If a **released** exam's scores changed → `announceScoreAdjusted()` notifies exactly those students (`score_adjusted`).
  3. Student flags on that question → `escalated`.
  4. Creates/updates a **`level=teacher`, `status=open` `QuestionFlag`** — this row is the Support Team queue item.
  5. Pulls the **bank** question to `under_review` (if active) and ensures an open `QualityReview` (`QualityReview::openFor`).

The escalation chain is **Student → Teacher → Support Team (super admin)**. `is_voided` on `v2_exam_questions` is the single source of truth for the per-exam academic effect; the bank question status (`under_review`) is separate. School/branch admins only *observe* this via `ReviewsEscalations` — they take no action. See [`quality-review-system.md`](../../quality-review-system.md).

## Data visible vs editable

- **Visible (within `school_id` + assigned classes):** their classes' students, enrollments, exams, attempts, per-topic/per-student analytics (narrowed to the teacher's subjects), and the flag-triage grid on their own exams. **Never** another teacher's classes or another school's data.
- **Editable:** their own **exams** (create/custom/generate/release/release-results), **per-exam voids** (via `sendForReview`), **student-flag triage** (dismiss/escalate), and **bank flags** (report a wrong question → auto-hide + `QualityReview`). **Not editable:** the question bank itself (super-admin CRUD), org hierarchy (school admin), quality-review *outcomes* (super-admin decides; the teacher only escalates).

## Core journeys

1. **Assign an exam** → `exams.create` (random) → preview / swap / regenerate → `store` → `release` (schedule optional) → students notified.
2. **Custom exam** → `exams.custom` → pick from gallery → `storeCustom` → release.
3. **Publish results** → after the window closes → `releaseResults` → students notified; results, Mistake Bank, and Learning Hub unlock.
4. **Handle a reported question** → `exams.show` triage grid → `dismissFlags` (keep) or `sendForReview` (void + escalate to Support).
5. **Report a bank question** → `questions.flag` → auto-hidden + queued for Quality Review.
6. **Coach a weak student** → NotificationBell *attention* item → class/student analytics → mark handled.

## Notifications received

Teachers have a real notification inbox (bell + page). Categories/types via `NotificationService`:
- **attention** — `flagAttentionSubject` raises a per-(student, subject) weakness flag; resolved by `resolveSubjectFlag` (owned by *improve/handled*, not the generator — see the teacher-notifications memory note).
- **update** — `exam_released` / `exam_scheduled`, `results_released`, `score_adjusted`.
- A daily `v2:teacher-attention-reminders` command backfills reminders.

See [module 16 — Notifications](../modules/16-notifications.md); code of record `app/Services/V2/NotificationService.php`, `app/Livewire/Teacher/NotificationBell.php` + `NotificationIndex.php`.

## Role-specific AI opportunities

- **Assisted exam assembly** — suggest a topic/difficulty mix for a target class from its weak-topic analytics, then feed the random generator. *Recommended for AI.*
- **Flag-triage assistant** — when a question is reported, draft a *dismiss vs escalate* recommendation from the flag reasons + question + the class's answer distribution, for the teacher to confirm. *Recommended for AI.*
- **Attention-driven coaching prompts** — turn a `needsAttention` student + their weak topics into a concrete next-step suggestion (which topic to reteach). *Future.*

## AI risks

- **Tenancy leakage** — a teacher must **never** see another teacher's classes/students or another school. Every AI feature must run inside the `v2_teacher` request (global scope + class-pivot narrowing) and honour the subject-narrowing on student analytics.
- **Never auto-release or auto-void.** Release, release-results, dismiss, and escalate are consequential, student-visible actions with notifications and score recomputation. AI may *recommend*; a human must click. Do not bypass the pre-release flagged-question guard or the "results only after close" guard.
- **Preserve the frozen-version guarantee.** Exams render a frozen `QuestionVersion`; AI must not mutate an exam's questions after students have taken it (that is what the void/propagation engine is for).
- **Escalation, not resolution.** A teacher escalates; the *outcome* (correct/cosmetic/material) is the super-admin's. AI must not fabricate a quality outcome on the teacher's behalf.

## Future Improvements

- **TestGenerator Livewire wizard** persistence — `generate()` is a Phase 1–2 placeholder; the shipped path is the `ExamController` random generator.
- AI exam-assembly and triage assistants — *not implemented / recommended*.
- Teacher-facing view of the student Mistake Bank — *not implemented* (see [module 12](../modules/12-mistake-bank.md) future work).
