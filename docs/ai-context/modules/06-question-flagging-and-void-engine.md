# 06 — Question Flagging & Void Engine

> Code-grounded reference. Verify against source before acting.

**Siblings:** [04 — Question Bank](./04-question-bank.md) ·
[05 — Question Versioning & Quality Review](./05-question-versioning-and-quality-review.md) ·
[07 — Quality Propagation Engine](./07-quality-propagation-engine.md)

---

## Purpose

Two coupled mechanisms for handling "this question looks wrong":

1. **Flagging** — a lightweight report against a question, at two tiers:
   - **Teacher flag** (bank or exam): pulls the question from the live pool immediately
     (`status → under_review`) and opens a Support quality review.
   - **Student flag** (during an exam / on their result): a **soft** signal — the question stays
     live; it is routed to that exam's teacher, who decides.
2. **Void engine** — `v2_exam_questions.is_voided` is the **single source of truth** for scoring.
   A voided exam-question stays attached (for audit + student visibility) but is excluded from every
   score, percentage, topic stat and ranking. A teacher voids locally for one exam; the Phase 3
   propagation engine (module 07) voids globally across history.

Flagging is the front door to the Quality Review queue (module 05); voiding is the marksheet
consequence. Read-only "Reported Questions" audits give branch/school admins visibility without any
approval power.

Status: **Implemented in code.**

## Users / Roles

| Role | Capability | Guard |
|------|-----------|-------|
| **Student** | Soft-flag a question during an exam / on the result (`POST exams/{exam}/questions/{question}/flag`) | `auth:v2_student` |
| **Teacher** | Flag a bank question (`POST questions/{question}/flag`); on their own exam, `dismissFlags` or `sendForReview` (void + escalate) | `auth:v2_teacher` |
| **Super Admin** | The Quality Review queue (`index`, `markCorrect`) — module 05 | `auth:v2_super_admin` |
| **School / Branch Admin** | **Read-only** "Reported Questions" audit; **not** in the approval chain | `auth:v2_school_admin` / `auth:v2_branch_admin` |

Approval chain: **Student → Teacher → Support Team (Quality Review)**. Admins are observers only.

## Current Implementation

### Controllers
- `app/Http/Controllers/V2/Teacher/QuestionFlagController.php::store(Request, Question)` — teacher
  flags a **bank** question. Authorized only if the teacher teaches the question's subject
  (`$teacher->classes()->pluck('subject_id')->contains($question->subject_id)` else 403). Validates
  `reason` (∈ `QuestionFlag::REASONS`), `note`, `screenshot`. `updateOrCreate` one open flag per
  `(question, teacher)`. If `status==='active'` → `under_review`. Calls `QualityReview::openFor()`.
  Audit `question.flagged`.
- `app/Http/Controllers/V2/Student/QuestionFlagController.php::store(Request, Exam, Question, NotificationService)` —
  soft flag. Authorized only if the student is enrolled in the exam's class (403) and the question is
  in the exam (404). **Refuses** re-flagging if the pivot is already voided or the student's prior
  flag is `dismissed`/`escalated`/`voided` (returns a tailored message). `updateOrCreate` one open
  flag per `(student, exam, question)`. **Question stays live.** Notifies the exam's teacher(s).
  Audit `question.flagged_by_student`.
- `app/Http/Controllers/V2/Teacher/ExamController.php`:
  - `dismissFlags(Exam, Question)` — `assertOwnsExam`; sets all open student-level flags on this
    `(exam, question)` to `dismissed`. **Question kept, no void.**
  - `sendForReview(Exam, Question, Request, ExamService, NotificationService)` — the void+escalate
    path (see Core Flows below).
- `app/Http/Controllers/V2/SuperAdmin/QuestionFlagController.php` — the Quality Review queue
  (`index`, `markCorrect`); documented in [module 05](./05-question-versioning-and-quality-review.md).
- `app/Http/Controllers/V2/SchoolAdmin/FlaggedQuestionController.php::index()` &
  `.../BranchAdmin/FlaggedQuestionController.php::index()` — **read-only** audits. Both `use
  ReviewsEscalations` and render `v2.admin.flagged_questions`. School admin scopes to `school_id`
  (all branches, `showBranch=true`); branch admin scopes to `branch_id` (`showBranch=false`).

### Shared trait
`app/Http/Controllers/V2/Concerns/ReviewsEscalations.php` — assembles the read-only audit.
`reportedQuestions(?schoolId, ?branchId)` merges `examReports()` (student-originated, grouped per
`(exam, question)`) + `bankReports()` (teacher bank flags, no exam) and `attachQualityReview()`
overlays the Support outcome (`qrOutcome`), completion (`qrCompleted`), propagation
(`qrPropagated`), and an admin-scoped `propScope` count of the propagation effect (module 07).
Nothing here is actionable.

### Void engine
`app/Services/V2/ExamService.php::voidExamQuestion(Exam, int $questionId, int $teacherId, string $reason): array` —
finds the pivot; if missing or already voided, returns `[]` (no-op). Otherwise sets `is_voided=true`,
`void_reason`, `voided_by`, `voided_at` (note: it does **not** set `void_source`, so it stays the
column default `'teacher'`), then `recomputeExamScores($exam)` and returns the student ids whose
score changed. The propagation engine sets `void_source='quality_review'` explicitly (module 07).

### Model
`app/Models/V2/QuestionFlag.php` — `REASONS` const (5 reason→label pairs). Relations: `question`
(`withTrashed`), `teacher`, `student`, `exam`, `school`. Scopes: `open`, `studentLevel`,
`teacherLevel`. `reasonLabel()`.

### Views
- `resources/views/v2/partials/flag_modal.blade.php` — teacher "Report a wrong question" modal
  (exam preview + gallery), submits via fetch.
- `resources/views/v2/partials/student_flag.blade.php` — student inline "Report a problem" control
  on take/result screens; shows lock messages for dismissed/escalated/voided and (Phase 4) the
  decided Quality Review outcome.
- `resources/views/v2/super_admin/question_flags/index.blade.php` — the Support queue (module 05).
- `resources/views/v2/admin/flagged_questions.blade.php` — the shared read-only admin audit.

### Tests
No dedicated flagging/void feature test found under `tests/` (Not implemented — a coverage gap).
(The mistake-bank tests do assert `is_voided` exclusion — `tests/Feature/V2/LearningHubMistakeBankTest.php`.)

## Data Model

### `v2_question_flags`
Base `2026_06_24_000001`; student tier `2026_06_25_000001`; `quality_review_id` added by the
versioning-pointers migration `2026_06_25_000005`.

| Column | Type / default | Notes |
|--------|----------------|-------|
| `id` | PK | |
| `question_id` | FK → `v2_questions` (cascade) | |
| `school_id` | FK → `v2_schools` (nullOnDelete), nullable | reporting context |
| `flagged_by_teacher_id` | FK → `v2_teachers` (nullOnDelete), nullable | |
| `level` | string(16), default `teacher` | `teacher` / `student` |
| `flagged_by_student_id` | FK → `v2_students` (nullOnDelete), nullable | |
| `exam_id` | FK → `v2_exams` (nullOnDelete), nullable | ties a student flag to its paper |
| `quality_review_id` | (added `..._000005`), nullable, indexed | links the report to its Support review |
| `reason` | string(40) | `incomplete_text` / `image_issue` / `wrong_answer` / `formatting` / `other` |
| `note` | text, nullable | |
| `screenshot_path` | string, nullable | public disk |
| `status` | string(16), default `open` | `open` / `escalated` / `resolved` / `dismissed` |
| `resolved_by` | uint, nullable | |
| `resolved_at` | timestamp, nullable | |

Indexes: `(status, question_id)`, `(question_id, flagged_by_teacher_id, status)`,
`(level, status, exam_id)`, `(exam_id, question_id, status)`.

> **Status note:** the base migration comment lists only `open|resolved|dismissed`, but the code
> also uses **`escalated`** (`sendForReview` locks student flags to `escalated`; `openFor` and the
> queue treat `open`+`escalated` as unresolved). Treat the live status set as
> `open | escalated | resolved | dismissed`.

Ownership/tenancy: a flag carries the reporter's `school_id` for scoping the admin audits, but the
**question it targets is global** (module 04). Void effects (below) are per-exam and therefore
school-scoped.

### Void columns on `v2_exam_questions`
Added by `2026_06_25_000002_add_question_voiding_to_exams` and `2026_06_25_000009_add_void_source_to_exam_questions`.

| Column | Type / default | Notes |
|--------|----------------|-------|
| `is_voided` | boolean, default false | **single source of truth for scoring** |
| `void_reason` | string(60), nullable | |
| `voided_by` | FK → `v2_teachers` (nullOnDelete), nullable | null when voided by propagation |
| `voided_at` | timestamp, nullable | |
| `void_source` | enum `teacher` / `quality_review`, default `teacher` | who/why: local teacher void vs. global propagation |
| `void_quality_review_id` | uint, nullable, indexed | the originating review (propagation only) |

`2026_06_25_000002` also adds advisory-recompute columns to `v2_exam_attempts`
(`adjusted_score`, `adjusted_total`, `adjusted_at`) and `v2_schools.apply_retro_void`
(boolean, default false) — the post-release policy switch.

Model `app/Models/V2/ExamQuestion.php`: `$fillable` includes all void fields; `voider()` →
`Teacher`; casts `is_voided`→bool, `voided_at`→datetime.

## Core Flows

### Teacher bank flag (auto-hide)
Teacher `store` → one open `(question, teacher)` flag (`level=teacher`, no `exam_id`) → question
`active → under_review` → `QualityReview::openFor()`. The question leaves the exam-generation pool
until Support decides (module 05).

### Student soft flag → teacher decision
1. Student `store` → one open `(student, exam, question)` flag (`level=student`, `exam_id` set).
   Question stays live; teacher notified.
2. Teacher, on their own exam, chooses:
   - **`dismissFlags`** — open student flags → `dismissed`. Question kept, no void.
   - **`sendForReview`** — the void + escalate path.

### `sendForReview` (the void lifecycle) — 5 steps
(`Teacher/ExamController::sendForReview`, verified.)
1. Determine `topReason` = most common reason among open student flags (fallback `wrong_answer`).
2. **Void for this exam**: `ExamService::voidExamQuestion(exam, questionId, teacherId, topReason)`
   → sets `is_voided=true` (+ `void_reason`/`voided_by`/`voided_at`; `void_source` stays default
   `teacher`), recomputes scores; changed students get `announceScoreAdjusted`.
3. Open student flags on this `(exam, question)` → `escalated` (no re-report).
4. `updateOrCreate` a **teacher-level open flag** (`level=teacher`, this `exam_id`, `status=open`) —
   this is the Support queue item.
5. If the bank question is `active` → `under_review`; then `QualityReview::openFor()` attaches the
   teacher flag + the just-escalated student flags to the one open review.
Audit `exam.question_sent_for_review`.

### Marksheet policy (release-aware) — from the void migration
- **Voided before results released** → official: attempt `score`/`total` recomputed.
- **Voided after results released** → official marksheet preserved; the advisory recompute lands in
  `adjusted_*`, applied to the visible score **only if** the school set `apply_retro_void=true`.
  No answer rows are ever mutated.

### Support decision & global propagation
The teacher-level open flag surfaces in the Support queue (module 05). The Support outcome may be
`correct` (dismiss), `cosmetic`, or `material`. A **material** outcome can be propagated across
history by the Phase 3 engine, which voids matching historical pivots with
`void_source='quality_review'` — see [module 07](./07-quality-propagation-engine.md).

## Inputs
- Student/teacher flag submissions (`reason`, `note`, optional `screenshot`).
- Teacher `dismissFlags` / `sendForReview` actions on their own exams.
- School `apply_retro_void` setting (post-release policy).

## Outputs
- `v2_question_flags` rows (the reports), transitioning through `open/escalated/resolved/dismissed`.
- `v2_exam_questions.is_voided` + provenance; recomputed `v2_exam_attempts`
  (`score`/`total` or advisory `adjusted_*`).
- An open `QualityReview` feeding module 05; teacher/student notifications.
- Read-only admin audit rows (`ReviewsEscalations::reportedQuestions`).

## Dependencies
- `App\Services\V2\ExamService` (`voidExamQuestion`, `recomputeExamScores`).
- `App\Services\V2\NotificationService` (student/teacher flag + score-adjusted notices).
- `App\Models\V2\QualityReview::openFor` (module 05).
- Module 07 (propagation) consumes material outcomes and re-voids historically.
- `AuditLogger` (`question.flagged`, `question.flagged_by_student`, `exam.question_sent_for_review`).

## Security / Access Rules
1. **Teacher subject scope** — a teacher may only flag a bank question in a subject they teach (403).
2. **Student enrolment scope** — a student may only flag a question in an exam of a class they are
   enrolled in, and only questions actually in that exam (403/404).
3. **Ownership** — `dismissFlags`/`sendForReview` require `assertOwnsExam`.
4. **Re-flag guards** — one open flag per reporter; students cannot re-open a
   dismissed/escalated/voided report; `markCorrect` 409s an already-decided review (module 05).
5. **Admins are read-only** — school/branch admins can view but never act on the approval chain.
6. **`is_voided` is authoritative** — all scoring/analytics/ranking read it; it is never bypassed.
7. **Post-release protection** — released marksheets are preserved unless `apply_retro_void`.

## Existing AI-Relevant Context
- Flags carry a **human-authored `reason` + `note` + optional `screenshot`** — a rich, structured
  signal of *what* is wrong with a question, ideal for AI triage.
- The `void_source` / `void_quality_review_id` provenance already separates "teacher's local call"
  from "globally confirmed error" — an AI must respect this distinction when reasoning about score
  history.
- `voided`/`escalated` state on the exam pivot tells a downstream AI (e.g. the Mistake-Bank tutor,
  module 12) that a question was excluded — the Mistake Bank already skips voided questions.

## AI Opportunities
- **Flag triage assistant** — cluster incoming flags by question + reason, summarize the reports,
  and propose a likely outcome (correct / cosmetic / material) for the Support admin to confirm
  (never auto-decide). *Recommended for AI.*
- **Screenshot / stem analysis** — read a flag's screenshot + the question snapshot to suggest
  whether the reported defect (cut-off text, cropped image) is real. *Recommended for AI.*
- **Duplicate-report detection** — recognise the same underlying defect reported across schools.
  *Future.*

## AI Risks
- **Never auto-void or auto-escalate.** Voiding changes marks; it must stay a teacher/Support human
  action. AI may summarize/suggest only.
- **Respect the release-aware policy.** An AI must not surface or apply a post-release void contrary
  to `apply_retro_void`.
- **Do not leak across the admin boundary.** Branch/school admins are read-only; an AI feature for
  them must not gain write access or expose other branches' data.
- **Preserve provenance.** Do not conflate a teacher's local `void_source='teacher'` with a
  quality-confirmed `void_source='quality_review'`.

## Future Improvements (future)
- Feature-test coverage for the flag → void → review lifecycle. *Not implemented.*
- A full escalation chain beyond teacher→Support (school/branch admin escalation is currently
  read-only audit only). *Future (noted in project memory `v2-student-flag-void.md` as a later slice).*
- AI-assisted Support triage. *Recommended for AI / future.*
