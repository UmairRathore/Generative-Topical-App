# 12 — Mistake Bank (Learning Hub)

> Code-grounded reference. Verify against source before acting.

**Siblings:** [13 — Learning Hub / Worked Solutions / Assets](./13-learning-hub-worked-solutions-assets.md) ·
[14 — Interactive Widgets](./14-interactive-widgets.md) ·
[15 — Notes Module](./15-notes-module.md)

---

## Purpose

The Mistake Bank ("My Mistakes") is the student-facing revision spine of the V2 Learning Hub.
Every wrong, non-voided answer a student gives becomes **one persistent row** in
`v2_student_mistakes`, grouped by subject → topic, filterable, and tracked through a mastery
lifecycle. It is the **prime AI-tutor context surface**: a single mistake row plus its question
exposes exactly what a data-grounded tutor needs (stem, options, correct answer, the student's own
wrong answer, topic/subtopic, how many times they got it wrong, mastery state).

Status: **Implemented in code** (Phase 1). No AI runs here — the service comment is explicit:
"No AI here." (`app/Services/V2/MistakeBankService.php:18`).

## Users / Roles

- **Students only.** All routes are under the `v2_student` guard
  (`app/Http/Controllers/V2/Student/LearningHubController.php:24`,
  `routes/v2.php:351-355`).
- A student may only ever touch **their own** mistakes — `authorizeMistake()` aborts 403 if
  `mistake->student_id !== student->id` (`LearningHubController.php:210-213`).
- **School isolation** is enforced by a global scope on the model
  (`StudentMistake::booted()`, `app/Models/V2/StudentMistake.php:56-67`) mirroring Exam / ExamAttempt.

## Current Implementation

- Capture is hooked **after** exam submission commits, wrapped so a failure can never break the
  submission (`app/Services/V2/ExamService.php:219-225` → `MistakeBankService::syncFromAttempt`).
- `v2:backfill-mistakes` command seeds the bank from pre-existing submitted attempts
  (`app/Console/Commands/V2/BackfillMistakes.php`) — idempotent, chunked, per-attempt try/catch.
- Coverage: `tests/Feature/V2/LearningHubMistakeBankTest.php` (10 tests: capture, correct-skip,
  idempotency, repeat-increment, voided-exclusion, release gating, mastery/reopen, asset visibility).

## Data Model

### `v2_student_mistakes` — one row per (student, question)
Migration: `database/migrations/2026_07_02_000001_create_v2_student_mistakes_table.php`.
Model: `app/Models/V2/StudentMistake.php`.

`unique(student_id, question_id)` guarantees the one-row invariant. Rows are **never deleted** —
mastered/archived stay for history. Subject/topic/subtopic/difficulty/year/source_paper are
**denormalized at capture** so the grouped list and analytics avoid joins.

Load-bearing columns:

| Column | Meaning |
|--------|---------|
| `student_id`, `school_id`, `question_id` | identity (all FK, cascade) |
| `subject_id`, `topic_id`, `subtopic_id`, `difficulty`, `year`, `source_paper` | denormalized curriculum context |
| `first_wrong_attempt_id`, `latest_wrong_attempt_id`, `latest_exam_id` | provenance |
| `selected_option` (char 1), `correct_option` (char 1) | the student's wrong answer + the key, from the **latest** wrong attempt |
| `mistake_count` (default 1) | incremented on each new wrong attempt (never a duplicate row) |
| `first_wrong_at`, `last_wrong_at` | timestamps |
| `status` (default `new`) | mastery lifecycle (below) |
| `review_count` (default 0), `last_reviewed_at` | review tracking |
| `confidence` (tinyint 0–100, nullable) | reserved; cleared on reopen. **No writer wires it up in Phase 1** (Partially implemented) |
| `resolved_at`, `archived_at`, `mastered_at` | resolution timestamps |

**Status values** (`StudentMistake` constants, lines 21–28):
`new` → `reviewed` → `practiced` → `mastered` → `archived`.
`RESOLVED_STATUSES = [mastered, archived]` (what "Hide Completed" filters out).
Note: `practiced` is defined as a constant (`STATUS_PRACTICED`) but **no code path sets it** in
Phase 1 — it is reserved for a future practice mode (Partially implemented).

### `v2_student_mistake_events` — append-only history
Migration: `database/migrations/2026_07_02_000002_create_v2_student_mistake_events_table.php`.
Model: `app/Models/V2/StudentMistakeEvent.php`.

One row per meaningful event, keeping the parent mistake a rolled-up record.

`event_type` values (model constants, lines 13–18):
`wrong` · `reviewed` · `asset_viewed` · `marked_mastered` · `reopened` · `confidence_updated`.
(`confidence_updated` is a defined constant with **no emitter** in Phase 1.)

Columns: `student_mistake_id`, `student_id`, `question_id`, `attempt_id`, `exam_id`,
`event_type`, `selected_option`, `correct_option`, `meta_json` (JSON, cast to array), `occurred_at`.
Index `(student_mistake_id, attempt_id)` backs the idempotency check.
`asset_viewed` stores `{meta_json: {asset_type}}` (`MistakeBankService::recordAssetViewed`,
service line 323-326).

## Core Flows

### Capture (`syncFromAttempt` → `recordWrong`)
`MistakeBankService.php:29-131`.
1. Only runs for `status === 'submitted'` attempts.
2. Raw query (no global scopes) joins `v2_exam_answers` × `v2_exam_questions` × `v2_questions`,
   selecting answers where `is_correct = false` **and** `eq.is_voided = false` (voided questions
   are excluded — see [module 13/QR](./13-learning-hub-worked-solutions-assets.md) and the void engine).
3. For each wrong row, `recordWrong` (wrapped in a DB transaction):
   - **New** (question, student): insert with `status = new`, `mistake_count = 1`.
   - **Existing**: idempotency check — if a `wrong` event already exists for this
     `(student_mistake_id, attempt_id)`, return `false` (no double count). Otherwise increment
     `mistake_count`, refresh `last_wrong_at` / `latest_wrong_attempt_id` / `latest_exam_id` /
     `selected_option` / `correct_option`, and append a `wrong` event.
   - **Reopen**: if the existing row was in `RESOLVED_STATUSES`, reset to `new`, null out
     `resolved_at`/`archived_at`/`mastered_at`/`confidence`, and append a `reopened` event.

### Listing (release-gated)
`list()` → `releasedQuery()` (service lines 141-197).
**Release gate:** a mistake only surfaces when its `latest_exam_id` exam has a non-null
`results_released_at` (`releasedQuery`, lines 157-164) — the capture happens immediately, but the
row stays hidden until the teacher releases results. Verified by
`test_mistakes_are_hidden_until_the_exam_results_are_released`.

- **Filters** (`applyFilters`): `all` / `needs_review` / `most_repeated` (≥2) / `never_reviewed`
  (`review_count = 0`) / `mastered`; plus subject/topic/difficulty; plus `hide_completed`.
- **Sorts** (`applySort`): `grouped` (default: subject→topic→newest), `recent`, `oldest`,
  `most_mistakes`, `difficulty`.
- `subjectOptions()` / `topicOptions()` power the subject-dependent topic dropdown (only subjects/
  topics that actually have released mistakes).

### Analytics
`analytics()` (lines 229-264) returns `total`, `unresolved`, `mastered`, **`pending`**
(captured-but-not-yet-released = `all mistakes − released total`), `top_topics` (top 5 weak topics
by `SUM(mistake_count)`), and `most_repeated` (single worst question).

### Transitions
- `markReviewed` — bumps `review_count`, `new → reviewed`, event `reviewed`. Called automatically
  when opening the `review` **or** `studio` page (controller lines 73, 112).
- `markMastered` — `status = mastered`, sets `mastered_at` + `resolved_at`, event `marked_mastered`.
- `reopen` — back to `new`, clears resolution timestamps, event `reopened`.
- `archive` — `status = archived`, sets `archived_at` + `resolved_at` (no event emitted).

## Inputs

- **From exams:** `ExamAttempt` (submitted) → `syncFromAttempt`. Reads `v2_exam_answers`,
  `v2_exam_questions.is_voided`, `v2_questions` metadata.
- **From the student (UI):** filter/sort query params on the index; `PATCH …/status`
  with `action ∈ {mastered, reset, archive}` (`updateStatus`, controller lines 168-187).

## Outputs

- **Index** (`index`): Blade view `v2.student.learning_hub.index` with paginated mistakes,
  analytics, subject/topic options.
- **Review** (`review`): Blade `v2.student.learning_hub.review` — reuses the shared
  `answer_review` partial against the **real source exam** narrowed to this question
  (real `ExamQuestion` + `ExamAnswer`, frozen version via `renderFrozenQuestions()`), plus the
  `worked_solution` asset. Aborts 404 unless the exam's results are released.
- **Studio** (`studio`): Inertia `Solution` page — see [module 13](./13-learning-hub-worked-solutions-assets.md).
- **Asset** (`asset`): JSON endpoint returning a **display-only** stored asset (never generates);
  gated on `DISPLAYABLE_TYPES` + release.

## Dependencies

- `App\Services\V2\ExamService` (capture hook) and the void engine (`is_voided`).
- `App\Models\V2\QuestionLearningAsset` + `MistakeBankService::visibleAsset()` — the **only** gate
  through which a student reaches learning content (see module 13).
- `App\Support\SignedImage` / `SecureImageController` — question diagram figures in the studio.
- Notes import bridge is anchored to a released mistake (see module 15).

## Security / Access Rules

1. **Ownership** — `authorizeMistake()` 403s on foreign mistakes.
2. **School isolation** — global scope on `StudentMistake` (portal guards only).
3. **Release gate** — `review`/`studio`/`asset` abort 404 unless `latestExam->resultsReleased()`;
   `list`/`analytics` filter via `releasedQuery`. A student cannot see a mistake for an exam whose
   results are not yet released.
4. **Capture isolation** — capture runs post-commit in a try/catch that only logs on failure
   (`ExamService.php:223-224`), so it can never break a submission.

## Existing AI-Relevant Context

**This is the single most important data shape for the planned Student AI Tutor V1**
(`docs/ai-context/NEXT_SESSION_AI_TUTOR_PROMPT.md`). One `StudentMistake` row + its `question`
relation exposes, with no extra queries beyond eager loads already present in `studio()`:

- `question_stem` (`Question::question_text`), `correct_answer`, the student's `selected_option`,
  `source_paper`, question `images` (diagram references, signed on demand).
- Curriculum: `subject` (name), `topic` (title), `subtopic` (title) — denormalized, join-free.
- Behavioural signal: `mistake_count`, `review_count`, `status` (mastery), `first_wrong_at` /
  `last_wrong_at`, and the full `events` history (every wrong/reviewed/asset_viewed).
- Trusted content for grounding: the question's approved `QuestionLearningAsset`s
  (worked_solution, option_explanation, etc.) via `visibleAsset()` — see module 13.

The AI-tutor spec's required context payload (stem, diagram reference, options, correct answer,
selected answer, worked solution, option explanations, topic/subtopic, mistake count, mastery
status) **maps 1:1** onto a mistake row + `studio()`'s existing loads.

## AI Opportunities

- **Grounded per-mistake tutor** — answer "why is C right and my B wrong?" using ONLY the mistake
  row + the question's approved assets (never free-generate physics). Save the answer to Notes
  (module 15). *Recommended for AI.*
- **Misconception clustering** — the `selected_option` distribution across students on the same
  question is a distractor-taxonomy signal (cohort analytics; out of student scope). *Future.*
- **Adaptive next-question** — pick a similar unmastered question from the same subtopic. *Future.*

## AI Risks

- **Never expose other students' data.** The tutor must inherit the ownership + school + release
  gates; a mistake row is per-student. Any cohort/analytics feature must not leak a student's
  answers to peers.
- **Release gate must hold.** Do not let an AI feature read a mistake whose exam results are
  unreleased — bypasses the teacher's release decision.
- **Do not invent marks/topics/answers.** The `correct_option` in the row is authoritative; the AI
  must not contradict it or invent syllabus facts (per `NEXT_SESSION_AI_TUTOR_PROMPT.md` safety).
- **Do not write official records.** Mistake status transitions are student-initiated; AI must not
  silently mark things mastered.

## Future Improvements

- Wire `confidence` + `confidence_updated` events (fields/constants exist, no writer). *Not implemented.*
- Implement the `practiced` status via an active-recall / practice mode. *Not implemented (Phase 2 per memory).*
- Teacher/admin views of the Mistake Bank. *Not implemented.*
- AI tutor grounded in the mistake context. *Recommended for AI / future.*
