# 15 — Notes Module

> Code-grounded reference. Verify against source before acting.

**Siblings:** [12 — Mistake Bank](./12-mistake-bank.md) ·
[13 — Learning Hub / Worked Solutions / Assets](./13-learning-hub-worked-solutions-assets.md) ·
[14 — Interactive Widgets](./14-interactive-widgets.md)

**A thorough design/flow doc already exists at [`../../learning-hub-notes.md`](../../learning-hub-notes.md)
(22 passing tests). This file condenses it for an AI agent and must not contradict it — read that
doc for the full brief, logic/design decisions, and end-to-end walkthrough.**

---

## Purpose

A Notion-style, block-based note system for students, built on **BlockNote 0.51** inside the
Inertia/React "Learning Studio" (`resources/js/learn/notes/`). Its defining capability — the one a
generic note app can't copy — is the **import bridge**: students pull real platform content (worked
solutions, interactive simulators, their own mistakes) straight into personal notes, because the app
owns the question bank, the asset pipeline, and the mistake data.

**Notes is the save-target for AI tutor output (Phase 1)** — the planned Student AI Tutor writes its
answer into a note (`docs/ai-context/NEXT_SESSION_AI_TUTOR_PROMPT.md`). The asset/block seam is ready
for it; AI-in-notes itself is deferred.

Status: **Implemented in code** (editor, blocks, autosave, versions, import bridges, subject
hierarchy, mobile, images). AI-in-notes and revision mode are **Not implemented** (roadmap).

## Users / Roles

- **Students only.** All routes under the `v2_student` guard, prefix `notes`
  (`routes/v2.php:358-374`). Every controller `authorizePage()`/`authorizeMistake()` 403s on
  foreign records; `NotesPage` carries a **school global scope** (`app/Models/V2/NotesPage.php:48-59`,
  mirrors `StudentMistake`).

## Current Implementation

Backend controllers (`app/Http/Controllers/V2/Student/`):
`NotesController` (shell index/show + figureUrls), `NotesPageController` (create / autosave / meta /
delete / outline), `NotesImportController` (the import bridge + gating), `NotesVersionController`
(history + restore), `NotesTreeController` (tree + search), `NotesUploadController` (private image
store). All six are imported in `routes/v2.php:20-25`.

Services (`app/Services/V2/`): `NotesDocumentService` (search fields, throttled snapshots,
provisioning, tree, figure-URL minting), `NotesBlockMapper` (asset/mistake → blocks).

Models (`app/Models/V2/`): `NotesNotebook`, `NotesSection`, `NotesPage`, `NotesPageVersion`,
`NotesPageTag`.

Frontend (`resources/js/learn/`): `Pages/Notes.jsx` (shell), `notes/` (editor, sidebar, modal,
picker, version drawer, autosave hook, schema, `figureUrls.jsx`), `notes/blocks/*.jsx` (11 block
files — below), `lib/tex.jsx` (KaTeX + mhchem shared renderer).

Tests: `tests/Feature/V2/LearningHubNotesTest.php`.

## Data Model

Hierarchy: **Notebook = subject → Section = chapter/topic → Page ("a Note") = one BlockNote
document.** Subject/topic links are nullable so free-form "My Notes" works.

Migrations: `2026_07_06_000001…000005` (notebooks, sections, pages, page_tags, page_versions) +
`2026_07_07_000001_add_subject_links_to_notes_tables` (adds `notebooks.subject_id`,
`sections.topic_id`).

### `v2_notes_pages` (the load-bearing table) — `app/Models/V2/NotesPage.php`
| Column | Purpose |
|--------|---------|
| `section_id`, `student_id`, `school_id` | ownership (FK cascade) |
| `title` (default "Untitled"), `icon` | display |
| `document_json` (longText, cast array) | **BlockNote's native top-level block array, stored verbatim** |
| `plain_text` (longText) | flattened text of all blocks — rebuilt every save (search) |
| `block_types_json` (JSON) | distinct block types present — powers "find all flashcard blocks" |
| `subject_id`, `topic_id`, `subtopic_id` | curriculum tags (nullable) |
| `is_pinned`, `is_favorite`, `is_archived`, `sort_order` | organization |
| `save_status` (`saved`/`error`), `content_version` (int, default 1) | **optimistic-concurrency lock** |
| `last_edited_at` | timestamp |

`content_version` is an optimistic-concurrency counter: a stale autosave is rejected with **409**
carrying the server's copy, never a silent clobber. A **FULLTEXT index on `(title, plain_text)` is
MySQL/MariaDB only** (guarded in the migration; sqlite tests have none).

Other tables: `v2_notes_notebooks` (`subject_id` nullable, title, emoji), `v2_notes_sections`
(`topic_id` nullable, `notebook_id`), `v2_notes_page_tags` (free-text labels, `page_id`+`tag`),
`v2_notes_page_versions` (`document_json` snapshot + `content_version`, last-20).

### Block types (`resources/js/learn/notes/blocks/`)
Custom blocks: **widget**, **mermaid**, **equation**, **card** (flashcard/memcard), **asset_snapshot**,
**mistake**, **callout**, **image_text** (two-column), **page_break**, **question_figure**, plus
`ui.jsx` helpers. (Standard paragraph/heading/list/table come from BlockNote core.)

- **`widget`** — persists only `{widgetType, config}` (config = the widget's live state as the
  student left it); re-mounts live via `WidgetRenderer` (module 14). `content: 'none'`,
  `contentEditable={false}`.
- **`question_figure`** — a **render-time signed-URL REFERENCE**: stores
  `{questionId, questionVersionId, imagePath, caption}` **and never a URL**. The signed URL is minted
  per view after an access re-check (below). `useFigureUrl(imagePath)` pulls it from context; no
  access → "unavailable" message, and the URL never enters `document_json`.
- **`mistake`** — a live reference by hashid + a thin snapshot (stem/selected/correct/studioUrl) so
  it deep-links to the studio yet still reads if archived.
- **`asset_snapshot`** — worked_solution / revision_notes / common_mistakes markdown, snapshotted
  (survives a later asset hide). Renders LaTeX via the shared KaTeX renderer.
- **`callout`** — cycleable variants incl. `correct`/`wrong` (used by imported option explanations).

## Core Flows

### Autosave & concurrency (`NotesPageController::update` + `NotesDocumentService::apply`)
1. Client PUTs `{document, content_version, title?}` (~debounced).
2. If `content_version` ≠ server's → **409** with `{contentVersion, title, document}`; client adopts
   the newer copy (`NotesPageController.php:83-90`).
3. Fresh → `apply()`: snapshot if due, recompute `plain_text` + `block_types_json`, bump
   `content_version`, set `last_edited_at`. Verified in `LearningHubNotesTest`.

**Snapshots** (`NotesDocumentService`): throttled to one per `SNAPSHOT_EVERY_SECONDS = 120`, pruned
to `MAX_VERSIONS = 20`. `restore()` snapshots current state first (restore is reversible).

**Search-field flattening** (`flattenText` / `blockTypes`): walks blocks recursively, pulling
`TEXT_PROPS = [front, back, source, markdown, title, questionStem, caption, label, latex]` plus inline
runs and table cells — so search never parses `document_json` at query time.

### The import bridge (`NotesImportController::store`) — the moat
`POST notes/pages/{page}/import` with `source ∈ {mistake, asset, widget_state}`.
1. **Anchor gate:** every import is anchored to one of the student's own mistakes. Aborts 404 unless
   the mistake belongs to the student **and** `latestExam->resultsReleased()` — mirrors the
   Learning Hub gate (module 12). A student can't harvest assets for questions they never met.
2. **Asset gate:** `asset` / `widget_state` sources resolve through `visibleAsset()` (approved/edited
   only, module 13). `widget_state`'s widget **type must match** the question's own visible widget.
3. **Map** (`NotesBlockMapper`): mistake → mistake block + question figures + option explanations;
   asset → snapshot/card/mermaid/widget blocks (worked_solution & option_explanation also prepend the
   question figure); widget_state → a live widget block from the student's config.
4. **Positional splice:** `before_block_id` inserts before a top-level block (a page_break id from
   `outline`); unknown/absent → append. **Section-scoped figure dedup**
   (`withoutDuplicateFigures`/`sectionFigurePaths`) prevents duplicating the same diagram within a
   page-break section.
5. `apply()` re-saves; the page auto-inherits curriculum tags from the mistake if unset.

### Question-figure URL minting (`NotesDocumentService::figureUrls`)
On `NotesController@show`, walks the document for `question_figure` blocks, **re-checks** the student
still has a released mistake on each question, mints fresh viewer-bound `SignedImage::url(...)`, and
passes them as `page.figureUrls`. Nothing is persisted; access loss → the figure silently won't
resolve.

### Provisioning & tree (`NotesDocumentService::tree` + helpers)
Opening Notes provisions **one notebook per active enrollment subject** (idempotent, matched by
subject **id**, not name). `ensureSubjectHome(subjectId, topicId?)` creates the notebook+topic section
on demand (imports, "new note in suggested place"); `ensureDefaults()` is the "My Notes / General"
fallback.

## Inputs

- **Student edits** → autosave PUT.
- **Import bridge** → mistake / asset / widget_state (all anchored to a released mistake).
- **Image uploads** → `notes/uploads` (private disk `notes-images/{studentHashid}/{uuid}.{ext}`,
  owner-only serving, throttle 30/min, jpg/png/gif/webp, no svg).
- **Meta** → rename / pin / favourite / archive / curriculum + free tags.

## Outputs

- `Pages/Notes.jsx` — sidebar tree + BlockNote editor.
- `tree` / `search` JSON (`NotesTreeController`); `outline` (page-break sections for the import
  picker); `versions` list + `restore`.
- `figureUrls` map on page show.

## Dependencies

- **BlockNote 0.51.4** pinned with **Mantine 8.x** (newer Mantine needs React 19; project is 18.3).
  Gotcha: `createReactBlockSpec` returns a **factory** in 0.51 — specs must be invoked in the schema.
  (Full pin/gotcha list: memory `v2-notes-blocknote`.)
- **KaTeX + mhchem** (`lib/tex.jsx`) — the one `dangerouslySetInnerHTML` (trusted KaTeX output).
- Module 12 (`MistakeBankService` — anchor + `visibleAsset`), module 13 (asset payloads), module 14
  (`WidgetRenderer` / `camb:add-to-note`), `SignedImage`/`SecureImageController` (figures).
- **License:** two-column `image_text` block is custom, deliberately not `@blocknote/xl-multi-column`
  (GPL/commercial) — keeps the tree MPL-clean.

## Security / Access Rules

1. **Ownership** on every page/mistake action (403).
2. **School global scope** on `NotesPage`.
3. **Import anchor + release gate** — every import requires a released, owned mistake.
4. **Asset visibility** — imports pull only approved/edited assets (module 13); widget imports must
   match the question's own widget type.
5. **Figures never store URLs** — signed, viewer-bound, re-checked per view; access loss = no image.
6. **Optimistic concurrency** — 409, never a silent clobber across tabs.
7. **Uploads** — private owner-only disk, server-named files, svg excluded, mime + size capped.

## Existing AI-Relevant Context

- A note's `document_json` is a structured block array; `plain_text` + `block_types_json` are the
  pre-computed, query-friendly projections an AI could read without parsing the doc.
- `NotesBlockMapper` shows exactly how platform content maps to blocks — **the same factories an AI
  tutor's "save answer to Notes" would use.** An AI answer would become one or more blocks (e.g. an
  `asset_snapshot`-like markdown block, or a callout), inserted via the same splice/`apply` path.
- `question_figure`, `widget`, and `mistake` blocks are live references — an AI writing into a note
  should prefer these reference blocks over pasting content it doesn't own.

## AI Opportunities

- **Save AI tutor output to a note** (the Phase 1 target) — insert AI answers as blocks through the
  existing import/apply path, grounded in the mistake context (module 12). *Recommended for AI.*
- **AI-in-notes actions** — explain / simplify / summarize / generate cards / quiz from a note's
  `plain_text` (deferred in the brief; the block seam is ready). *Future.*
- **Revision mode** — hide answers / quiz cards, building on flashcard/memcard blocks. *Not implemented.*

## AI Risks

- **Respect the anchor + release gate.** Any AI "save to note" must go through a released, owned
  mistake — do not let AI insert content for questions the student can't access.
- **Never persist a signed image URL** into `document_json`; use `question_figure` reference blocks
  (the URL is minted per view). Persisting a URL would leak/expire.
- **Optimistic concurrency** — an AI write must carry the current `content_version` or it will 409;
  don't force-overwrite a newer document.
- **Don't inject arbitrary widgets** — widget imports are type-checked against the question's own
  visible widget.
- **KaTeX is the only trusted `dangerouslySetInnerHTML`** — AI-authored content must not open a new
  HTML-injection path.

## Future Improvements

- AI-in-notes (summarize / quiz / improve wording) and active-recall revision mode. *Not implemented (roadmap).*
- Inline `$x$` rendering in editable text (currently only equation blocks / imports render maths). *Deferred.*
- Image crop / annotate; copy a figure into private notes storage only if the student annotates it. *Future.*
