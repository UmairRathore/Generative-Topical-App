# 09 — Exam Attempts / Checking / Results

> Code-grounded reference. Verify against source before acting.

**Siblings:** [08 — Exam Builder](./08-exam-builder.md) ·
[10 — Analytics & Statistics](./10-analytics-and-statistics.md) ·
[11 — Reports (current system)](./11-reports-current-system.md) ·
[12 — Mistake Bank](./12-mistake-bank.md)

---

## Purpose

Everything from a student *taking* a released exam through **auto-marking** on submit to the
**results** page (score + per-question answer review). Also covers the **voiding / marksheet engine**
that removes a bad question from every score and stat, and the **results-release gate** that keeps
scores hidden until the teacher opens them.

Status: **Implemented in code.**

## Users / Roles

- **Students** take exams and view their own results (`auth:v2_student`, `routes/v2.php:334`,
  `343-346`). A student sees only exams in classes they are actively enrolled in, and only their own
  attempt.
- **Teachers** trigger voiding / "send for quality review" and can view any enrolled student's graded
  paper (`ExamController::studentPaper`, [module 08](./08-exam-builder.md) controller).
- **School / branch / super admins** view graded papers read-only (see *Views reuse* below and
  [module 10](./10-analytics-and-statistics.md)).

## Current Implementation

### Routes (`routes/v2.php`, student group)
| Method | URI (under `/v2/student`) | Action | Name |
|---|---|---|---|
| GET | `exams` | `index` | `v2.student.exams.index` |
| GET | `exams/{exam}/take` | `take` | `exams.take` |
| POST | `exams/{exam}/submit` | `submit` | `exams.submit` |
| GET | `exams/{exam}/result` | `result` | `exams.result` |
| POST | `exams/{exam}/questions/{question}/flag` | (StudentQuestionFlag) | `exams.flag` |

Teacher-side checking actions (same `ExamController` as module 08):
`PATCH exams/{exam}/questions/{question}/dismiss` (`dismissFlags`),
`PATCH exams/{exam}/questions/{question}/review` (`sendForReview`),
`GET exams/{exam}/students/{student}/paper` (`studentPaper`).

### Controllers
- `app/Http/Controllers/V2/Student/ExamController.php` — `index`, `take`, `submit`, `result`, plus
  the server-side deadline authority (`submissionOpen()`, `SUBMIT_GRACE_SECONDS = 120`).
- `app/Http/Controllers/V2/Teacher/ExamController.php` — `dismissFlags()`, `sendForReview()`
  (voids for the exam, escalates reports, pulls the bank question, opens a Support quality review),
  `studentPaper()`.

### Service (`app/Services/V2/ExamService.php`)
- `startAttempt(Exam, Student)` — `firstOrCreate` a `v2_exam_attempts` row (`in_progress`,
  `total_questions = exam->question_count`, `started_at = now`). One attempt per (exam, student)
  by unique constraint. (Lines 162-173.)
- `submit(ExamAttempt, responses)` — the auto-marker. In a transaction, for each frozen exam-question:
  resolves the **correct key from the frozen version first** (`questionVersion?->correct_answer ??
  question?->correct_answer`), normalizes the selected option to a single upper-case letter, sets
  `is_correct = (selected !== null && correct !== null && selected === correct)`, `updateOrInsert`s
  the `v2_exam_answers` row, and counts `score`. Marks the attempt `submitted` with `score` +
  `submitted_at`. **After commit**, calls `MistakeBankService::syncFromAttempt($result)` wrapped in
  try/catch so a capture failure can never break submission (lines 219-225 → [module 12](./12-mistake-bank.md)).
- `voidExamQuestion(Exam, questionId, teacherId, reason)` — flips the pivot `is_voided` (idempotent),
  then `recomputeExamScores()`. Returns student ids whose **visible** score changed post-release.
- `recomputeExamScores(Exam)` — re-derives every submitted attempt's `score`/`total_questions` from
  the **live (non-voided)** answers and **always writes** the visible marksheet. Answers are never
  touched. If results were already released and a score moved, that student id is returned so the
  caller can notify. (Lines 278-306.)
- `simulateRecompute(Exam, extraVoidedQids)` — read-only before/after preview (used by Phase-3
  propagation and audit capture). (Lines 317-349.)
- `resultBreakdown(Exam, ExamAttempt, flaggedQids)` — **display-only** per-attempt counts +
  per-question palette; it never recomputes marks. Voided questions counted separately so
  `breakdown.correct` matches `$attempt->score` (lines 368-416; shape below).
- `topicStatsForAttempt(ExamAttempt)` — per-topic `[{topic, correct, total, percent}]` for one attempt.

### Models
- `app/Models/V2/ExamAttempt.php` — table `v2_exam_attempts`. Global `school` scope (same pattern as
  Exam). Computed accessors: **`percentage`** = `round(score / total_questions * 100)` (0 if either
  missing, lines 54-60) and **`timeTaken`** = `"m:ss"` from `started_at`→`submitted_at` (null if
  unfinished, lines 63-71). `isSubmitted()`. Relations: `exam`, `student`, `answers`.
- `app/Models/V2/ExamAnswer.php` — table `v2_exam_answers`. `is_correct` cast bool. Fillable:
  `attempt_id`, `question_id`, `selected_option`, `correct_option`, `is_correct`. **No global scope**
  (reached only via an attempt, which is scoped).

### Migrations
- `2026_06_15_000103_create_v2_exam_attempts_table.php` — `status` in_progress|submitted, `score`
  (correct count), `total_questions`, `started_at`, `submitted_at`; `unique(exam_id, student_id)`.
- `2026_06_15_000104_create_v2_exam_answers_table.php` — `selected_option` char(1) nullable,
  `correct_option` char(1) snapshot, `is_correct` bool; `unique(attempt_id, question_id)`.
- `2026_06_25_000002_add_question_voiding_to_exams.php` — adds `is_voided`/`void_reason`/`voided_by`/
  `voided_at` to the pivot, **`adjusted_score`/`adjusted_total`/`adjusted_at`** to attempts (advisory
  recompute kept when the official marksheet is preserved), and `v2_schools.apply_retro_void`
  (opt-in to apply post-release voids to the visible marksheet, default false).
- `2026_06_25_000009_add_void_source_to_exam_questions.php` — `void_source` enum
  (`teacher`|`quality_review`) + `void_quality_review_id`.

> **Behavioural nuance to verify against the migration comment vs. current code:** the migration
> describes a "preserve official marksheet after release; keep advisory in `adjusted_*` unless
> `apply_retro_void`" rule. The **current** `recomputeExamScores()` **always writes** `score`/
> `total_questions` (it treats a void as a deliberate, visible decision) and only *reports* which
> released students changed so they can be notified. The `adjusted_*` columns exist in schema; confirm
> where (if anywhere) they are currently written before relying on them — this appears to have evolved
> past the original migration design. **unclear from codebase** whether `adjusted_*` / `apply_retro_void`
> are still read on any live path.

### Views
- `resources/views/v2/student/exams/take.blade.php` — the exam runner (Livewire + Alpine timer;
  server is the deadline authority, not the client). Chemistry exams get a periodic-table reference
  panel (`config('v2.periodic_table')`, not question content).
- `result.blade.php` — full results (score strip + palette + per-question review).
- `result_pending.blade.php` — shown when submitted but `results_released_at` is null.
- Shared partials in `resources/views/v2/partials/`: `answer_review.blade.php`,
  `result_score_strip.blade.php`, `result_palette.blade.php` — **reused** by the student result page,
  the teacher `student_paper`, and the school/branch/super-admin paper views so every graded-paper
  screen renders identically.

### Commands / Jobs / Tests
- **Jobs:** voiding/propagation has a queued job on the quality-review side (see quality-propagation
  memory note); the take/submit/mark path itself is synchronous.
- **Tests:** the only attempt-touching V2 tests are in
  `tests/Feature/V2/LearningHubMistakeBankTest.php`, which exercise **submit → Mistake Bank capture**
  (voided-exclusion, release gating, repeat increment). There are **no direct tests** of the marker
  (`submit` scoring), `recomputeExamScores`, the deadline/grace gate, or the results-release gate.
  Coverage gap.

## Data Model

`v2_exam_attempts` (score = correct count, `percentage` derived) → hasMany `v2_exam_answers`
(one per exam-question). The **`v2_exam_questions.is_voided` pivot is the single source of truth** for
which questions count; answers are the immutable record of what the student picked and the key at
submit. `adjusted_*` on the attempt is legacy advisory storage (see nuance above).

## Core Flows

**Take:** `take()` → `ensureCanTake()` (enrolled + `isLive()` OR already submitted) →
`startAttempt()` (redirects to result if already submitted) → loads frozen questions + versions,
`renderFrozenQuestions()`, renders `take.blade.php`.

**Submit (authoritative deadline):** `submit()` — idempotent if already submitted. **Server-side**
`submissionOpen()` rejects anything past `available_until + 120s` grace (the client timer/auto-submit
is only a convenience). On accept: `startAttempt()` → `ExamService::submit()` auto-marks → redirect
to result. Audit `exam.submitted`.

**Auto-marking:** pure key comparison against the **frozen version's** `correct_answer`. No partial
credit, no negative marking; a skipped/unknown answer is wrong. `score` = number correct.

**Result / release gate:** `result()` requires a submitted attempt. If `!resultsReleased()` →
`result_pending.blade.php` (no score, no answers). Once released → full review via `resultBreakdown()`
(reveals `correct_option` and per-question state). Results are viewable any time after release, even
post-expiry.

**Voiding (marksheet engine):** teacher `sendForReview()` → `voidExamQuestion()` flips the pivot →
`recomputeExamScores()` rewrites every submitted attempt's `score`/`total`. Voided questions are
excluded from marks **and** from every topic/subtopic stat across the app
([module 10](./10-analytics-and-statistics.md) — `is_voided = false` joins everywhere). If already
released, changed students are notified (`announceScoreAdjusted`). The void is permanent regardless of
the later QA outcome.

## Inputs
- Take: exam route-model (scoped) + student guard.
- Submit: `answers` map `question_id => 'A'|'B'|'C'|'D'` (POST body); normalized to first upper letter.
- Void: exam + question + teacher id + reason string.

## Outputs
- `v2_exam_attempts` (submitted, score, submitted_at) + `v2_exam_answers` rows
  (selected/correct/is_correct).
- `resultBreakdown()` returns:
  ```php
  [
    'breakdown' => ['correct'=>int,'wrong'=>int,'unattempted'=>int,'voided'=>int,'total'=>int],
    'palette'   => [ ['n'=>sort_order,'qid'=>int,'state'=>'correct|wrong|unattempted|voided',
                      'flagged'=>bool,'voided'=>bool], ... ],
  ]
  ```
- Mistake Bank rows as a side effect of submit (module 12).

## Dependencies
- **Question versioning** — marking reads the frozen version's key.
- `MistakeBankService::syncFromAttempt` (post-submit, isolated) — [module 12](./12-mistake-bank.md).
- `NotificationService` (`announceResults`, `announceScoreAdjusted`).
- `QuestionFlag` / `QualityReview` — student flags, escalation, void provenance (question-flagging &
  quality-review memory notes).
- `AuditLogger` (`exam.submitted`, `exam.question_sent_for_review`, `exam.results_*`).

## Security / Access Rules
- Guards + model `school` global scope isolate every attempt/answer to the same school.
- Take/submit assert the student is enrolled in the exam's class (`student->classes()->where(...)`).
- **Deadline is server-enforced** (`submissionOpen`), independent of the client countdown — a student
  cannot keep the tab open and submit late (only the 120s network-grace is allowed).
- Results are gated by `results_released_at`; a submitted-but-unreleased result shows nothing.
- Teacher void/paper actions re-assert ownership/enrollment (`assertOwnsExam`, enrollment checks).

## Existing AI-Relevant Context
- **No AI in marking or results.** Scoring is deterministic key comparison — this is the *trusted
  ground truth* every downstream stat, report and (future) AI tutor consumes.
- Each answer row is a clean `(student, question, selected, correct, is_correct)` tuple — the raw
  signal for misconception / distractor analysis.

## AI Opportunities *(Recommended for AI — not implemented)*
- **Per-result explanations:** on the review page, AI-generated "why B is wrong / why C is right"
  using the frozen stem + options + the student's pick (ground truth already present).
- **Misconception tagging:** classify each wrong `selected_option` against a distractor taxonomy for
  cohort-level analytics (see behavioural-analytics roadmap note).
- **Next-step nudge:** after submit, recommend targeted practice from the wrong answers → Mistake Bank.

## AI Risks
- **Never let AI alter `score`/`is_correct`.** Marking must stay a deterministic key comparison; AI
  output is advisory/explanatory only.
- **Release gate:** any AI feature on the result page must respect `resultsReleased()` — do not leak
  scores/answers before the teacher releases.
- **Void awareness:** AI over answers must exclude `is_voided` questions (single source of truth), or
  it will reason about questions that were officially removed.
- **Frozen-version fidelity:** explanations must use the frozen version the student actually saw, not
  a later-corrected live question.

## Future Improvements
- Add tests for the marker, the deadline/grace boundary, the release gate, and `recomputeExamScores`.
- Resolve the `adjusted_*` / `apply_retro_void` ambiguity (schema vs. current always-write behaviour).
- Answer rows store no timing/order — per-question time-on-task would enable richer behavioural signal.
