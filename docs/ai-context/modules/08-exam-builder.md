# 08 — Exam Builder

> Code-grounded reference. Verify against source before acting.

**Siblings:** [09 — Exam Attempts / Checking / Results](./09-exam-attempts-checking-results.md) ·
[10 — Analytics & Statistics](./10-analytics-and-statistics.md) ·
[11 — Reports (current system)](./11-reports-current-system.md) ·
[12 — Mistake Bank](./12-mistake-bank.md)

---

## Purpose

The Exam Builder lets a **teacher** assemble a multiple-choice test ("exam" / "test") for one of
their classes, drawn from the V2 question bank, and then **release** it to the enrolled students
inside a controlled time window. Two build modes exist:

- **Random** — the teacher picks a class, optional topic(s) and a question count; the service draws
  random questions from the class subject (`ExamService::generate()`).
- **Custom** — the teacher browses/hand-picks specific questions (`ExamService::createFromQuestions()`).

Both modes **freeze** the exact question content at build time (see *Question versioning*), so an
exam always renders what the student saw even after the bank question is later corrected.

Status: **Implemented in code.**

## Users / Roles

- **Teachers only** build and release exams. Every builder route is under the `auth:v2_teacher`
  guard (`routes/v2.php:277`, `288-306`).
- A teacher may only act on an exam they **created**, or one assigned to a class they teach —
  enforced per-action (`ExamController::show()` `abort_unless created_by === teacher->id || teacher
  teaches class`, `ExamController.php:406-410`; `release()`/`releaseResults()` require
  `created_by === teacher->id`, lines 324, 381).
- Students, school/branch/super admins **do not build** exams. Admins can only *view* the resulting
  papers (see [module 10](./10-analytics-and-statistics.md)).

> **Note (verified):** `app/Livewire/Teacher/TestGenerator.php` and
> `app/Livewire/Teacher/QuestionPicker.php` exist but are **V1** components — they import
> `App\Models\Question` / `App\Models\Subject` / `App\Models\Topic` (the un-namespaced V1 models),
> not the `App\Models\V2\*` models. **They are NOT part of the V2 Exam Builder.** The V2 builder is
> entirely **controller + Blade + Alpine**, driven by `ExamController` and `ExamService`; there is
> no Livewire in the V2 exam-build path. Do not wire V2 work into those V1 components.

## Current Implementation

### Routes (`routes/v2.php`, teacher group)
| Method | URI (under `/v2/teacher`) | Action | Name |
|---|---|---|---|
| GET | `exams` | `index` | `v2.teacher.exams.index` |
| GET | `exams/create` | `create` (random form) | `exams.create` |
| GET | `exams/custom` | `custom` (browse/pick) | `exams.custom` |
| GET | `exams/custom/selected` | `selectedCards` (drawer render) | `exams.custom_selected` |
| POST | `exams/custom` | `storeCustom` | `exams.store_custom` |
| GET | `exams/generate/preview` | `generatePreview` | `exams.generate_preview` |
| GET | `exams/generate/swap` | `swapPreview` | `exams.generate_swap` |
| GET | `exams/generate/regenerate` | `regeneratePreview` | `exams.generate_regenerate` |
| POST | `exams` | `store` (random) | `exams.store` |
| PATCH | `exams/{exam}/release` | `release` | `exams.release` |
| PATCH | `exams/{exam}/release-results` | `releaseResults` | `exams.release_results` |
| GET | `exams/{exam}` | `show` (manage/preview) | `exams.show` |

(Flagging/void actions `dismiss`, `review`, `student_paper` live on the same controller but belong to
[module 09](./09-exam-attempts-checking-results.md) — checking/results.)

### Controller
`app/Http/Controllers/V2/Teacher/ExamController.php`
- `index()` — lists the teacher's exams with `submitted_count`, `flagged_count`, `avg_score`.
- `create()` — random-mode form; only offers topics that have a drawable question
  (`whereHas('questions', active + has options)`, lines 52-55) so a topic can't yield nothing.
- `store()` — validates (`question_count` 1..40, `duration_minutes` 1..240 nullable), scopes
  chosen topics to the class subject via `validTopicIds()`, then `ExamService::generate()`.
- `custom()` / `storeCustom()` — hand-pick flow; `question_ids` is a comma-string of ids.
- `generatePreview()` / `swapPreview()` / `regeneratePreview()` / `selectedCards()` — AJAX endpoints
  returning `{ ids, html }` so the teacher previews/swaps the actual question cards before committing
  (rendered via `_selected_cards.blade.php`).
- `release()` / `releaseResults()` — lifecycle transitions (see *Core Flows*).
- `show()` — manage page: eager-loads frozen questions, enrolled students, attempts, per-topic
  stats, and the student-flag/void/escalation summary.

### Service
`app/Services/V2/ExamService.php`
- `generate(Teacher, data)` — random build. Resolves `topic_ids[]` (or legacy single `topic_id`),
  calls `drawRandomIds()`, `shuffle($ids)`, then `freeze()`. (Lines 30-52.)
- `drawRandomIds(subjectId, topicIds, count, exclude, yearFrom, yearTo)` — the pool query:
  `active()` + `has('options')`, scoped to subject/topics/year, excluding given ids.
  **Prefers answerable questions** (`whereNotNull('correct_answer')`) so auto-marking works, then
  fills the remainder from `whereNull('correct_answer')` if short. (Lines 64-87.)
- `createFromQuestions(Teacher, data, questionIds)` — custom build. Keeps only ids that are
  `active` + `has options` + belong to the class subject, preserves selection order, **caps at 40**,
  shuffles, then `freeze()`. (Lines 94-113.)
- `freeze(Teacher, class, data, ids, topicId)` — the shared writer, inside a `DB::transaction`.
  Creates the `v2_exams` row as **`status = 'draft'`**, `question_count = total_marks = count(ids)`,
  `published_at = now()`, then inserts `v2_exam_questions` rows. **Stamps
  `question_version_id`** from `v2_questions.current_version_id` per question so the exam is frozen to
  the exact content version. `marks` is hard-coded to 1 per row. (Lines 116-159.)

### Models
- `app/Models/V2/Exam.php` — table `v2_exams`. **Global `school` scope** filters
  `v2_exams.school_id` to the authed guard's school for `v2_school_admin` / `v2_teacher` /
  `v2_student` (booted, lines 39-49). Lifecycle helpers: `isReleased()`, `isDraft()`,
  `isScheduled()`, `isExpired()`, `isLive()`, `resultsReleased()`, `effectiveStatus()`
  (draft|scheduled|live|expired). Scopes `published()` (`status='released'`) and `available()`
  (released + inside window). `renderFrozenQuestions()` swaps each exam-question's `question`
  relation for `resolvedQuestion()` so views show the historical version (lines 68-73).
- `app/Models/V2/ExamQuestion.php` — table `v2_exam_questions`. `question()` uses `withTrashed()` so
  a frozen exam still renders even if the bank question was soft-deleted (line 23). `questionVersion()`
  → the frozen `QuestionVersion`. `resolvedQuestion()` renders from the version snapshot, falling
  back to the live question if no version was stamped (lines 33-40).

### Migrations
- `2026_06_15_000101_create_v2_exams_table.php` — base table (note: original default `status` was
  `'published'`; superseded below).
- `2026_06_15_000102_create_v2_exam_questions_table.php` — pivot; `unique(exam_id, question_id)`.
- `2026_06_19_000001_add_release_window_to_v2_exams.php` — adds `released_at`, `available_from`,
  `available_until`; **converts old `published` exams to `released` + open-ended**.
- `2026_06_19_000002_add_results_released_to_v2_exams.php` — adds `results_released_at`.
- `2026_06_25_000005_add_versioning_pointers.php` — adds `v2_questions.current_version_id` and
  `v2_exam_questions.question_version_id` (nullable, indexed, **no hard FK**).
- `2026_06_25_000002_add_question_voiding_to_exams.php` / `..._000009_add_void_source...` — void
  columns on the pivot (owned by [module 09](./09-exam-attempts-checking-results.md)).

### Views
`resources/views/v2/teacher/exams/` — `index`, `create` (random), `custom` (pick),
`show` (manage/preview), `student_paper`, and partial `_selected_cards.blade.php`.

### Commands / Jobs / Tests
- **Commands / Jobs:** none dedicated to *building* an exam. (A `v2:backfill-question-versions`-style
  backfill exists for versioning — see the versioning memory note — but exam build itself is synchronous.)
- **Tests:** **None.** There are no automated tests covering exam generation, freezing, release, or
  the builder controller (verified: the only V2 Feature tests are `tests/Feature/V2/LearningHubMistakeBankTest.php`
  and `LearningHubNotesTest.php`). This is a coverage gap — see *AI Opportunities / Future Improvements*.

## Data Model

### `v2_exams`
| Column | Type | Meaning |
|---|---|---|
| `school_id`, `class_id`, `subject_id` | FK | ownership + scope; `subject_id` fixed by the class |
| `topic_id` | FK nullable | the single topic, or **NULL = mixed** (drives the single-vs-mixed split) |
| `created_by` | FK teacher | the builder |
| `title` | string | teacher-chosen name |
| `question_count` | tinyint | 1..40; = number of frozen questions |
| `total_marks` | smallint | set = `question_count` at freeze (1 mark/question) |
| `duration_minutes` | smallint nullable | timer / auto-close window length |
| `year_from`, `year_to` | smallint nullable | random-generation year filter |
| `shuffle` | bool | default true (option-order intent) |
| `status` | string(16) | **`draft`** at build → **`released`** on release |
| `published_at` | ts | set at freeze (`now()`) |
| `released_at` | ts nullable | when released |
| `available_from` / `available_until` | ts nullable | the take window (release → +duration) |
| `results_released_at` | ts nullable | null = results hidden; set = students may see score/answers |

### `v2_exam_questions` (the frozen paper — single source of truth for scoring)
| Column | Type | Meaning |
|---|---|---|
| `exam_id`, `question_id` | FK | `unique(exam_id, question_id)` |
| `question_version_id` | bigint nullable | frozen content version (`current_version_id` at build) |
| `sort_order` | tinyint | display order (1-based) |
| `marks` | tinyint | hard-coded 1 |
| `is_voided`, `void_reason`, `voided_by`, `voided_at`, `void_source`, `void_quality_review_id` | — | voiding engine — see [module 09](./09-exam-attempts-checking-results.md) |

## Core Flows

**Random build:** teacher opens `create` → optional live preview (`generatePreview` draws ids,
renders cards; `swapPreview` replaces one, `regeneratePreview` re-rolls the un-ticked ones) → `store`
→ `generate()` → `drawRandomIds()` → `shuffle` → `freeze()` (draft). Redirect to `show`.

**Custom build:** teacher opens `custom` → filters + ticks questions (ids tracked client-side, drawer
rendered by `selectedCards`) → `storeCustom` → `createFromQuestions()` (validate/cap/shuffle) →
`freeze()` (draft). Redirect to `show`.

**Release (`release()`):** teacher chooses WHEN it opens — `now` / `in_5` / `in_10` / `schedule`
(a `release_at` datetime) — and a `duration_minutes` (1..240). The window is
`available_from = chosen open time`, `available_until = available_from + duration` (auto-close; there
is no manual expiry). **Brand guard:** if any frozen question is no longer `active` (e.g. flagged/
pulled after build), release is **blocked** with an error — the teacher must regenerate/rebuild
(lines 347-352). On success: `status='released'`, notifies the class
(`NotificationService::announceExamRelease`), audit `exam.released`.

**Release results (`releaseResults()`):** separate step. Allowed **only once the exam has closed**
(`isExpired()`), else 422 (re-hide is allowed any time). Sets/clears `results_released_at`; on first
release notifies students (`announceResults`). See [module 09](./09-exam-attempts-checking-results.md)
for what students then see.

## Inputs
- Random: `class_id`, `topic_ids[]` (scoped to class subject), `question_count` (1..40), `title`,
  `duration_minutes?`. Year filters (`year_from/to`) are supported by the service but the
  `store()` validator does not currently accept them (only preview/generate paths pass them through).
- Custom: `class_id`, `title`, `duration_minutes?`, `question_ids` (comma string).
- Release: `open`, `release_at?`, `duration_minutes`. Release-results: `release` (bool).

## Outputs
- One `v2_exams` row (draft) + N `v2_exam_questions` rows (frozen, version-stamped).
- On release: window timestamps + student notification.
- `show()` returns the frozen paper, enrolled-student roster, attempts, `topicStatsForExam()`, class
  `avg`, `submittedCount`, and the flag/void/escalation summary for the manage UI.

## Dependencies
- **Question bank** (`App\Models\V2\Question`): pool source; `active()` scope + `has('options')` +
  `correct_answer` presence gate what can be drawn/marked.
- **Question versioning** (`QuestionVersion`, `current_version_id`) — freeze target.
- `AuditLogger` (`exam.created`, `exam.created_custom`, `exam.released`, `exam.results_*`).
- `NotificationService` (release + results announcements).
- The class → subject binding and `StudentEnrollment` (roster on `show`).

## Security / Access Rules
- `auth:v2_teacher` on every route; `v2.must_change_password` gate on the group.
- Model **global `school` scope** guarantees cross-school isolation on every `Exam` query.
- Per-action ownership checks: build/preview endpoints validate `class_id ∈ teacher's classes`
  (`Rule::in($classIds)`); `release`/`releaseResults` require `created_by === teacher->id`; `show`
  allows creator OR a teacher of the class.
- Topic inputs are re-scoped to the class subject (`validTopicIds()`), preventing out-of-scope topics.
- Custom-pick ids are re-validated against `active + has options + subject` server-side — the client
  list is never trusted.

## Existing AI-Relevant Context
- **No AI in the build path.** Generation is pure random/hand-pick SQL; there is no
  difficulty-balancing, no LLM question selection, no adaptive sequencing.
- The freeze/versioning design means every exam has a **stable, reproducible content snapshot** —
  useful ground truth for any future AI that reasons over "what exactly was asked."

## AI Opportunities *(Recommended for AI — not implemented)*
- **Blueprint-driven generation:** an AI could balance a paper by difficulty band, subtopic coverage,
  and past class weakness (data already exists via `ExamService::classTopicStats` /
  `classStudentMatrix`, [module 10](./10-analytics-and-statistics.md)) instead of uniform random draw.
- **Adaptive / remedial exams:** build a test targeting a class's weakest topics/subtopics, or a
  student's Mistake Bank ([module 12](./12-mistake-bank.md)).
- **Distractor-aware selection:** prefer questions whose wrong options probe known misconceptions.
- **Auto-title / description** from the chosen topic mix.

## AI Risks
- **Frozen-content contract:** any AI that mutates question content must respect versioning — an exam
  must keep rendering the version it froze. Never rewrite in place without minting a new version.
- **Answerability gate:** AI-selected questions must have a `correct_answer` or auto-marking silently
  scores them wrong/unmarkable (`drawRandomIds` deliberately prefers answerable ones).
- **Scope leakage:** any AI helper querying the pool must respect the model `school` global scope and
  the subject/topic constraints, or it can surface cross-school / out-of-syllabus questions.
- **Determinism for grading:** generation may be AI-driven, but the *paper, once frozen, must stay
  deterministic*; marking is a pure key comparison ([module 09](./09-exam-attempts-checking-results.md)).

## Future Improvements
- Add automated tests for freeze/version-stamping, the 40-cap, the answerable-preference fill, and the
  release brand-guard — currently untested.
- `store()` does not expose `year_from/to` though the service supports it — wire it through or drop it.
- Per-question `marks` is always 1; weighted marking would need schema use beyond the current default.
