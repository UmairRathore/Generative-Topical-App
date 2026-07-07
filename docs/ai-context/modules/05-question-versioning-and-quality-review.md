# 05 — Question Versioning & Quality Review

> Code-grounded reference. Verify against source before acting.

**Siblings:** [04 — Question Bank](./04-question-bank.md) ·
[06 — Question Flagging & Void Engine](./06-question-flagging-and-void-engine.md) ·
[07 — Quality Propagation Engine](./07-quality-propagation-engine.md) ·
[12 — Mistake Bank (Learning Hub)](./12-mistake-bank.md)

---

## Purpose

Two tightly coupled subsystems that give the Question Bank a **quality-controlled, append-only
history**:

1. **Versioning** — every content edit to a bank question writes an **immutable snapshot**
   (`v2_question_versions`) and advances the question's `current_version_id`. Each exam stamps the
   exact version it used (`v2_exam_questions.question_version_id`), so a take/result/paper view
   always renders **the question as the student actually saw it**, even after the bank question is
   later corrected.
2. **Quality Review** — every question under review has exactly **one open** `v2_quality_reviews`
   row; the teacher/student flags (module 06) are its *reports*. The Support Team decides an
   **outcome** (`correct` / `cosmetic` / `material`). Cosmetic/material corrections produce a new
   version; a **material** error is additionally marked `propagation_pending` for the Phase 3
   historical-propagation job (module 07).

This is the phased "Quality Review" system from project history:
- **Phase 1** — versioning + frozen exam render (this doc).
- **Phase 2** — the 3-outcome Support queue + review-per-question (this doc).
- **Phase 3** — material propagation → [module 07](./07-quality-propagation-engine.md).
- **Phase 4** — student/teacher/admin surfacing of outcomes (partly module 06's admin audit).

Status: **Implemented in code** (Phases 1–2).

## Users / Roles

- **Super Admin** owns the Quality Review queue and all version creation
  (`auth:v2_super_admin`, `routes/v2.php:109-131`).
- **Students** consume frozen versions (they never see the "current" version if their exam used an
  older one) — via `Exam::renderFrozenQuestions()`.
- **Teachers** feed the queue by flagging / sending-for-review (module 06) but do not decide outcomes.

## Current Implementation

### Version creation lives in the Question Bank editor
`app/Http/Controllers/V2/SuperAdmin/QuestionBankController.php`:
- `recordVersion(Question, Request)` (lines 647-672) — the **only** version writer. Captures a
  snapshot from a fresh DB read, `version_number = max+1`, links `quality_review_id` if the edit
  was opened from a review, sets `created_by`, then `UPDATE v2_questions.current_version_id`. If a
  review was in play it also stamps `v2_quality_reviews.resulting_version_id`.
- `update()` (lines 272-317) runs, in one transaction: content update → `syncOptions` →
  `syncImages` → `recordVersion()` → `finalizeReview()`.
- `finalizeReview(?reviewId, ?outcome, versionId, Question)` (lines 328-361) — applies a **Cosmetic
  / Material** decision on save: sets `outcome`, `status='decided'`, `reviewed_by`, `reviewed_at`,
  `resulting_version_id`, and `propagation_status = ('propagation_pending' if material else null)`;
  restores the question from `under_review` → `active`; closes attached flags to `resolved`.
- `versions()` (lines 220-238) — read-only history view.

### The "Correct" (dismissal) outcome lives in the flag queue
`app/Http/Controllers/V2/SuperAdmin/QuestionFlagController.php`:
- `index()` — the Quality Review queue, filterable `status=open|decided`; eager-loads the question
  (`withTrashed`), its reports (teacher/student/school), and `resultingVersion`.
- `markCorrect(Request, QualityReview, NotificationService)` (lines 72-99) — **outcome `correct`**:
  "our digital copy already matches Cambridge". Sets `outcome='correct'`, `status='decided'`,
  restores `under_review → active`, closes reports, notifies the reporting teacher. **No version,
  no propagation.** `abort_if($review->status === 'decided', 409)`.

> Decision routing: **Correct** is inline (`markCorrect`); **Cosmetic/Material** open the bank
> editor with `?quality_review_id` and are recorded on save in `QuestionBankController::update` →
> `finalizeReview`. (Documented in the `QuestionFlagController` header comment, lines 13-27.)

### Snapshot service
`app/Services/V2/QuestionSnapshot.php`:
- `capture(Question): array` — self-contained `{question, options, images}` built from **raw
  attributes** (so json/boolean/decimal casts round-trip). Field allow-lists: `QUESTION_FIELDS`
  (17 content fields, no status/timestamps/version pointer), `OPTION_FIELDS` (`label, text,
  has_image, sort_order`), `IMAGE_FIELDS` (13 fields incl. `image_path`, `bbox`, `caption`,
  `ocr_text`, `diagram_labels`).
- `hydrate(array, int $questionId): Question` — rebuilds a **non-persisted** `Question` (with
  `options`/`images` relations, original id, `exists = true`) purely for rendering. Never saved.

### Frozen exam render
`app/Models/V2/Exam.php::renderFrozenQuestions()` (lines 68-73) — swaps each exam-question's
`question` relation for `$eq->resolvedQuestion()`. `app/Models/V2/ExamQuestion.php::resolvedQuestion()`
(lines 33-40) returns `questionVersion->toRenderableQuestion()` (hydrated snapshot) or **falls back**
to the live `question` if no version was stamped. `QuestionVersion::toRenderableQuestion()` (lines
39-42) delegates to `QuestionSnapshot::hydrate`.

### Views
`resources/views/v2/super_admin/question_bank/versions.blade.php` (history + diff),
`resources/views/v2/super_admin/question_flags/index.blade.php` (the Quality Review queue).

### Tests
No dedicated versioning/QR feature test found under `tests/` (Not implemented — a coverage gap).

## Data Model

Ownership/tenancy: versions and quality reviews are **global** (no `school_id`) — they belong to the
global Question Bank. Only the *effects* (voided exam pivots, adjusted attempts) are school-scoped
(module 07).

### `v2_question_versions` — immutable snapshot
Migration `2026_06_25_000004`. Model `app/Models/V2/QuestionVersion.php`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `question_id` | FK → `v2_questions` (cascade) | |
| `version_number` | uint | unique with `question_id` |
| `snapshot` | json (cast `array`) | full `{question, options, images}` — the render source |
| `correct_answer` | char(1), nullable | denormalized copy of the frozen answer |
| `change_summary` | text, nullable | e.g. `Created.` / `Edited.` / admin-entered |
| `review_reason` | string(60), nullable | |
| `quality_review_id` | FK → `v2_quality_reviews` (nullOnDelete), nullable | the review that produced this version |
| `created_by` | uint, nullable | super-admin id (null for backfill) |

Unique `(question_id, version_number)`. **Never edited after creation** — corrections create a *new*
version and advance the pointer.

### `v2_quality_reviews` — one Support review per question
Migration `2026_06_25_000003` (+ `..._000007_add_propagation_status`). Model
`app/Models/V2/QualityReview.php`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `question_id` | FK → `v2_questions` (cascade) | |
| `status` | string(16), default `open` | `open` / `decided` |
| `outcome` | string(20), nullable | `correct` / `cosmetic` / `material` (null until decided). `OUTCOMES` const |
| `propagation_status` | string(24), nullable, indexed | null / `propagation_pending` / `propagated` (module 07) |
| `reason_category` | string(40), nullable | |
| `source_checked` | json (cast `array`), nullable | |
| `reviewed_by` | uint, nullable | super-admin id |
| `reviewed_at` | timestamp, nullable | |
| `resulting_version_id` | uint, nullable, indexed | plain column → the corrected version |

**Invariant:** exactly one `open` review per question at a time (enforced by `openFor`).
`QualityReview::openFor(int $questionId)` (lines 40-50) — `firstOrCreate(question_id, status:'open')`
then attaches all still-unresolved (`open`/`escalated`) flags with a null `quality_review_id` to it.
**Idempotent** — re-reporting never spawns duplicates. Called by the teacher send-for-review flow
(module 06) and the backfill migration. `question()` uses `withTrashed`. Relations: `resultingVersion`,
`reports` (`hasMany QuestionFlag`).

### Version pointers (added by `2026_06_25_000005_add_versioning_pointers`)
Three plain indexed columns (no hard FK): `v2_questions.current_version_id`,
`v2_exam_questions.question_version_id`, `v2_question_flags.quality_review_id`.

### `v2_exam_questions.question_version_id`
Base table `2026_06_15_000102`; `question_version_id` added by `..._000005`. Stamps the exact frozen
version each exam used. Backfill `2026_06_25_000006` created version #1 for every existing question
(incl. soft-deleted) and stamped every existing exam-question with it (chunked, idempotent).
Model `app/Models/V2/ExamQuestion.php`: relations `questionVersion` (→ `QuestionVersion`),
`question` (`withTrashed`); `resolvedQuestion()` prefers the frozen version.
(Void columns on this table are documented in [module 06](./06-question-flagging-and-void-engine.md).)

### Backfills
- `2026_06_25_000006_backfill_question_versions` — v1 for every question + stamp exam-questions.
- `2026_06_25_000008_backfill_open_quality_reviews` — one open review per question with
  `status='under_review'`, via `openFor` (idempotent).

## Core Flows

### Every edit versions the question
`QuestionBankController::update` (transaction): update fields → sync options/images →
`recordVersion()` (snapshot the fresh state, `version_number = max+1`, advance
`current_version_id`) → if opened from a review, `finalizeReview()`. New exams thereafter freeze the
new `current_version_id`; already-taken exams keep pointing at their old `question_version_id`.

### Exam renders the frozen version
Load `examQuestions.questionVersion` (+ `.question` fallback) → call
`Exam::renderFrozenQuestions()` → each exam-question's `question` relation becomes the hydrated
snapshot → the normal render partials show the historical content byte-for-byte (image files were
never deleted, so paths resolve).

### Quality Review lifecycle (Phase 2)
1. A question enters review: teacher `sendForReview` (module 06) sets it `under_review`, creates a
   teacher-level open flag, and calls `QualityReview::openFor()`. The one open review's `reports`
   are the attached flags.
2. Super admin opens the queue (`QuestionFlagController::index`) and decides:
   - **Correct** → `markCorrect` (inline): dismiss, restore to active, close reports. No version.
   - **Cosmetic / Material** → "Correct question" opens the bank editor (`?quality_review_id`).
     On save, `recordVersion()` creates the corrected version and `finalizeReview()` records the
     outcome + `resulting_version_id`, restores the question to active, closes reports. **Material**
     also sets `propagation_status='propagation_pending'` → eligible for [module 07](./07-quality-propagation-engine.md).

## Inputs
- **From module 06:** an open `QualityReview` with attached `QuestionFlag` reports; a question set
  to `under_review`.
- **From the admin:** the corrected content + `change_summary` + the `outcome` (cosmetic/material)
  or the inline `correct` decision.

## Outputs
- Immutable `v2_question_versions` rows; an advanced `current_version_id`.
- A `decided` `QualityReview` with `outcome` + `resulting_version_id`.
- For material outcomes: a `propagation_pending` review → the Phase 3 entry point (module 07).
- Frozen exam renders (student-accurate history).
- Teacher notification of the review decision (`NotificationService::notifyReviewDecision`).

## Dependencies
- `app/Services/V2/QuestionSnapshot.php` (capture/hydrate).
- The Question Bank editor (`QuestionBankController`, module 04) — sole version writer.
- Module 06 (flagging/void) — feeds the queue and sets `under_review`.
- Module 07 (propagation) — consumes `propagation_pending` material reviews.
- `NotificationService`, `AuditLogger` (`quality_review.{correct,cosmetic,material}` events).

## Security / Access Rules
1. **Super-admin-only** decisions and version writes (`auth:v2_super_admin`).
2. **Immutability** — versions are never updated in place; corrections append.
3. **One open review per question** — `openFor` is the guardrail; `markCorrect` 409s a
   re-decision of an already-`decided` review.
4. **File retention** — image files are never deleted so old snapshots always render.
5. **Frozen truth** — students see the version their exam captured, not the latest bank content.

## Existing AI-Relevant Context

**Versioning is what makes a grounded AI tutor safe.** `question_version_id` on the exam pivot is a
**stable canonical reference to exactly what the student saw** — the tutor can hydrate the frozen
`snapshot` (via `QuestionVersion::toRenderableQuestion()` / `QuestionSnapshot::hydrate`) and reason
about the *actual* stem/options/diagram/answer, immune to later bank corrections. Without this, a
tutor grounding on the "current" bank question could explain a version the student never saw.

- The snapshot carries `ocr_text`, `caption`, `diagram_labels`, `bbox` per image — structured
  grounding for figures.
- `change_summary` + `review_reason` + the `QualityReview.outcome` describe *why* a question changed
  — a signal the tutor could use to warn "this question was later corrected".
- The `QualityReview` reports history is a curated human-quality signal on which questions are
  trustworthy.

## AI Opportunities
- **AI triage of the Quality Review queue** — cluster/prioritize open reviews, draft a suggested
  outcome + `change_summary` for a human to confirm (see [module 06](./06-question-flagging-and-void-engine.md)).
  *Recommended for AI.*
- **Cosmetic vs. material classifier** — pre-suggest whether a correction changes the answer
  (material) or only presentation (cosmetic), to route propagation. *Recommended for AI.*
- **Version-diff summarizer** — generate a human `change_summary` from the snapshot diff. *Future.*

## AI Risks
- **Ground on the version, not the live question.** An AI tutor must hydrate the exam's
  `question_version_id` snapshot; using the current bank content can misexplain a corrected question.
- **Never auto-decide a review.** Outcome (esp. `material`, which triggers score-changing
  propagation in module 07) must stay a human decision; AI proposes, a super admin commits.
- **Do not write versions.** `recordVersion` is the single writer; an AI must not create snapshots
  or advance `current_version_id` out-of-band.

## Future Improvements (future)
- Feature-test coverage for versioning + the QR queue. *Not implemented.*
- AI-assisted queue triage + outcome suggestion. *Recommended for AI / future.*
- Surfacing version history to teachers/students beyond the frozen render. *Future.*
