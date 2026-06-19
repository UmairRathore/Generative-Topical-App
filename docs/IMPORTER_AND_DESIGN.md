# V2 Question Bank — Importer & Design Choices

How Cambridge past-paper MCQs get into the platform, how they are rendered, and
the content-security + test-lifecycle systems built around them. Everything here
is subject-agnostic: A-Level Physics (9702), O-Level Physics (5054) and future
subjects all share the same tables, partitioned by subject.

---

## 1. The importer — `v2:import-questions`

One generic command imports any subject's bundle. It reads the pipeline output
and writes `v2_papers`, `v2_questions`, `v2_question_options`,
`v2_question_images`.

```bash
# A-Level Physics (default path)
php8.4 artisan v2:import-questions --subject=9702 --copy-images

# O-Level Physics (point at the bundle's papers/ dir)
php8.4 artisan v2:import-questions storage/olevel_physics_5054/papers --subject=5054 --copy-images
```

| Option | Meaning |
|---|---|
| `path` (arg) | The `papers/` directory. Image paths in the JSON are resolved relative to its parent (the bundle root). |
| `--subject=<code>` | Syllabus code (5054 / 9702 / …). Looked up in `v2_subjects`; **everything is scoped to that subject's id**. |
| `--copy-images` | Copy `images_final/*` into `storage/app/public/v2/questions/<stem>/` and run the duplicate-option-image fixer. Always use this. |
| `--fresh` | Delete the subject's existing papers first (FK-cascades to questions/options/images). Required to re-import. |

### Bundle shape (per paper `<stem>` e.g. `5054_w19_qp_11`)

```
papers/<stem>/questions.json      one paper, ~40 questions
papers/<stem>/images_final/*.png  every referenced crop
papers/<stem>/<stem>_ms_*.pdf     official mark scheme (reference only)
```

`questions.json` is identical across subjects. Per question: `question_text`,
`image_between_question_before_text` / `..._after_text` (stem split), `options[]`
(`{label,text,images[]}`), `option_table` (`{headers,rows,image_path}`),
`assets[]` (`{id,image_path,role,bbox,…}`), `correct_answer`, `layout_type`.
Image `image_path` is relative to the bundle root, e.g.
`papers/5054_w25_qp_12/images_final/q010_option_A.png`.

### Consistency guarantees

- **Subject partitioning.** `v2_papers.subject_code` is set to the imported
  subject's own code; every question's `subject_id` matches its paper's. O-Level
  (5054, subject id 5, level "O Level") and A-Level (9702, id 16, "A Level")
  never mix — all app scoping (classes, exams, question bank, `ExamService`) is
  by `subject_id`. Image directories are keyed by `<stem>` (`5054_*` vs
  `9702_*`), so they never collide.
- **Idempotency.** Papers upsert by the unique `source_file`; questions are
  inserted under a `UNIQUE(paper_id, question_number)` index. A re-import without
  `--fresh` **fails fast** with a clear message rather than duplicating or
  surfacing a raw SQL error.
- **Incomplete source data.** A question that arrives with neither options nor an
  option-table is flagged `needs_review = true`, and `ExamService` only ever
  draws questions with `has('options')`, so a malformed question can never appear
  in a generated test.

### Manually-authored questions

Super-admin CRUD questions attach to a synthetic per-subject paper
`CUSTOM_<code>` (source_file `CUSTOM_<code>.virtual`) so the FK + unique
constraint hold; uploads land under `v2/questions/custom/<id>/` and are the only
files deleted on question delete (imported paper crops are never touched).

### Topic tagging

Questions import with `topic_id = null`; a tagging pass assigns topics.

- **A-Level (9702):** `v2:tag-questions` + `QuestionTopicClassifier` — a tuned,
  hardcoded keyword/score map over the 9702 subtopics, plus
  `v2:apply-topic-overrides` for curated corrections.
- **O-Level (5054) and future subjects:** `v2:tag-by-keywords --subject=<code>`
  — subject-generic. It reads a syllabus JSON
  (`storage/syllabus/physics/OLevels/subject_content_o_level_physics.json`, 23
  topics with weighted keywords), upserts the topics into `v2_topics`, then
  scores each question's text + options + table cells + image captions/OCR and
  assigns the highest-scoring topic (low-confidence → `needs_review`). ~94% of
  5054 questions tag; image-only questions with no text keywords stay untagged.

The question bank's **cascading filters** (Level → Subject → Year → Session →
Variant; Topic ← Level + Subject) only enable a child once its parent is chosen,
so O/A-Level Physics never cross-populate; a bold **Grade** column / gallery
header label distinguishes them.

---

## 2. Rendering design choices (shared across every subject & role)

Three Blade partials render every question — `question_stem`, `question_card`
(pool gallery), `answer_review` (results/papers). Because they are shared, O-Level
renders exactly like A-Level.

### Layout taxonomy (`v2_questions.layout_type`)

Derived from which images a question has — by the importer and by the CRUD:

| layout_type | Meaning |
|---|---|
| `text_only` | Plain stem, text options. |
| `question_diagram` | Stem diagram(s), text options. |
| `option_images` | A picture per A/B/C/D option. |
| `option_table` | The answer set **is a table** — one table image + circles. |
| `question_diagram_and_option_images` | Both a stem diagram and per-option pictures. |

### "Tables and stuff" — the option-table layout

For `option_table` questions the answers are a single table image rendered with
selectable **A / B / C / D circles beside it** (`.v2-table-pick`), the correct
one marked green. The option rows still exist (pipe-delimited cells) so the
correct answer resolves. The super-admin CRUD reproduces this as the *single
answer image* mode (a table **or** graph, type recorded).

### "Same diagram showing for all MCQs"

Some papers print one figure and label the choices A/B/C/D *on* it, which the
extractor captures as four near-identical `option_image` crops. The
**`DuplicateOptionImageFixer`** (run at import with `--copy-images`) detects this
and collapses them to the single figure, reclassifying the question to
`question_diagram` and rendering plain A/B/C/D labels — so the diagram shows
**once**, not four times. `Question::collapsedOptionFigures()` is the render-time
safety net for any unfixed question. This applied to ~124 O-Level questions on
import (option_images 297 → 178), identical to the A-Level behaviour.

### Stem ordering & image sizing

`Question::stemBlocks()` reconstructs reading order: `text_before` → between
diagram(s) → `text_after` → after diagram(s), using bbox gaps. Crops are sized by
`QuestionImage::displayWidth()` (a uniform fraction of the crop's true pixel
width, clamped) so internal label text stays a consistent size across diagrams.

### Question status lifecycle

`v2_questions.status` = `active | draft | under_review | archived` (default
`active`). Only `active` questions are drawn into tests (`Question::scopeActive`);
only non-active questions may be deleted.

---

## 3. Content security — signed image serving

Per the Content-Security spec, no question image is a public static path. Every
crop is fetched through `GET /v2/img` with an HMAC token.

- **Token** = `HMAC-SHA256(user_id | role | exam_id | question_id | expires |
  path)` (`App\Support\SignedImage`). Built by the `simg($path)` Blade helper,
  which every V2 image render uses instead of `asset('storage/…')`.
- **Viewer-bound.** `SecureImageController` verifies the token + expiry + an
  allowed-path prefix, then confirms the request's authenticated V2 actor
  (`v2_actor()`, resolved across all five guards) matches the token's user. A
  harvested link is useless without that user's live session.
- **Expiry.** `SECURE_IMAGE_TTL` (default 6h — long enough for a full exam
  sitting with lazy-loaded images; the viewer-binding is the real control).
- **Headless detection.** UA / `Accept-Language` / `Sec-Fetch-*` heuristics are
  logged for review on a hit — never blocked, so a scraper gets no signal.
- **S3 / R2 fallback.** Set `SECURE_IMAGE_DISK=s3` (`config/secureimages.php`)
  and the controller presigns a short-lived S3 URL instead — **no code change**.
- **Rate limits.** Login throttled 5 / 15 min; `/v2/img` keyed by the *viewer*
  (not IP) so a classroom behind one NAT is never throttled while a single
  bulk-puller is capped.

**Role access to the pool browser** (the full question gallery): Super Admin =
full, Teacher = filtered, Branch/School Admin = none (stats only), Student = none
(only their released exam). Enforced by the existing per-guard routes.

> Not yet built (spec "Month 2", lower priority): canvas rendering + preview
> watermark on pool images, and per-minute velocity limits in the pool browser.
> The signed-URL + rate-limit + headless layers above are the "before first
> school" must-haves.

---

## 4. Test lifecycle — release, schedule, expiry

A teacher-generated test is **not instantly live**.

- Created as a **draft** (`ExamService::generate`) — invisible to students.
- The teacher **releases** it (`PATCH …/exams/{exam}/release`): **now** or at a
  **scheduled** time (`available_from`), with an optional **expiry**
  (`available_until`). After expiry no student can access it.
- State machine: `draft → scheduled → live → expired`
  (`Exam::effectiveStatus()` / `isLive()` / `isScheduled()` / `isExpired()`,
  `scopeAvailable()`). A student may only **take** a live test
  (`ensureCanTake`); a submitted result stays viewable any time.
- **No-attempt** is derived (no extra column): an enrolled student with no
  submitted attempt is shown "Missed" once the test expires, "Not started" while
  live.

Existing exams were migrated to `released` + open-ended, so the demo is
unchanged.
