# 07 — Quality Propagation Engine (QR Phase 3)

> Code-grounded reference. Verify against source before acting.

**Siblings:** [04 — Question Bank](./04-question-bank.md) ·
[05 — Question Versioning & Quality Review](./05-question-versioning-and-quality-review.md) ·
[06 — Question Flagging & Void Engine](./06-question-flagging-and-void-engine.md)

---

## Purpose

Phase 3 of the Quality Review system. When a Support review decides a question had a **material**
error (the answer/content was actually wrong — module 05), the correction must be applied **backward
across history**: every historical exam that used a faulty version of that question must have the
question **voided** for scoring, marksheets **recomputed**, and affected students **notified**.

The engine is **version-targeted** (voids only exam pivots whose `question_version_id` is a confirmed
faulty version — never the corrected/future one), **auditable** (a per-pivot and per-attempt audit
trail), and **idempotent + resumable** (a failed run can be safely re-dispatched and continues where
it stopped).

Status: **Implemented in code** (Phase 3).

## Users / Roles

- **Super Admin only.** Preview + confirm routes are under `auth:v2_super_admin`
  (`routes/v2.php:130-131`).
- The **queued job** runs as a background worker (no user).
- **Effects** reach teachers (exam-local exclusion notice) and students (score-adjusted notice, for
  released exams whose visible score moved). Branch/School admins get a **read-only** scoped view of
  propagation effects (module 06's `ReviewsEscalations`).

## Current Implementation

### Controller
`app/Http/Controllers/V2/SuperAdmin/QuestionPropagationController.php`:
- `guard(QualityReview)` — `abort_unless($review->outcome === 'material' && $review->propagation_status === 'propagation_pending', 404)`. Only a decided **material** review that is still pending can be propagated.
- `preview(Request, QualityReview, PropagationService)` (route `GET question-flags/{review}/propagate`,
  name `...question_flags.propagate`) — computes a **read-only** blast radius for a candidate
  faulty-version set (default = all versions before the corrected one) and renders
  `v2.super_admin.question_propagation.preview`.
- `confirm(Request, QualityReview, PropagationService)` (route `POST question-flags/{review}/propagate`,
  name `...question_flags.propagate.confirm`) — validates the selected versions
  (`selectable()` intersects with prior-only versions; empty → 422), snapshots the blast-radius
  counts onto a `v2_quality_propagations` row keyed by `idempotency_key = 'qr:'.$review->id`, then
  `PropagateMaterialCorrection::dispatch($prop->id)`. If a run already exists in
  `pending`/`running`/`completed`, it is **left alone** (re-confirm only resumes a `failed` run).
- `defaultFaulty()` / `selectable()` — enforce that the corrected version and anything at/after it
  can **never** be selected.

### Service — the engine
`app/Services/V2/PropagationService.php` (ctor injects `ExamService`, `NotificationService`):
- `preview(QualityReview, array $versionIds): array` (lines 42-102) — no writes. Builds the
  per-version usage table (`versionTable()`), partitions matching pivots into `would_void` /
  `already_teacher` (`void_source != 'quality_review'`) / `already_quality`, and for each affected
  exam calls `ExamService::simulateRecompute($exam, [$questionId])` to count attempts, expected
  notifications (released + changed), and a ≤25-row before/after sample.
- `run(QualityPropagation): void` (lines 141-180) — the idempotent, resumable driver. Reads the
  faulty set **only** from `$prop->version_ids`, groups matching pivots by exam, processes in
  chunks of 50, then marks the propagation `completed` and the review `propagation_status='propagated'`
  and logs `quality_review.propagated`. On throw: marks `failed` + stores `error`.
- `runExam(...)` (lines 183-266) — **one exam per DB transaction**: void its not-yet-voided faulty
  pivots (`is_voided=true`, `void_reason='material_error'`, `void_source='quality_review'`,
  `void_quality_review_id`), write a `v2_quality_propagation_pivots` audit row per pivot
  (`action ∈ voided | already_voided_quality | already_voided_teacher_local`), recompute scores
  (`ExamService::recomputeExamScores`), write a `v2_quality_propagation_attempts` before/after row
  per submitted attempt, and notify (students if released+changed; teachers always, exam-local).

### Job
`app/Jobs/V2/PropagateMaterialCorrection.php` — `public int $tries = 1` (no auto-retry; prefer
explicit re-dispatch), `public int $timeout = 600`. `handle()` loads the propagation and calls
`PropagationService::run` only if `status !== 'completed'`. Constructor takes the `int $propagationId`
(not the model), so the job body re-reads current state.

### View
`resources/views/v2/super_admin/question_propagation/preview.blade.php` — version checkboxes
(corrected version disabled), a "recalculate blast radius" GET form, blast-radius metric grid
(schools / branches / teachers / exams / attempts / would-void / expected notifications, plus
already-voided counts), a ≤25-row sample of before/after score changes, and the confirm POST form.

### Tests
No propagation-specific tests found under `tests/` (Not implemented — a coverage gap).

## Data Model

Migration `2026_06_25_000010_create_quality_propagation_tables.php`. The `propagation_status` column
on `v2_quality_reviews` (`2026_06_25_000007`) is the entry gate: null / `propagation_pending` /
`propagated`.

Ownership/tenancy: the propagation record itself is **global** (super-admin owned). Its child audit
rows carry `school_id` / `branch_id` so the effect can be **re-scoped read-only** for branch/school
admins (module 06). The mutated targets (`v2_exam_questions`, `v2_exam_attempts`) are school-scoped.

### `v2_quality_propagations` — one run per material review
Model `app/Models/V2/QualityPropagation.php`. Key columns: `quality_review_id` (FK cascade),
`question_id`, `version_ids` (json, cast `array` — **the job's single source of truth**),
`confirmed_by`, `confirmed_at`, `status` (default `pending`: `pending`/`running`/`completed`/`failed`),
the blast-radius snapshot counts (`schools_count`, `branches_count`, `teachers_count`, `exams_count`,
`attempts_count`, `notifications_expected`, `notifications_sent`), **`idempotency_key` (unique** —
one run per review, format `qr:{reviewId}`), `started_at`, `completed_at`, `error` (text).
Relations: `review`, `pivots`, `attempts`.

### `v2_quality_propagation_pivots` — per-exam-question audit
Model `QualityPropagationPivot.php` (`$timestamps = false`). Columns: `propagation_id` (FK cascade),
`exam_question_id`, `exam_id`, `school_id`, `branch_id`, `question_version_id`, `action` (string 32:
`voided` / `already_voided_teacher_local` / `already_voided_quality` / ...), `processed_at`.
**Unique `(propagation_id, exam_question_id)`** (`qpp_prop_eq_unique`) → `insertOrIgnore` never
double-records.

### `v2_quality_propagation_attempts` — per-attempt before/after audit
Model `QualityPropagationAttempt.php` (`$timestamps = false`). Columns: `propagation_id` (FK cascade),
`exam_id`, `attempt_id`, `student_id`, `score_before`, `total_before`, `score_after`, `total_after`,
`released` (bool), `notified` (bool). **Unique `(propagation_id, attempt_id)`** (`qpa_prop_att_unique`).

## Core Flows

### Material correction → propagation (end to end)
1. **(Module 05)** A material review is decided → `outcome='material'`,
   `propagation_status='propagation_pending'`, `resulting_version_id` set.
2. **Preview** — admin opens `GET .../propagate`. `PropagationService::preview` targets pivots by
   `question_version_id IN (selected faulty versions)`, simulates recompute per exam, and shows the
   blast radius. Nothing is written.
3. **Confirm** — admin posts selected versions. `confirm()` rejects empty (422) and the corrected/
   future version (`selectable`), writes/reuses the `v2_quality_propagations` row (unique
   `idempotency_key='qr:{reviewId}'`) with `version_ids` + snapshot counts, status `pending`, and
   dispatches the job. A run already `pending`/`running`/`completed` is not re-created.
4. **Job (`PropagateMaterialCorrection::handle`)** → `PropagationService::run`:
   - Read faulty set from `$prop->version_ids` (never re-derived).
   - Group matching pivots by exam; process 50 exams per chunk. For each exam in its own transaction
     (`runExam`):
     - Skip any pivot already recorded for this propagation (resume point).
     - Void each not-yet-voided faulty pivot (`is_voided=true`, `void_reason='material_error'`,
       `void_source='quality_review'`, `void_quality_review_id`); record a pivot audit row. A pivot
       already voided is recorded as `already_voided_quality` / `already_voided_teacher_local` and
       **left as-is** (a teacher's deliberate local void is never overwritten).
     - If nothing was newly voided → no recompute/notify (marks unchanged).
     - Else recompute scores (`ExamService::recomputeExamScores`), record a before/after attempt
       audit row each, notify students (only if the exam's results are released **and** their score
       changed — reuses `NotificationService::announceScoreAdjusted`), and always notify the exam's
       teacher(s) of the exam-local exclusion (`notifyTeachersOfPropagation`).
   - Mark propagation `completed`, review `propagation_status='propagated'`, audit
     `quality_review.propagated`.

### Idempotency & resumability (three layers)
1. **Run level** — `idempotency_key` unique + `confirm()` refuses to re-create a
   pending/running/completed run.
2. **Driver level** — `run()` short-circuits if `status === 'completed'`; the job re-reads state.
3. **Exam/row level** — each exam is an atomic transaction; a pivot already having an audit row is
   skipped, and the audit tables' composite uniques make `insertOrIgnore` inserts safe on re-run.

## Inputs
- A decided **material** `QualityReview` with `propagation_status='propagation_pending'` and a
  `resulting_version_id`.
- Admin-selected faulty `version_ids` (must be prior versions; corrected/future rejected).

## Outputs
- Voided historical `v2_exam_questions` (`void_source='quality_review'`).
- Recomputed `v2_exam_attempts` scores (pre-release: official; post-release: per the school's
  `apply_retro_void` policy — see [module 06](./06-question-flagging-and-void-engine.md)).
- Full audit: `v2_quality_propagation_pivots` + `v2_quality_propagation_attempts`.
- Student score-adjusted notifications (released, changed) + teacher exam-local exclusion notices.
- `review.propagation_status = 'propagated'`; `AuditLog` `quality_review.propagated`.

## Dependencies
- `App\Services\V2\ExamService` — `simulateRecompute` (preview) + `recomputeExamScores` (run).
- `App\Services\V2\NotificationService` — `announceScoreAdjusted`, `notifyTeachersOfPropagation`.
- `App\Services\V2\AuditLogger`.
- The void columns / engine on `v2_exam_questions` (module 06).
- The material outcome + versioning (module 05).

## Security / Access Rules
1. **Super-admin only**; the `guard()` further requires a **material** + **pending** review.
2. **Version-targeted** — only pivots whose `question_version_id` is a confirmed faulty version are
   voided; the corrected/future version can never be selected or touched.
3. **Never overwrite a teacher's local void** — already-voided pivots are audited, not re-voided.
4. **Release-aware scoring** — post-release exams keep the official marksheet unless the school
   opted into `apply_retro_void` (module 06); students are only notified when their *visible* score
   moved.
5. **One run per review** — unique `idempotency_key`.

## Existing AI-Relevant Context
- The propagation audit tables are a **precise historical record of every score change** attributable
  to a content error — a clean training/eval signal for "which questions were materially wrong and
  how many students they affected".
- Because voids are **version-targeted**, the system already distinguishes "the student saw the wrong
  version" from "the student saw the corrected version" — the exact grounding an AI tutor needs to
  avoid explaining a version that was later invalidated.

## AI Opportunities
- **Blast-radius summarization** — turn the preview counts + sample into a plain-language impact
  brief for the admin before they confirm. *Recommended for AI.*
- **Anomaly detection** — flag material errors with unusually large blast radius (many schools /
  released exams) for extra human scrutiny. *Future.*
- **Post-propagation student explanation** — for a notified student, generate a grounded
  "this question was corrected and your score changed" explanation from the audit row + the frozen
  vs. corrected version. *Recommended for AI.*

## AI Risks
- **This engine changes official scores.** No AI may trigger or auto-confirm a propagation — confirm
  is an irreversible-feeling, human-only super-admin action.
- **Do not bypass version targeting.** An AI helper must respect `version_ids` as the source of
  truth; widening the set could void exams that used the *correct* version.
- **Do not re-notify.** Notification counts are audited (`notifications_sent`, `notified`); an AI
  feature must not re-announce score changes.

## Future Improvements (future)
- Propagation feature-test coverage (idempotency, version targeting, release/void interaction).
  *Not implemented.*
- AI impact brief on the preview screen. *Recommended for AI / future.*
- A super-admin dashboard aggregating propagation history across reviews. *Future.*
