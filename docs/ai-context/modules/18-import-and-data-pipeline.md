# 18 — Import & Data Pipeline

> Code-grounded reference. Verify against source before acting.

**Siblings:** [16 — Notifications](./16-notifications.md) ·
[17 — Secure Images](./17-secure-images.md) ·
[19 — Audit Logging](./19-audit-logging.md)

---

## Purpose

The **data-provenance map** for the question bank: how Cambridge past-paper
questions, answers, topic tags, and image assets get from the extractor output on
disk into the V2 database — plus the backup/restore path that snapshots that state
without re-running any generation. Also the artisan commands that repair/clean the
bank and the seeders that build demo datasets.

**Status: Implemented in code** (V2 `v2:*` commands). A separate **legacy V1
importer** (`cambpast:import`, `questions:enrich-topics`) still exists but is
**out of the V2 scope** — see [Legacy V1](#legacy-v1-importer-out-of-v2-scope).

> **Determinism note (important context):** V2 topic tagging is **deterministic —
> no LLM**. `v2:tag-questions` uses `App\Services\Questions\QuestionTopicClassifier`
> (rule/keyword based), anchored to the loaded syllabus tree so it can only emit
> topic/subtopic ids that exist in the syllabus. Nothing out-of-syllabus is ever
> written. Backup/restore is likewise a pure data operation.

---

## Users / Roles

Operator/CLI only (developers running artisan). None of these are user-facing HTTP
routes.

---

## Current Implementation

### Where the source files live on disk (verify)

- **Generative-Topical extractor output:**
  `storage/Generative-Topical/output/papers/<stem>/questions.json` (+
  `images_final/`). `<stem>` = e.g. `9702_m16_qp_12`. This is the default source
  for `v2:import-questions` / `v2:import-answers` / `v2:copy-images`.
- **Served images:** copied into `storage/app/public/v2/questions/<stem>/`; the
  stored `image_path` is web-relative (`v2/questions/<stem>/<file>`) — served via
  [module 17](./17-secure-images.md).
- **Backups:** `storage/app/questions/backups/<stem>/` (per-paper JSON + image
  copies) — written by `v2:export-questions`, read by `v2:import-from-backup`.
- **Tagging rubrics / working files:** `storage/app/questions/tagging/`.
- **Syllabus JSON:** `storage/syllabus/…` (e.g.
  `storage/syllabus/physics/Alevels/subject_content_a_level_physics.json`).

### Import service

`app/Services/Import/CambPastImportService.php` — belongs to the **V1** importer
(uses `App\Models\ImportBatch`, `QuestionAsset`; writes no `v2_` tables).
`importPaperFromJson()` returns counters
`{paper_id, imported, skipped, demo_safe, errors[]}`. **Not part of the V2 bank
build.** The V2 import logic lives directly in the `v2:import-*` command classes.

### V2 commands (`app/Console/Commands/V2/`)

Signatures + one-line purpose (from each file's `$signature` / `$description`):

| Command | Signature (options) | Purpose |
| --- | --- | --- |
| **Import / build** | | |
| `v2:import-questions` | `{path?} {--subject=9702} {--copy-images} {--fresh}` | Import Generative-Topical `questions.json` into `v2_papers`, `v2_questions`, `v2_question_options`, `v2_question_images`. `--fresh` wipes the subject first; `--copy-images` copies `images_final/*` into public storage. `correct_answer`/`topic_id` read through as-is (nullable). |
| `v2:import-answers` | `{path?}` | Import correct answers from `questions.json` into `v2_questions` (run `extract_marks.py` first). |
| `v2:copy-images` | `{path?} {--force}` | Copy question `images_final` into public storage so the app can serve diagrams. `--force` overwrites. |
| **Backup / restore** | | |
| `v2:export-questions` | `{--subject=} {--paper=} {--out=} {--no-assets} {--with-trashed}` | Export the bank (questions, markings, assets) to per-paper JSON + image backups under `storage/app/questions/backups`. |
| `v2:import-from-backup` | `{--path=} {--subject=} {--paper=} {--mode=tags} {--dry-run}` | Restore topic tags + answers (`mode=tags`) or full rows/images (`mode=full`) from an export — **no AI re-run**. |
| **Tagging (deterministic, no API)** | | |
| `v2:tag-questions` | `{--subject=9702} {--fresh} {--syllabus=}` | Tag questions with syllabus topics via the in-repo deterministic `QuestionTopicClassifier`. |
| `v2:tag-by-keywords` | `{--subject=5054} {--syllabus=…o_level_physics.json} {--fresh}` | Seed topics from a keyword-syllabus JSON and tag a subject's questions to the best-matching topic. |
| `v2:apply-topic-overrides` | `{--subject=9702}` | Apply curated topic corrections on top of the keyword classifier (no API). |
| **Bank repair / cleanup** | | |
| `v2:strip-question-boilerplate` | `{--dry-run}` | Strip Cambridge footer/boilerplate text out of question fields. |
| `v2:fix-symbol-font` | `{--subject=*} {--dry-run}` | Repair Adobe Symbol-font chars mis-stored as Private-Use codepoints (arrows, Greek, maths operators). |
| `v2:fix-duplicate-option-images` | `{--dry-run}` | Collapse duplicate question/option images so each diagram renders once. |
| `v2:detach-reference-images` | `{--dry-run}` | Re-role full-page Periodic Table / Data Sheet pages off questions so they're not shown inline. |
| `v2:backfill-image-dimensions` | `{--force}` | Cache real pixel width/height for question images (`--force` re-reads rows that already have dimensions). |
| `v2:quarantine-optionless` | `{--dry-run} {--status=under_review}` | Move active questions with no answer-choice rows to `under_review` (they cannot be served). |
| `v2:recover-multiple-completion` | `{--subject=9701} {--dry-run}` | Re-attach the standard CIE multiple-completion A–D rubric to optionless questions and re-activate them. |
| **Feature backfill** | | |
| `v2:backfill-mistakes` | `{--student=}` | Backfill the Mistake Bank from existing submitted attempts (links [module 12](./12-mistake-bank.md)). |
| **Notifications (documented in [module 16](./16-notifications.md))** | | |
| `v2:student-exam-reminders` | *(none)* | Daily student exam-lifecycle notifications (no API). |
| `v2:teacher-attention-reminders` | `{--days=60} {--min-tests=4} {--min-questions=5}` | Daily teacher attention flags + operational updates (no API). |

### Backup / restore flow

`v2:export-questions` writes per-paper JSON (questions + markings/answers + asset
metadata) and copies the image files into `storage/app/questions/backups/<stem>/`.
`v2:import-from-backup` reads that back: `--mode=tags` restores **tags + answers
onto existing questions** (the common case — restores classification without
re-running the deterministic classifier or any generation), `--mode=full` also
rebuilds missing rows + image files. `--dry-run` reports without writing. This is
the snapshot/restore that lets you rebuild the bank state without re-doing the
tagging pass.

### Seeders (`database/seeders/`)

One line each (from each seeder's header):

| Seeder | Purpose |
| --- | --- |
| `V2SubjectsSeeder` | Idempotent `v2_subjects` — the O-Level + A-Level CAIE subject/code catalog (e.g. 5054 Physics O, 9702 Physics A, 9701 Chem A, 5090 Bio O). |
| `V2TopicsSeeder` | `v2_topics` + `v2_subtopics` for A-Level Physics 9702 from the official syllabus JSON. Idempotent on (subject, external_id). |
| `V2BiologyTopicsSeeder` | CAIE O-Level Biology 5090 topic taxonomy (19 top-level topics from `5090_y25_sy`). Idempotent. |
| `V2SuperAdminSeeder` | The platform super-admin (`super@gt.v2`). |
| `V2BranchSeeder` | Branches per school + a branch admin each; back-fills `branch_id` on classes/teachers/students/enrollments (Sage gets two branches for isolation demos). Idempotent. |
| `V2DemoSeeder` | The canonical multi-school demo dataset (A-Level Physics baseline). |
| `V2ScienceDemoSeeder` | Chemistry + Biology demo, O & A Level — adds the science spine (O-Level grade, Chem/Bio teachers) the base demo lacked. |
| `V2NotesDemoSeeder` | Learning-Hub Notes demo (Physics only): approves DRAFT learning assets of handpicked questions — status flip only, asset content never touched. Links [module 15](./15-notes-module.md). |
| `V2ShowcaseAssetsSeeder` | Ports the bespoke Biology showcase blades (photosynthesis / biology) into real `v2_question_learning_assets` mapped onto actual bank questions. Links [module 13](./13-learning-hub-worked-solutions-assets.md). |
| `V2ReportDemoSeeder` | One O-Level student with a full cross-subject history for report demos. |

(Non-V2 seeders `AdminUserSeeder`, `CatalogSeeder`, `DemoUsersSeeder`,
`SyllabusPhysicsAlevelSeeder`, `DatabaseSeeder` belong to the legacy V1 app —
out of V2 scope.)

---

## Data Model

Targets written by the V2 import commands: `v2_subjects`, `v2_papers`,
`v2_questions`, `v2_question_options`, `v2_question_images`, `v2_topics`,
`v2_subtopics`, and question learning-asset tables (see [module 13](./13-learning-hub-worked-solutions-assets.md)).
`image_path` on `v2_question_images` is the web-relative path served by
[module 17](./17-secure-images.md). No dedicated V2 "import batch" table — the V2
commands are idempotent/re-runnable rather than batch-tracked.

---

## Core Flows

1. **First-time bank build (per paper set):**
   `v2:import-questions --subject=NNNN --copy-images` →
   `v2:import-answers` (or answers already in `questions.json`) →
   `v2:tag-questions` / `v2:tag-by-keywords` (+ `v2:apply-topic-overrides`) →
   repair passes as needed (`v2:strip-question-boilerplate`, `v2:fix-symbol-font`,
   `v2:fix-duplicate-option-images`, `v2:detach-reference-images`,
   `v2:backfill-image-dimensions`) → `v2:quarantine-optionless` /
   `v2:recover-multiple-completion` for the optionless-MCQ gap.
2. **Snapshot:** `v2:export-questions` → `storage/app/questions/backups/`.
3. **Restore (no AI):** `v2:import-from-backup --mode=tags` (or `full`).
4. **Feature backfill:** `v2:backfill-mistakes` after the Mistake Bank shipped.

Most repair/import commands support `--dry-run` — always dry-run first.

---

## Inputs

- Extractor output on disk (`questions.json`, `images_final/`).
- Syllabus JSON trees (`storage/syllabus/…`).
- Export backups (`storage/app/questions/backups/`).

## Outputs

- Rows in the `v2_*` question tables.
- Copied image files in `storage/app/public/v2/questions/…`.
- Per-paper JSON + image backups on export.

---

## Dependencies

- `App\Services\Questions\QuestionTopicClassifier` (deterministic tagging).
- The syllabus loader + syllabus JSON files.
- `Storage` (public disk) for images.
- Downstream: [module 17 Secure Images](./17-secure-images.md) serves the imported
  files; [module 12 Mistake Bank](./12-mistake-bank.md) is backfilled from attempts.

---

## Security / Access Rules

- CLI-only; no HTTP surface.
- **Question-bank immutability rule (see project memory):** never delete/modify
  bank questions or assets outside these purpose-built, dry-run-capable commands.
  `--fresh`/`full` are destructive — confirm scope before running.

---

## Existing AI-Relevant Context

- Tagging is **already deterministic and syllabus-anchored** — an AI agent should
  treat the existing tags as ground truth, not re-derive them.
- The export JSON is a clean, complete serialization of a paper's questions +
  answers + asset metadata — a good structured source for any downstream context.

## AI Opportunities

- **Recommended for AI (with guardrails):** an *audit/QA assistant* that flags
  suspicious deterministic tags for human review — but it must **not** write tags
  directly; the deterministic classifier remains the writer.
- **Recommended for AI:** summarising a paper's coverage from the export JSON for
  teacher-facing "what this paper tests" blurbs.

## AI Risks

- **Do not** insert an LLM into the tagging write path — it would break the
  syllabus-anchored guarantee (only-in-syllabus ids) and the reproducibility that
  backup/restore relies on.
- The bank is immutable by policy; an AI pipeline that mutates questions/assets
  violates the [question-bank-immutable] rule.

## Future Improvements

- **Not implemented:** a V2 import-batch/audit table (V1's `ImportBatch` is not
  carried into V2). Import runs are currently idempotent rather than tracked.
- The legacy V1 importer (`cambpast:import`, `questions:enrich-topics`) is retained
  but should not be extended for V2 work.

---

## Legacy V1 importer (out of V2 scope)

- `app/Console/Commands/CambPastImport.php` — `cambpast:import {path?} {--copy-assets}`
  — "Import extractor output (questions.json + assets) into the CambPast database."
  Uses `App\Models\ImportBatch` + `CambPastImportService` (V1 tables). **Legacy.**
- `app/Console/Commands/EnrichQuestionTopicsCommand.php` — `questions:enrich-topics`
  (many options: `--paper=*`, `--all`, `--from-json`, `--from-db`, `--dry-run`,
  `--force`, `--limit=`, `--syllabus=`, `--export-json`, `--output-root=`) —
  "Classify A-Level Physics questions against the syllabus and evaluate difficulty."
  Uses `QuestionEnrichmentService` (V1). **Legacy — do not use for V2.**
