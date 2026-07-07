# 04 — Question Bank

> Code-grounded reference. Verify against source before acting.

**Siblings:** [05 — Question Versioning & Quality Review](./05-question-versioning-and-quality-review.md) ·
[06 — Question Flagging & Void Engine](./06-question-flagging-and-void-engine.md) ·
[07 — Quality Propagation Engine](./07-quality-propagation-engine.md) ·
[12 — Mistake Bank (Learning Hub)](./12-mistake-bank.md)

---

## Purpose

The Question Bank is the **global, canonical pool of V2 MCQ questions** — the imported Cambridge
past-paper corpus plus manually authored questions — from which exams are generated and against
which every learning surface (Mistake Bank, worked solutions, AI tutor) is grounded. A question is
the atomic unit of curriculum content: a stem (text + diagrams), A–D options, a single correct
answer, and a topic/subtopic tag.

The bank is **immutable by policy** (per project memory `question-bank-immutable.md`): questions are
never hard-deleted (soft deletes only), image files are never unlinked, and every content edit
produces an immutable version snapshot (see [module 05](./05-question-versioning-and-quality-review.md)).

Status: **Implemented in code.** Import + deterministic tagging + full super-admin CRUD are all live.

## Users / Roles

- **Super Admin only** for bank CRUD. All routes are under the `auth:v2_super_admin` guard
  (`routes/v2.php:109-118`), controller `app/Http/Controllers/V2/SuperAdmin/QuestionBankController.php`.
- **Teachers** read the bank indirectly: exam generation draws from `status = 'active'` questions
  (`Question::scopeActive`, `app/Models/V2/Question.php:124-127`) and teachers may **flag** a bank
  question (see [module 06](./06-question-flagging-and-void-engine.md)).
- **Students** never touch the bank directly; they see the **frozen version** an exam captured
  (see [module 05](./05-question-versioning-and-quality-review.md)).
- The Livewire `app/Livewire/Admin/*` components (`QuestionBrowser`, `QuestionForm`,
  `QuestionReview`, `PaperIndex`, `PaperForm`, `PaperQuestions`, `ImportSummary`) are the **V1
  legacy** admin over different tables (`questions`, `papers`) — do NOT confuse them with the V2
  bank. Per project memory (`feedback-v2-only.md`), all new work is V2; V1 is untouched.

## Current Implementation

### Controller
`app/Http/Controllers/V2/SuperAdmin/QuestionBankController.php` — public methods:

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET question-bank` | Filtered/paginated list (table or gallery). Filters: level, subject, year, session, variant, topic, layout, answer, image-content, status, search `q`. Special statuses: `trashed` (`onlyTrashed`), `untagged` (`whereNull('topic_id')`). |
| `create` | `GET question-bank/create` | New-question form (`new Question(['marks'=>1,'status'=>'active'])`). |
| `store` | `POST question-bank` | Create question (+ options + images + first version). Attaches to a per-subject `CUSTOM_{code}.virtual` paper via `customPaperFor()`. |
| `edit` | `GET question-bank/{question}/edit` | Edit form; passes through `?quality_review_id` when opened from the Quality Review queue. |
| `versions` | `GET question-bank/{question}/versions` | Read-only immutable version history + diff vs current. |
| `update` | `PUT question-bank/{question}` | Save edit → sync options/images → `recordVersion()` → `finalizeReview()` if from a review. |
| `destroy` | `DELETE question-bank/{question}` | **Soft delete only** (Trash). Guarded: only `draft`/`under_review`/`archived` (`DELETABLE`) — active questions cannot be trashed. |
| `restore` | `PATCH question-bank/{question}/restore` | Restore from Trash (resolves hashid manually — route-model binding skips soft-deleted). |
| `setStatus` | `PATCH question-bank/{question}/status` | Change status (validated against `STATUSES`). JSON or redirect. |

Class constants (lines 44-57): `STATUSES = ['active','draft','under_review','archived']`,
`DELETABLE = ['draft','under_review','archived']`, `DIFFICULTIES = ['easy','medium','hard']`,
`OPTION_LABELS = ['A','B','C','D']`, `STEM_ROLES = ['question_image_between_text','question_image_after_text']`,
`ANSWER_TYPES = ['table','graph']`.

Private helpers of note: `recordVersion()` (creates the immutable version — see
[module 05](./05-question-versioning-and-quality-review.md)), `finalizeReview()` (closes a quality
review on save — see module 05), `syncOptions()` (delete+reinsert A–D), `syncImages()`
(re-derives `layout_type` from image presence), `customPaperFor()`.

### Models (`app/Models/V2/`)
- `Question.php` — `$table = 'v2_questions'`, uses `SoftDeletes` + `HasHashid`. Relations:
  `paper`, `subject`, `topic`, `subtopic`, `options` (ordered by `sort_order`), `images`
  (ordered by `sort_order`), `flags`, `openFlags` (`status='open'`), `learningAssets`,
  `versions` (newest first), `currentVersion` (`current_version_id`). Scopes: `answerable`
  (`whereNotNull('correct_answer')`), `active` (`status='active'`). Render helpers: `stemBlocks()`
  (reconstructs visual order of text + diagrams using bbox positions), `optionTableImage()`,
  `collapsedOptionFigures()`.
- `QuestionOption.php` — `$fillable`: `question_id, label, text, has_image, sort_order`.
- `QuestionImage.php` — `$fillable`: `question_id, external_id, image_path, role, option_label,
  page, bbox, width, height, caption, ocr_text, confidence, diagram_labels, sort_order`.
  `displayWidth()` clamps a CSS px width from the crop's real pixel width.
- `Topic.php` — `subject`, `subtopics` (ordered), `questions`. Keyed by `external_id` (syllabus id).
- `Subtopic.php` — `topic`, `questions`. Keyed by `external_id`.
- `Paper.php` — `subject`, `questions` (ordered by `question_number`).
- `Subject.php` — `schoolSubjects`, scope `active` (`is_active`).

### Import & tagging pipeline (deterministic — NO LLM)
See [18 — Import & Data Pipeline](./18-import-and-data-pipeline.md) for the full flow. Bank-relevant pieces:
- `app/Console/Commands/V2/ImportQuestions.php` — `v2:import-questions {path?} {--subject=9702}
  {--copy-images} {--fresh}`. Imports the Generative-Topical extractor output
  (`questions.json` → `v2_questions` + `v2_question_options` + `v2_question_images`). No AI.
- `app/Console/Commands/V2/TagQuestions.php` — `v2:tag-questions {--subject=9702} {--fresh}
  {--syllabus=}`. Tags topic/subtopic via the **deterministic** classifier. No AI.
- `app/Console/Commands/V2/TagByKeywords.php` — `v2:tag-by-keywords {--subject=5054} {--syllabus=}
  {--fresh}`. Keyword-overlap tagger for syllabi carrying weighted keywords (e.g. O-Level 5054);
  sets `needs_review` below `MIN_CONFIDENCE`. No AI.
- `app/Console/Commands/V2/ApplyTopicOverrides.php` — `v2:apply-topic-overrides {--subject=9702}`.
  A curated `(source_paper, question_number) → topic_external_id` lookup table (26 hand-verified
  entries) applied on top of the classifier. No AI.
- **The classifier:** `app/Services/Questions/QuestionTopicClassifier.php` — `VERSION = 'rule-v1'`.
  Entirely in-memory keyword scoring (`SUBTOPIC_KEYWORDS` weighted phrases + `SUBTOPIC_NEGATIVE_KEYWORDS`
  to disambiguate overlapping topics), `squashAbsolute()` confidence mapping, learning-objective
  token matching. **No external API / no LLM.** This is the "tags are the plan, not content"
  boundary noted in project memory.
- `app/Console/Commands/V2/ImportAnswers.php` — imports the mark-scheme `correct_answer` per paper.
- Legacy V1 importer (do NOT reuse for V2): `app/Console/Commands/CambPastImport.php`
  (`cambpast:import`) + `app/Services/Import/CambPastImportService.php`.

### Views
`resources/views/v2/super_admin/question_bank/`: `index.blade.php` (list, table/gallery),
`form.blade.php` (create/edit), `versions.blade.php` (history + diff), `_uploader.blade.php` (partial).

### Tests
- `tests/Feature/Admin/QuestionBrowserTest.php`, `tests/Feature/Admin/PaperManagementTest.php` —
  **V1 legacy** Livewire admin (not the V2 bank).
- `tests/Feature/Import/CambPastImportTest.php` — V1 import.
- No dedicated V2 `QuestionBankController` feature test found in `tests/` (Not implemented — a gap).

## Data Model

Ownership/tenancy: the Question Bank is **global** — questions have **no** `school_id` and are not
school-scoped. Tenancy applies downstream (exams, attempts, mistakes are school-scoped); the bank
itself is a single shared corpus owned by Super Admins.

### `v2_questions`
Migrations: `2026_06_15_000004_create_v2_questions_table.php` (+ `..._000110_add_text_segments`,
`2026_06_16_000001_add_dimensions...`, `2026_06_18_000003_add_status`, `2026_06_23_000001_add_soft_deletes`).

| Column | Type / default | Notes |
|--------|----------------|-------|
| `id` | PK | |
| `paper_id` | FK → `v2_papers` (cascade) | every question belongs to a paper |
| `subject_id` | FK → `v2_subjects` (cascade) | |
| `topic_id` | FK → `v2_topics` (nullOnDelete), nullable | null = **untagged** |
| `subtopic_id` | FK → `v2_subtopics` (nullOnDelete), nullable | |
| `year` | smallint, nullable | denormalized for filtering |
| `question_number` | tinyint | |
| `question_text` | longText, nullable | the stem |
| `text_before`, `text_after` | (added by `..._000110`) | stem split around inline diagrams |
| `layout_type` | string(48), default `text_only` | `text_only` / `question_diagram` / `option_table` / `option_diagram` / `question_diagram_and_option_images` / `mixed` — re-derived on save from image presence |
| `correct_answer` | char(1), nullable | **A/B/C/D — the single source of truth for correctness** |
| `option_table` | json, nullable | `{headers:[], rows:[[]]}` for `option_table` layout |
| `marks` | tinyint, default 1 | |
| `difficulty` | string(16), nullable | `easy`/`medium`/`hard` |
| `page_start`, `page_end` | smallint, nullable | source PDF pages |
| `needs_review` | boolean, default false | tagging low-confidence flag |
| `warnings` | json, nullable | import warnings |
| `source_paper` | string, nullable | e.g. `9702_m16_qp_12` |
| `status` | string(16), default `active`, indexed | `active` / `draft` / `under_review` / `archived` |
| `current_version_id` | (added by `..._000005`), nullable, indexed | → the live `v2_question_versions` row |
| `deleted_at` | softDeletes | Trash pointer |

Unique: `(paper_id, question_number)`. Indexes: `(subject_id, topic_id)`, `(subject_id, year)`,
`year`, `layout_type`, `status`, `deleted_at`.

**`correct_answer` note:** correctness lives on the question, **not** on the option. There is **no
`is_correct` column** on `v2_question_options` — an option is correct iff its `label` equals the
question's `correct_answer`.

### `v2_question_options`
Migration `2026_06_15_000005`. Columns: `id`, `question_id` (FK cascade), `label` char(1) (A/B/C/D),
`text` (nullable — `""`/null when the option IS an image), `has_image` (bool, default false),
`sort_order` (tinyint, default 0). Unique `(question_id, label)`. ~4 rows per question.

### `v2_question_images`
Migrations `2026_06_15_000006` (+ `2026_06_16_000001` adds `width`/`height`). Columns: `id`,
`question_id` (FK cascade), `external_id`, `image_path` (relative), `role` (`question_image_between_text`
/ `question_image_after_text` / `option_image` / `table`), `option_label` (char(1), option_image only),
`page`, `bbox` (json `[x0,y0,x1,y1]`), `caption`, `ocr_text`, `confidence` (decimal 4,3),
`diagram_labels` (json), `sort_order`, `width`, `height`. Index `(question_id, role)`.
**Image files are never deleted** — old version snapshots still reference them (see module 05).

### `v2_topics` / `v2_subtopics`
`v2_topics` (`2026_06_15_000001`): `id`, `subject_id` (FK cascade), `external_id` (string 16 —
syllabus id e.g. `"1"`), `title`, `level` (`AS`/`A Level`), `sort_order`. Unique `(subject_id, external_id)`.
`v2_subtopics` (`2026_06_15_000002`): `id`, `topic_id` (FK cascade), `external_id` (e.g. `"1.1"`),
`title`, `sort_order`. Unique `(topic_id, external_id)`. **Keyed by `external_id`** (the syllabus
identifier), so tags are portable across re-imports.

### `v2_papers` / `v2_subjects`
`v2_papers` (`2026_06_15_000003`): `subject_id`, `source_file` (unique), `source_paper` (indexed),
`subject_code`, `paper_code`, `paper_number`, `variant`, `session_code` (`m`/`s`/`w`),
`session_label`, `year`, `total_questions`. `v2_subjects` (`2026_06_13_000010`): `name`, `code`,
`level`, `is_active`.

## Core Flows

### Import → tag → activate
1. `v2:import-questions` reads extractor `questions.json` → inserts questions, options, images
   (copies image files with `--copy-images`). Questions land with `status = active` and no topic.
2. `v2:tag-questions` (or `v2:tag-by-keywords`) runs the deterministic classifier → sets
   `topic_id` / `subtopic_id`; low-confidence → `needs_review = true`.
3. `v2:apply-topic-overrides` corrects the 26 hand-verified misclassifications.
4. `v2:import-answers` stamps `correct_answer` from the mark scheme (a question is only
   `answerable` — exam-eligible — once `correct_answer` is set).

### Manual authoring (`store`)
Super admin fills the form → `store()` finds the next `question_number` on the subject's custom
paper → creates the question → `syncOptions()` writes A–D → `syncImages()` writes stem/answer
figures and re-derives `layout_type` → `recordVersion()` creates version #1
(`change_summary = 'Created.'`, advances `current_version_id`).

### Edit (`update`)
Inside one DB transaction: update content fields → `syncOptions` → `syncImages` →
`recordVersion()` (version #N, advances `current_version_id`) → `finalizeReview()` (only when the
edit was opened from a Quality Review). See [module 05](./05-question-versioning-and-quality-review.md)
for the versioning/review half.

### Delete / restore
`destroy` soft-deletes (Trash) — **only** for non-active statuses; options/images kept for lossless
restore; frozen exams keep rendering the question via `ExamQuestion::question()->withTrashed()`.
`restore` un-trashes.

## Inputs
- **Extractor output:** `questions.json` (stem, options, `option_table`, image assets, `correct_answer`).
  Disk locations per project memory `v2-question-data-sources.md`.
- **Mark schemes:** `correct_answer` via `v2:import-answers`.
- **Syllabus JSON:** topic/subtopic trees + (for keyword subjects) weighted keywords.
- **Admin form:** stem text/diagrams, A–D options (text and/or image), answer image, status,
  difficulty, `change_summary`.

## Outputs
- The `active` + `answerable` question set consumed by **exam generation**
  (`Question::scopeActive`/`scopeAnswerable`).
- Immutable **version snapshots** (`v2_question_versions`) — see module 05.
- Rendered stems via `stemBlocks()` and images via signed URLs
  (`SecureImageController` / `App\Support\SignedImage`).

## Dependencies
- `app/Services/V2/QuestionSnapshot.php` (version capture — module 05).
- `app/Services/V2/DuplicateOptionImageFixer.php` (render-time dedup net; canonical repair is
  `v2:fix-duplicate-option-images`).
- Exam generation + `ExamService` consume the bank (see [08 — Exam Builder](./08-exam-builder.md)).
- Syllabus tables (`v2_topics`/`v2_subtopics`) and `QuestionTopicClassifier`.

## Security / Access Rules
1. **Super-admin-only CRUD** — all routes under `auth:v2_super_admin`.
2. **No hard deletes** — `destroy` soft-deletes and refuses active questions (`DELETABLE`).
3. **No file deletion** — image rows soft-delete; files stay so older version snapshots render.
4. **Global corpus, no tenancy** — the bank has no `school_id`; a question is shared across all schools.
5. **Exam eligibility gate** — only `status='active'` + non-null `correct_answer` questions enter tests.

## Existing AI-Relevant Context
- The bank is the **canonical content store** the AI tutor must ground on. A question row exposes
  stem (`question_text` + `text_before`/`text_after`), options (`label`/`text`/`has_image`),
  `correct_answer`, diagrams (`v2_question_images` with `bbox`/`caption`/`ocr_text`/`diagram_labels`),
  and curriculum tags (`topic`/`subtopic` via `external_id`).
- **Tagging is deterministic, not generative** — `QuestionTopicClassifier` (`rule-v1`) is the
  single source of topic assignment. Any AI tagger would be a *new* path; today there is none.
- `option_table`, `ocr_text`, and `diagram_labels` give structured, machine-readable descriptions of
  figures — useful grounding for a tutor that must reason about a diagram it cannot "see".

## AI Opportunities
- **AI-assisted tagging / QA of `needs_review` questions** — propose a topic + confidence for
  low-confidence rows for a human to confirm (never auto-write). *Recommended for AI.*
- **Duplicate / near-duplicate detection** across the corpus (embedding similarity) to flag
  accidental re-imports. *Future.*
- **Stem/diagram description enrichment** — generate `caption`/`diagram_labels` for images lacking
  them, to improve downstream tutor grounding (human-reviewed). *Recommended for AI.*

## AI Risks
- **Never mutate the canonical answer.** `correct_answer` is authoritative and human/mark-scheme
  sourced; an AI must not overwrite it (per `question-bank-immutable.md`).
- **Do not hard-delete or unlink.** Any AI maintenance path must respect soft-delete + file
  retention, or it breaks frozen historical exams (module 05).
- **Tagging drift.** An AI tagger that disagrees with `rule-v1` must not silently re-tag live
  questions — that changes every downstream analytic that keys on topic.

## Future Improvements (future)
- Dedicated V2 `QuestionBankController` feature-test coverage. *Not implemented.*
- AI-assisted tagging/QA workflow for `needs_review`. *Recommended for AI / future.*
- Bulk topic-remap tooling with an audit trail (today overrides are code-constant). *Future.*
