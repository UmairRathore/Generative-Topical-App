# 13 — Learning Hub: Worked Solutions & Learning Assets

> Code-grounded reference. Verify against source before acting.

**Siblings:** [12 — Mistake Bank](./12-mistake-bank.md) ·
[14 — Interactive Widgets](./14-interactive-widgets.md) ·
[15 — Notes Module](./15-notes-module.md)

---

## Purpose

`v2_question_learning_assets` is the **reusable, per-question learning-content pool** behind the
Learning Hub: worked solutions, per-option explanations, flashcards/memcards, mermaid flows,
interactive-widget configs, revision notes, and common-mistakes writeups. Content is attached to
the **canonical `question_id`** (never per student), so an asset is authored/generated once and
shared by everyone who has that question in their Mistake Bank.

This is the **trusted-content pool an AI tutor should prefer over free generation** — every visible
asset has passed super-admin review.

Status: **Implemented in code** (display + review). **Generation happens OUTSIDE the app** (see below).

## Users / Roles

- **Students** — read-only, and only through a released mistake (`MistakeBankService::visibleAsset`).
  They never see the asset table directly.
- **Super-admins** — review/approve/edit/reject/hide via
  `app/Http/Controllers/V2/SuperAdmin/QuestionAssetReviewController.php`
  (guard `v2_super_admin`, routes `routes/v2.php:121-124`).
- **No in-app author role.** Generation is external (a Claude Code / workflow process writes `draft`
  rows directly). The controller comment states this explicitly (lines 15-21): "Generation happens
  OUTSIDE the app … there is **no regenerate** action." See the asset-generation pipeline
  (planned `modules/18`, memory `v2-asset-generation`).

## Current Implementation

- Model: `app/Models/V2/QuestionLearningAsset.php` — the type/status whitelists + `scopeVisible`.
- Migration: `database/migrations/2026_07_02_000003_create_v2_question_learning_assets_table.php`.
- Student read path: `MistakeBankService::visibleAsset()` +
  `LearningHubController::{review,studio,asset}`.
- Admin review path: `QuestionAssetReviewController` + Inertia `resources/js/learn/Pages/AssetReview.jsx`.
- Studio render: Inertia `resources/js/learn/Pages/Solution.jsx`.
- Structural validation: `app/Support/LearningAssetValidator.php`.

## Data Model

### `v2_question_learning_assets`
`unique(question_id, asset_type, asset_key)` — `asset_key = ''` for singletons (e.g.
`worked_solution`); a per-item key for sets. `index(question_id, status)`.

| Column | Purpose |
|--------|---------|
| `question_id` | canonical question (FK cascade) |
| `asset_type` | one of the 8 allowed types (below) |
| `asset_key` | `''` for singletons; item key / widget type for sets |
| `title` | optional heading |
| `content` (longText) | markdown / mermaid source / plain text |
| `payload_json` (cast array) | structured payload (cards array, option explanations, widget `{widget, config}`) |
| `format` | `markdown` \| `html` \| `json` \| `mermaid` … |
| `status` | review lifecycle (below), default `draft` |
| `source_hash` | dedup/provenance |
| `generated_by` | `manual` \| `claude` \| `fable` \| `opus` \| `demo_showcase` \| user ref |
| `reviewed_by`, `reviewed_at`, `approved_at` | review provenance |

### The 8 asset types (`ALLOWED_TYPES`, model lines 19-22)
`worked_solution` · `option_explanation` · `flashcards` · `memcards` · `mermaid` ·
`interactive_widget` · `revision_notes` · `common_mistakes`.

`DISPLAYABLE_TYPES` (the text types the Hub's JSON `asset` endpoint serves, lines 25-27):
`worked_solution`, `option_explanation`, `revision_notes`, `common_mistakes`.
(The studio renders **all** 8; the plain JSON `asset` endpoint serves only these four.)

### Payload shapes (verified against `NotesBlockMapper` + `Solution.jsx`/`AssetReview.jsx`)
- `worked_solution` / `revision_notes` / `common_mistakes` — markdown in `content`.
- `option_explanation` — `payload_json = {options: [{label, text|values, correct, why}], columns?: []}`.
- `flashcards` / `memcards` — `payload_json` is a **bare array** `[{front, back}, …]`.
- `mermaid` — mermaid source in `content` (`format = 'mermaid'`; must start `graph`/`flowchart`/…).
- `interactive_widget` — `payload_json = {widget, config}` (or a bare config with `asset_key` = the
  widget type). See [module 14](./14-interactive-widgets.md).

### Status lifecycle (model lines 33-48)
```
draft ──▶ approved ─┐          (student-visible)
      └▶ edited ────┘  ← super-admin edit
      └▶ rejected
      └▶ hidden
      └▶ failed_validation
legacy: generated, reviewed  (treated as PENDING, kept only for mapping)
```
- `VISIBLE_STATUSES = [approved, edited]` — **the only statuses a student ever sees** (lines 44-45).
- `PENDING_STATUSES = [draft, generated, reviewed]` — awaiting review (approve-all targets these).
- `scopeVisible()` filters to `VISIBLE_STATUSES` (lines 65-68). Verified by
  `LearningHubMistakeBankTest::test_only_visible_assets_are_returned` (draft & generated hidden;
  approved & edited visible).

## Core Flows

### Student read (single gate)
`MistakeBankService::visibleAsset(questionId, type)` (service lines 310-321):
1. Reject `type` not in `ALLOWED_TYPES` (returns null).
2. Query `question_id + asset_type`, `scopeVisible()`, ordered `approved` before `reviewed` before
   anything else. Returns the single best visible asset or null.

`studio()` (controller lines 90-166) loads every text/structured type + the `interactive_widget`,
records `asset_viewed` events, and passes them to the `Solution` Inertia page as tabs (tabs
auto-appear per asset present). `review()` loads just the `worked_solution`.

### Super-admin review
`QuestionAssetReviewController`:
- `review()` — Inertia `AssetReview` page: question stem (from the **current version snapshot**
  `v2_question_versions.snapshot`), signed diagram figures, options with correct flag, and every
  asset **of any status** with `LearningAssetValidator::warnings()` attached. Open-redirect-safe
  back URL.
- `updateStatus()` — `approve` / `reject` / `hide` / `reset` (reset → `draft`). Sets `reviewed_by`
  (super-admin id) + timestamps.
- `update()` — edit title/content/payload_json → status `edited` (student-visible). Validates JSON.
- `approveAll()` — bulk-approve every `PENDING_STATUSES` asset for the question.

### Generation (external — not in this app)
Draft rows are written by a Claude Code / workflow pipeline (memory `v2-asset-generation`), grounded
on the question stem + options + correct answer. **The app has no LLM key for this and no regenerate
button.** The asset table is the seam between the external generator and the in-app review/display.

## Inputs

- **External generator** → `draft` rows (out of app).
- **Super-admin** → status/edit actions.
- **Student** → read requests routed through a released mistake only.

## Outputs

- `Solution.jsx` (student studio) and `AssetReview.jsx` (admin) — both reuse the same renderers
  (Markdown, Flashcards, Flow/mermaid, `WidgetRenderer`, Figures).
- Notes import (module 15): `NotesBlockMapper::fromAsset()` snapshots text assets, emits card
  blocks, and re-mounts widgets live.

## Dependencies

- `MistakeBankService::visibleAsset()` — the sole student gate.
- `LearningAssetValidator` — structural warnings (never blocks).
- `SignedImage` / `SecureImageController` — diagram figures.
- Widget registry (`resources/js/widgets/registry.js`) — `interactive_widget` rendering (module 14).
- `v2_question_versions` — the review page reads the current version snapshot for stem/options.

## Security / Access Rules

1. **Students see approved/edited only** — `VISIBLE_STATUSES`, enforced in one place (`scopeVisible`).
2. **Reachable only through a released mistake** — `visibleAsset` has no auth of its own; every
   caller (`review`/`studio`/`asset`, notes import) first authorizes the mistake and checks
   `resultsReleased()`. A student cannot pull assets for a question they never attempted.
3. **Type whitelist** — `ALLOWED_TYPES` (service + import controller); the JSON endpoint further
   restricts to `DISPLAYABLE_TYPES`.
4. **Admin actions are super-admin-guarded** and record `reviewed_by`.

## Existing AI-Relevant Context

For an AI tutor, the approved assets on a question are **pre-vetted grounding material**:
- `worked_solution` (markdown, often already contains `$$…$$` LaTeX) — the canonical explanation.
- `option_explanation` payload — why each distractor is wrong (distractor analysis, per-label).
- `common_mistakes` / `revision_notes` — misconception + summary text.
- `flashcards` / `memcards` — atomic recall facts already grounded on the solution.

`LearningAssetValidator` encodes what "structurally valid" means (correct option count, one correct
answer matching the key, known widget type, calculator has formula+inputs) — useful guardrails for
any AI that authors or checks assets.

## AI Opportunities

- **Prefer approved assets over free generation.** A tutor should quote/summarize the approved
  `worked_solution` + `option_explanation` before generating anything new; only generate when no
  approved asset exists, and say so. *Recommended for AI.*
- **AI-assisted review triage** — pre-score drafts (extend `LearningAssetValidator` with semantic
  checks) to speed super-admin approval. *Recommended for AI.*
- **Gap-filling generation** — author drafts for questions lacking a type, writing `draft` rows for
  human review (never auto-approve). *Future.*

## AI Risks

- **Never surface unapproved (`draft`/`rejected`/`hidden`) content to students.** Any AI feature
  must go through `visibleAsset` / `scopeVisible`, not raw queries.
- **Don't contradict the answer key.** `option_explanation`'s `correct` flag and the question's
  `correct_answer` are authoritative; the validator already flags mismatches.
- **Generation stays review-gated.** AI-authored assets must land as `draft` — auto-approving
  bypasses the super-admin trust boundary that makes this pool "trusted."
- **Widget configs must reference a real component** (`LearningAssetValidator::KNOWN_WIDGETS`), or
  they will not render (module 14).

## Future Improvements

- In-app, API-backed generation command (memory notes the app currently has no Anthropic key;
  external workflow is the stopgap). *Not implemented.*
- Broader per-subject coverage (memory: 5054 O-Level Physics is largely done; other subjects pending).
- AI-assisted semantic validation beyond the current structural checks. *Recommended for AI.*
