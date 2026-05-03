# cambpast — Cambridge MCQ PDF extractor

Python-only pipeline that turns Cambridge-style multiple-choice exam PDFs
(e.g. Physics 9702 Paper 1) into structured JSON + cropped image assets +
correct-answer mappings + a production-grade QA report — ready for a
question-bank dashboard to import.

This is **not** a web app, has **no database**, has **no frontend**, and
makes **no network calls**.

The pipeline has four layers — each is a single CLI script you can run
independently:

1. **Deterministic extractor** (`extract.py`) — page render, text/word
   detection, question detection, option-marker detection, visual-region
   clustering, **diagram context expansion**, **marker-band option-text
   recovery**, **force-split repair** for misclassified vertical /
   horizontal image-option blocks, table parsing, image cropping, debug
   rendering. Reproducible and offline.
2. **Mark scheme mapper** (`extract_marks.py`) — reads each
   `papers/marksheets/*.pdf`, extracts `{qno: A/B/C/D}` from both modern
   ("Question | Answer | Marks" rows) and old (two side-by-side columns)
   layouts, and writes `correct_answer` into the matching
   `output/papers/<stem>/questions.json`.
3. **Curation layer** (`curate.py`) — optional, runs *after* extraction.
   Validates the JSON against the cropped images and adds enrichment
   metadata (topic, subtopic, syllabus reference, difficulty, keywords).
   Phase 1 ships with a deterministic `MockProvider`; real models can be
   plugged in via the provider registry without touching the extractor.
4. **Production QA & blocker worklist** (`qa_report.py` + the analyser
   in `.cache/build_blocker_worklist.py`) — severity-tiered batch report
   over `output/papers/`. Writes `qa_summary.{json,md}`,
   `problem_cases.{json,md}`, `blocker_worklist.{json,md}`, and
   `marks_qa.{json,md}`. Used to gate "is this batch publishable?".

---

## Quick start

From the project root (`D:\generative topical\cambpast\`):

```bash
pip install -r requirements.txt

# 1. drop any number of Cambridge MCQ PDFs into input/
python extract.py --input input --output output --dpi 200

# 2. drop the matching mark scheme PDFs into papers/marksheets/
#    (filenames must follow the Cambridge convention — eg
#     question paper 9702_m24_qp_12.pdf  ↔  mark scheme 9702_m24_ms_12.pdf)
python extract_marks.py --marks papers/marksheets --output output

# 3. (optional) run the curation pass
python curate.py --output output --provider mock

# 4. run the production QA + regenerate the blocker worklist
python qa_report.py --output output --expected 40
python .cache/build_blocker_worklist.py
```

After step 1+2 the dashboard can already render every clean question with
clickable A/B/C/D and the right answer marked. The QA layer is just
gating — it tells you which questions still need an extractor fix, an
admin edit, or a render-side fallback.

Tested on Python 3.13, Windows.

---

## Directory map

Everything lives inside `cambpast/`. The whole thing is reproducible —
delete `output/` and re-run; nothing else changes.

| Directory | Purpose | Edit by hand? |
|---|---|---|
| `cambpast/` (root) | Project root. Run commands from here. | — |
| `cambpast/input/` | Drop source question-paper PDFs here. | put files in |
| `cambpast/papers/marksheets/` | Drop mark-scheme PDFs here. Filenames must match the question paper with `_qp_` → `_ms_`. | put files in |
| `cambpast/input_extras/` | Optional — papers you've staged but don't want in the main batch. The QA report ignores this folder. | put files in |
| `cambpast/output/` | Generated. Safe to delete and rebuild. | never |
| `cambpast/output/papers/<pdf-stem>/` | One bundle per processed PDF. | never |
| `cambpast/output/papers/<pdf-stem>/pages/` | Full-page PNGs at the chosen DPI (`page_001.png`, …). | never |
| `cambpast/output/papers/<pdf-stem>/layout/` | Per-page JSON of every word + bbox (debug / re-extraction). | never |
| `cambpast/output/papers/<pdf-stem>/images/` | Cropped, reusable assets — diagrams, graphs, tables, option images. **This is what your dashboard imports.** | never |
| `cambpast/output/papers/<pdf-stem>/debug/` | Annotated PNGs (red question boxes, coloured visual boxes, green option crops, orange option markers). | never |
| `cambpast/output/qa/` | All QA artefacts written by `qa_report.py`, `extract_marks.py`, and `.cache/build_blocker_worklist.py`. | never |
| `cambpast/samples/` | Reference LLM I/O shapes for the curation pass. | reference |
| `cambpast/src/` | All Python modules. | yes if extending |
| `cambpast/src/curation/` | The optional AI-assisted post-processing layer. | yes if extending |
| `cambpast/.cache/` | One-off helper scripts that aren't part of the runtime pipeline. | yes if extending |

---

## Top-level files

| File | What it is |
|---|---|
| `extract.py` | Deterministic CLI entry point. Parses `--input`, `--output`, `--dpi` and calls `src.extractor.extract_folder`. **Run this first.** |
| `extract_marks.py` | Mark-scheme CLI. Parses `--marks`, `--output`, `--dry-run`. Reads every `<stem>_ms_<n>.pdf`, extracts answers via PyMuPDF word-bbox geometry (handles modern + old layouts), writes `correct_answer` into the matching `questions.json`, then emits `output/marks_manifest.json` and `output/qa/marks_qa.{json,md}`. |
| `curate.py` | Curation CLI. Runs *after* `extract.py`. Validates and enriches the JSON via a pluggable `LLMProvider`. Default provider is `mock` (deterministic, offline). |
| `qa_report.py` | Production QA. Walks `output/papers/<stem>/questions.json`, classifies every question into severity tiers (`PASS / REVIEW / RENDER_FIX / ACCEPTABLE_FALLBACK / BLOCKER`), and emits `qa_summary.{json,md}` + `problem_cases.{json,md}`. |
| `.cache/build_blocker_worklist.py` | Reads `output/qa/problem_cases.json`, filters to BLOCKER questions, groups by root cause, recommends a resolution path per blocker, writes `blocker_worklist.{json,md}`. |
| `requirements.txt` | Pinned dependencies (PyMuPDF, pdfplumber, Pillow, opencv-python, numpy, pandas, optional pytesseract). The project uses stdlib `dataclasses` instead of pydantic. |
| `README.md` | This file. |
| `summary.md.txt` | Original spec brief, kept for reference. |
| `9702_w24_qp_12.pdf` | Sample paper at the root (also copied into `input/`). |

---

## Output files per paper

| Path | What it contains |
|---|---|
| `output/manifest.json` | One entry per processed PDF: paper code, subject, session, total questions, warnings, output dir. |
| `output/marks_manifest.json` | One entry per mark-scheme PDF processed: `paper_stem`, extracted answer count, the `{qno: letter}` map, page count. |
| `papers/<stem>/questions.json` | The structured truth: paper metadata + every question with text, images, options, option_table, layout_type, captions, `diagram_labels`, `correct_answer`, warnings. **This is the file the dashboard imports.** |
| `papers/<stem>/curated_questions.review.json` | (Optional, written by `curate.py`.) Wraps `questions.json` with per-question `validation`, `enrichment`, and `review_status`. |
| `papers/<stem>/pages/page_NNN.png` | Page render at the chosen DPI. Source of every crop. |
| `papers/<stem>/layout/page_NNN_layout.json` | All words + bboxes on that page. Lets you re-extract differently without re-rendering. |
| `papers/<stem>/images/qNNN_question_diagram_NN.png` | A question-diagram crop, with **Diagram Context Expansion** applied so labels (`1200 N`, `velocity / m s⁻¹`, `p`, `q`, `r`, …) are included. |
| `papers/<stem>/images/qNNN_table_01.png` | An option-table crop (Q1 / Q38 style). Includes the key/legend when present. |
| `papers/<stem>/images/qNNN_option_A.png` (B/C/D) | A single option's image. Produced by **marker-driven crops** for 2×2 grids (Q8 / Q13), horizontal 4-option rows (Q12 / Q33), **vertical 4×1 image-option blocks** (Q6 / Q33 of the w14 series), and **horizontal force-split** when the visual encompasses or partially covers the marker row. |
| `papers/<stem>/debug/page_NNN_debug.png` | Whole page with every detected box overlaid. |
| `papers/<stem>/debug/qNNN_debug.png` | Just the question region with overlays — quickest way to spot a misclassification. |

### Output QA files (under `output/qa/`)

| File | Source | What it contains |
|---|---|---|
| `qa_summary.json` | `qa_report.py` | Per-paper rows + batch metrics (pass/review/failed counts, severity-tier question counts, `estimated_demo_safe_question_count`, `estimated_success_rate_percent`). |
| `qa_summary.md` | `qa_report.py` | Same, grouped Failed → Review → Clean, with category lists per paper. |
| `problem_cases.json` | `qa_report.py` | Flat list of every problem question with reasons, severity, warnings, asset paths, debug image path. |
| `problem_cases.md` | `qa_report.py` | Same, organised by paper. |
| `blocker_worklist.json` | `.cache/build_blocker_worklist.py` | BLOCKER-only list with root-cause classification + per-question `recommended_resolution`: `extractor_fix / use_full_question_crop_fallback / manual_json_fix / hide_from_publish`. |
| `blocker_worklist.md` | same | Same, grouped by root cause. |
| `marks_qa.json` | `extract_marks.py` | Per-paper mark-scheme QA: `extracted` / `mapped_to_questions` counts, missing question numbers, invalid answers, `papers_without_mark_scheme`. |
| `marks_qa.md` | same | Same, grouped Failed → Partial → Pass. |

---

## JSON schema (per question)

```json
{
  "question_number": 8,
  "page_start": 5,
  "page_end": 5,
  "question_text": "Two balls P and Q ...",
  "image_between_question_before_text": null,
  "image_between_question_after_text": null,
  "question_images_between_text": [],
  "question_images_after_text": [
    {
      "id": "q008_question_diagram_01",
      "image_path": "papers/9702_m24_qp_12/images/q008_question_diagram_01.png",
      "page": 5,
      "bbox": [222.2, 104.9, 277.0, 169.8],
      "role": "question_image_after_text",
      "caption": "Question diagram (collision) after text: ...",
      "ocr_text": "",
      "confidence": 0.7,
      "diagram_labels": [
        { "text": "1.30 m s–1", "bbox": [...], "confidence": 0.9 }
      ]
    }
  ],
  "options": [
    { "label": "A", "text": "", "images": [ /* ImageAsset */ ] },
    { "label": "B", "text": "", "images": [ ... ] },
    { "label": "C", "text": "", "images": [ ... ] },
    { "label": "D", "text": "", "images": [ ... ] }
  ],
  "option_table": null,
  "assets": [ /* flat list of every image for this question */ ],
  "correct_answer": "D",
  "layout_type": "question_diagram_and_option_images",
  "needs_review": false,
  "warnings": [],
  "caption_llm_ready_payload": [
    {
      "image_path": "...",
      "nearby_text": "...",
      "question_number": 8,
      "option_label": "A",
      "suggested_role": "option_image"
    }
  ]
}
```

`layout_type` is one of:
`text_only | question_diagram | option_table | option_images | question_diagram_and_option_images | mixed`.

`correct_answer` is filled in by `extract_marks.py` (initial value: `null`).

### Layout reconstruction fields

When a question's diagram sits *between* two pieces of text (Q4 in
`9702_w24_qp_12`, for example), two scalar fields make the original page
layout reproducible:

| Field | Value when no between-text diagram | Value otherwise |
|---|---|---|
| `image_between_question_before_text` | `null` | text before the diagram |
| `image_between_question_after_text` | `null` | text after the diagram, before the options |

`before + " " + after == question_text` (with the cut at the diagram's
post-expansion bottom edge, page-aware for multi-page questions).

---

## `src/` modules — what each file does

### Extractor (deterministic)

| Module | Responsibility |
|---|---|
| `src/__init__.py` | Empty package marker. |
| `src/models.py` | Dataclasses for the JSON schema: `Paper`, `Question`, `Option`, `OptionTable`, `ImageAsset`, `DiagramLabel`, `CaptionPayload`, `PaperOutput`. |
| `src/utils.py` | Pure helpers: bbox math (`bbox_union`, `bbox_inside`, `cluster_bboxes`), DPI ↔ pixel scaling, Cambridge filename parser (`9702_m24_qp_12` → Physics, 9702/12, Feb/Mar 2024). |
| `src/visuals.py` | Visual-region detection. Vector drawings via PyMuPDF, raster images via `page.get_image_rects`, glyph/equation fragments dropped, drawings clustered into diagrams, tables via pdfplumber with multi-line cell normalisation. |
| `src/expansion.py` | **Diagram Context Expansion**: `merge_split_diagrams` (joins graph axes/curve, mic+speaker, etc.) and `expand_diagram_bbox` (grows the crop to include force values, axis ticks, `p/q/r` markers, table keys). Refuses to grow into another diagram and **rejects sentence-like text** (ends in `.`/`?`, > 5 words, > 60 chars, or starts with `They/The/A/An/It/If/When/Which/...`). |
| `src/options.py` | Finds bold standalone A/B/C/D markers via combinatorial alignment scoring (rejects unit symbols like `0.15 A` and false A/B/C/D inside diagrams), classifies layout (`horizontal / vertical / grid / partial`), extracts each option's text. Also exposes `recover_option_text_from_marker_bands` — a wider-y-band fallback used when the primary extractor returns empty for an option (catches stacked fractions, multi-line wraps, clean 2×2 grid layouts). |
| `src/captions.py` | Rule-based caption generator using a topic keyword table (forces, motion, graph, circuit, oscillation, …). No LLM call — pure regex + templates. |
| `src/debug.py` | Draws coloured bounding boxes onto rendered PNGs (per-page and per-question). Pure Pillow. |
| `src/extractor.py` | The orchestrator. Renders pages, dumps layout JSON, finds question starts, builds (multi-page) question regions, runs marker-driven crops for 2×2 grids and horizontal rows, runs the **post-classification force-split repair** (vertical and horizontal) for cases where a single visual was misclassified as a question diagram, classifies remaining visuals, applies expansion, crops and saves assets, generates captions + LLM-ready payloads, builds debug images, writes `questions.json` and `manifest.json`. |

### Curation (optional, AI-assisted)

| Module | Responsibility |
|---|---|
| `src/curation/__init__.py` | Empty package marker. |
| `src/curation/schemas.py` | Dataclasses for the curated JSON: `ValidationIssue`, `ValidationResult`, `Enrichment`, `CuratedQuestion`, `CurationMetadata`, `CuratedPaper`. Plus the closed set of issue codes (`MISSING_QUESTION_TEXT`, `MISSING_OPTION`, `WRONG_OPTION_IMAGE`, …). |
| `src/curation/provider.py` | `LLMProvider` ABC + `MockProvider` + a registry. `MockProvider` produces realistic placeholder validation/enrichment by inspecting inputs only — no model calls. Real providers (Anthropic, OpenAI, local) plug in via `register_provider`. |
| `src/curation/prompts.py` | Prompt templates and JSON schemas (`validation_result.v1`, `enrichment.v1`). |
| `src/curation/validator.py` | `validate_question(...)`. Builds the user prompt, attaches debug + asset images, calls the provider, parses the result. Falls back to `needs_review` with code `PROVIDER_BAD_JSON` if the model is malformed. |
| `src/curation/enricher.py` | `enrich_question(...)`. Same pattern; falls back to a blank `Enrichment` so admins can fill it manually. |
| `src/curation/runner.py` | `curate_paper` / `curate_all`. Reads `questions.json`, runs both passes, writes `curated_questions.review.json`. Preserves `admin_approved` / `rejected` items unchanged unless `--force` is set. |

---

## Pipeline (one PDF, top to bottom)

1. `extract.py` → `extract_folder(input, output, dpi)`
2. For each PDF: `PaperExtractor.run()`
   1. Render every page (`fitz`) → `pages/page_NNN.png`
   2. Dump every word + bbox → `layout/page_NNN_layout.json`
   3. Build per-page info: spans, lines, content rect, classify cover / data / formulae pages so they're skipped
   4. Detect visuals + tables → merge them
   5. **Stage 1 — `merge_split_diagrams`** per page (a graph reported as separate axes/curve clusters becomes one region; mic + speaker become one apparatus)
   6. Find bold standalone digits 1..40 at the left margin → question starts (greedy match `1, 2, 3, …`)
   7. Build question regions (multi-page slices when a question crosses a page break)
   8. For each question:
      - Find option markers (combinatorial alignment scoring rejects unit symbols and diagram-internal A/B/C/D), choose layout (`horizontal / vertical / grid / partial`)
      - **Marker-driven option crops** — first the 2×2 grid case, then the horizontal-row case (additive). Each option gets its own image cropped from the rendered page; visuals overlapping these crops are taken out of the regular classifier so a giant cluster never re-enters the loop.
      - Classify each remaining visual into `option_table` / `option_image` / `question_image_between_text` / `question_image_after_text`
      - **Force-split repair pass** (post-classification) — fires when an option layout came back under-supplied:
        - **Group A — vertical 4×1**: 4 markers stacked at the left margin and a tall block (or 4 per-option visuals) to the right. The block is sliced horizontally per marker into 4 option images, and the misclassified `question_image_*` is removed.
        - **Group B — horizontal row, partially split**: 4 horizontal markers with 1–3 option images already produced. The visual zone is sliced into 4 column crops based on marker x positions. When the visual *encompasses* the markers (option diagrams sit both above and below the marker letter), the crop's `y_top` extends upward to capture the full option content.
      - **Stage 2 — `expand_diagram_bbox`** for every question-diagram and option-table crop. Attaches `diagram_labels` (text, bbox, confidence) for force values, axis labels, ticks, `p/q/r`, V arrows, table keys
      - Crop the PNG using the **expanded** bbox
      - Generate a rule-based caption + a `caption_llm_ready_payload` for later enrichment
      - Compose `question_text` (excluding text inside visuals **and** short labels aligned with any visual region — keeps axis labels and tick numbers out of the prose)
      - **Marker-band recovery** for any option that came back empty — a wider y-band fallback that catches stacked fractions, multi-line wrapped text, and clean 2×2 grid layouts. Filtered by visual-region membership so it never sweeps diagram-interior text into option text.
      - Compute `image_between_question_before_text` / `image_between_question_after_text` if a between-text diagram exists
      - Decide `layout_type`, raise warnings, set `needs_review`
   9. Write debug images per page and per question (with the four green `opt A/B/C/D` boxes overlaid in horizontal/vertical layouts)
   10. Write `questions.json`
3. Write `manifest.json`

If you then run `python extract_marks.py --marks papers/marksheets --output output`,
each `questions.json` gets `correct_answer` populated.

If you then run `python curate.py --output output --provider mock`, the
curation layer reads each `questions.json`, calls
`MockProvider.complete_json(...)` once per question per pass, and writes
`curated_questions.review.json` next to it. No network. No SDK.

---

## Layout cases handled

| Pattern | Example questions | What you get |
|---|---|---|
| Text-only options | Q4, Q7, Q10, Q15 | `options[*].text`, no images |
| Inline horizontal options (text) | Q2, Q5, Q9, Q14 | `options[*].text` |
| Vertical options (text) | Q4, Q7, Q10 | `options[*].text` |
| Inline options with stacked fractions | s20_qp_11 Q9, m17_qp_12 Q9 | `options[*].text` (recovered via wider y-band fallback) |
| Question diagram between text | Q2, Q3, Q4, Q12 | `question_images_between_text` with `diagram_labels`, plus `image_between_question_before_text` / `image_between_question_after_text` |
| Question diagram after text | Q6, Q8, Q14, Q17 | `question_images_after_text` with `diagram_labels` |
| **2×2 image / graph option grid** | Q8, Q13 | `options[A..D].images[*]`, marker-driven crops |
| **Horizontal 4-option image row** | Q12, Q33 | `options[A..D].images[*]`, marker-driven crops (one per option) |
| **Vertical 4×1 image-option block** | m19 Q6, w14_qp_12 Q33, w17_qp_13 Q30 | `options[A..D].images[*]`, force-split horizontally per marker |
| **Horizontal row, partial split** | w12_qp_11 Q13, w10_qp_12 Q27 | force-split into 4 column crops, even when the visual encompasses the marker row |
| Option table | Q1, Q32, Q35 | `option_table.headers/rows`, cropped image |
| Option table with tick / cross | Q38 | cropped table image (key/legend included via expansion) + warning |
| Multi-page question | as needed | `page_start < page_end`, slices merged transparently |

---

## Diagram Context Expansion (the important bit)

Raw vector clusters from PyMuPDF are usually too tight — a graph crops without
its axis labels, a force diagram crops without `1200 N`. We add two stages:

1. **`merge_split_diagrams`** (per page, before classification)
   Two regions merge when:
   - they overlap ≥ 40 % in the y dimension and are within 35 pt horizontally, **or**
   - they overlap ≥ 40 % in the x dimension and are within 18 pt vertically.

   Tables are never merged with non-tables.

2. **`expand_diagram_bbox`** (per asset, after classification)
   Grows the crop to absorb nearby short labels:
   - Text on the left/right that y-overlaps ≥ 30 % and is within 70–80 pt horizontally
   - Text above/below that x-overlaps ≥ 30 % and is within 30–35 pt vertically
   - Touching-corner labels (≤ 6 pt gap)

   Paragraph-style text is rejected: line width > 160 pt, ends in `.`/`?`,
   > 60 chars, > 5 words, or starts with `They/The/A/An/It/If/When/Which/...`
   — so question prompts and inline sentences never get pulled into the crop.
   Expansion stops at the question region boundary and refuses to grow into
   another diagram on the same page.

   Each attached label is recorded in `asset.diagram_labels` as
   `{ text, bbox, confidence }`.

The same alignment-based proximity test is reused inside
`_compose_question_lines` so axis labels (`acceleration`, `time`),
tick numbers (`0`, `0.5`, `1.0`), and `p/q/r` markers stay out of
`question_text`.

---

## Marker-driven option crops + force-split repair

Option images are produced by computing a crop bbox per A/B/C/D marker —
not by trying to assign a clustered visual region to a marker. Four cases:

- **2×2 grid (`_compute_grid_option_crops`)** — the four markers form two
  rows. Vertical split at the gap between the two rows; horizontal split at
  the midpoint between the two markers in each row. Used for Q8 / Q13 style.
- **Horizontal row (`_compute_horizontal_option_crops`)** — the four markers
  share a y. `x_left = region left`, dividers at midpoints between adjacent
  markers, `x_right = region right`. `y_top = max(marker bottom) + 2`,
  `y_bot = min(region bottom, max(visual_below.bottom) + 6)`. Used for Q12 /
  Q33 style.
- **Vertical 4×1 force-split (`_compute_vertical_option_crops`)** — the four
  markers stack at the left margin. The block to their right is sliced
  horizontally per marker (`y_top = marker.y - 4`,
  `y_bot = next marker.y - 4`). Two sub-cases: one tall block (m19 Q6) or
  several per-option visuals (s15_qp_11 Q29).
- **Horizontal force-split** — same idea as the row case, but fires *after*
  classification when 1–3 option images came back from heuristic matching.
  When the source visual encompasses the marker row, `y_top` is extended
  upward to capture option content above the markers.

All four cases bypass the "match a clustered visual to a marker" path
entirely, which is what used to produce one giant crop spanning all four
options. The force-split passes also remove the misclassified
`question_image_after_text` asset whose bbox overlaps the source visual,
so the diagram never appears twice.

---

## Marks extraction (`extract_marks.py`)

Cambridge MCQ mark schemes use one of two layouts:

- **Modern (~2016+)** — three columns per row: `Question | Answer | Marks`.
- **Old (~2010–2015)** — two side-by-side `(Number, Answer)` columns,
  20 questions in each.

The extractor uses **PyMuPDF word boxes** only — no font/text heuristics.
For every digit token in `[1, 40]` we find the nearest `A/B/C/D` letter to
its right within `±10 pt` of y and ≤ `220 pt` of x. The 220 pt cap is small
enough to keep a left-column number from pairing with a right-column letter
in the old layout. The same algorithm handles both layouts.

After extraction the script:

1. Writes `correct_answer` into each question of
   `output/papers/<stem>/questions.json` (matching by stem: `_qp_` ↔ `_ms_`).
2. Writes `output/marks_manifest.json` (per-paper extraction summary).
3. Writes `output/qa/marks_qa.{json,md}` — a QA pass that flags partial
   extractions, missing mark schemes, and invalid answers.

```bash
python extract_marks.py --marks papers/marksheets --output output
# add --dry-run to see the mapping without modifying any questions.json
```

Latest production run (108 mark scheme PDFs, 107 papers):

| Metric | Value |
|---|---:|
| Mark scheme PDFs seen | 108 |
| **PASS (40/40 answers extracted and mapped)** | **107** |
| Partial | 0 |
| Failed (no matching `questions.json`) | 1 |
| Total answers extracted | 4 320 |
| Total answers mapped into `questions.json` | **4 280** (= 107 × 40) |
| Papers without a mark scheme | 0 |

---

## Production QA & blocker worklist (`qa_report.py` + `.cache/build_blocker_worklist.py`)

`qa_report.py` walks `output/papers/<stem>/questions.json` and assigns each
question a **severity tier**:

| Severity | When it fires | What to do about it |
|---|---|---|
| `PASS` | No issues. | ship as-is |
| `REVIEW` | Spot-check needed but probably usable: option_image crop edge / size, `repeated_axis_label_option_text`, `crop_skipped`, mixed layout that has at least some text/images. | admin glances at the debug PNG |
| `RENDER_FIX` | `question_text_contains_option_text` (the extractor folded the inline option list into the prose). The dashboard renderer can strip the duplication on import. | renderer-side cleanup, no extractor work |
| `ACCEPTABLE_FALLBACK` | Option-table fallback (tick/cross / unreadable cells) **with a cropped table image on disk**. | dashboard shows the cropped table image |
| `BLOCKER` | Cannot be safely published without an extractor or manual fix: missing options, empty options *with no usable image fallback*, image-option layout with < 4 image crops, giant crop spanning multiple options, misclassified option-image block, mixed layout with empty options, missing A/B/C/D. | route via the worklist below |

`qa_summary.json` exposes batch-level metrics:

- `total_papers`, `total_questions`, `failed_papers`
- `blocked_papers / review_papers / render_fix_only_papers / fallback_ok_papers / pass_papers`
- `blocker_question_count / render_fix_question_count / review_question_count / acceptable_fallback_question_count / pass_question_count`
- `estimated_demo_safe_question_count = total_questions − BLOCKER − FAILED-paper questions`
- `estimated_success_rate_percent = demo_safe / total × 100`
- `target_success_rate_percent` (default `98.0`)

Latest production batch (107 papers, 4 280 questions):

```
total_papers: 107
total_questions: 4280
failed_papers: 0
papers with total_questions != 40: 0
blocker_question_count: 28
estimated_demo_safe_question_count: 4252
estimated_success_rate_percent: 99.35
blocker_rate_percent: 0.65
```

`.cache/build_blocker_worklist.py` then reads `problem_cases.json`,
filters to BLOCKERs, groups by root cause, and recommends one of four
resolution paths per blocker:

| Resolution | Meaning |
|---|---|
| `extractor_fix` | A clear pattern — one global change clears the cluster. |
| `use_full_question_crop_fallback` | A usable question-diagram crop exists on disk. The renderer shows that crop with A/B/C/D as overlay click-targets. |
| `manual_json_fix` | Idiosyncratic case — faster to hand-edit `questions.json`. |
| `hide_from_publish` | Last resort: no fix path, no usable image. Currently 0 questions. |

Latest worklist totals (28 BLOCKERs):

| Group | Count |
|---|---:|
| `empty_options` | 27 |
| `mixed_layout` | 6 |
| `short_image_option` | 1 |
| `horizontal_giant_crop` | 1 |
| `missing_options` | 1 |

| Recommended resolution | Count |
|---|---:|
| `use_full_question_crop_fallback` | 20 |
| `manual_json_fix` | 7 |
| `extractor_fix` | 1 |
| `hide_from_publish` | 0 |

---

## Validation & warnings emitted by the extractor

The runner prints warnings and sets `needs_review = true` when:

- a paper has anything other than 40 detected questions
- a question is missing one of `A/B/C/D`
- an option table looks like it contains tick/cross symbols (saved as a cropped image fallback)
- in an image-option layout, any A/B/C/D has neither text nor any image
  (`Option X appears empty in image-option layout.`)
- the image-option row produces fewer than 4 crops
  (`Image-option row has N crop(s); expected 4.`)
- a heuristic-matched option image bbox covers another marker's centre x
  (`Option image for X appears to span multiple options (Y, Z).`)
- an option image is suspiciously wide (≥ 75 % of question region width)
  (`Option image for X is suspiciously wide (Wpt / Spt of question region).`)
- marker-band recovery still left an option empty
  (`Option X remained empty after marker-band recovery.`)
- a visual region could not be classified

Every page also gets a debug PNG with coloured bounding boxes for manual
review. In horizontal-row and vertical-block layouts you'll see four green
`opt A/B/C/D` boxes side by side under (or beside) the markers.

The semantic detectors in `qa_report.py` emit additional signals that the
extractor itself cannot warn about:
`probable_option_images_misclassified_as_question_diagram`,
`repeated_axis_label_option_text`,
`question_text_contains_option_text`,
`option_image_crop_needs_visual_review`.

---

## Curation layer

After `extract.py` + `extract_marks.py` finish, you can optionally run:

```bash
python curate.py --output output --provider mock           # all papers
python curate.py --paper output/papers/<stem>               # one paper
python curate.py --output output --provider mock --force    # overwrite admin-approved
```

This reads `questions.json` and writes `curated_questions.review.json`
beside it. Each question gets:

- `extracted` — the original Question, untouched
- `validation` — `{status, confidence, issues[], checked_assets}` from the provider
- `enrichment` — `{topic, subtopic, syllabus_ref, concepts, difficulty, keywords, …}`
- `review_status` — `extracted | ai_reviewed | flagged | admin_approved | rejected`
- `admin_notes` — empty string, ready for the admin UI

`MockProvider` is fully deterministic and offline — useful for wiring up the
admin review UI before touching a real model. To plug in a real model:

```python
from src.curation.provider import register_provider, LLMProvider, ProviderInfo

class AnthropicProvider(LLMProvider):
    info = ProviderInfo(name="anthropic", model="claude-opus-4-7")
    def complete_json(self, system, user, image_paths, json_schema):
        # lazy-import anthropic, attach images, force the JSON schema via tool use
        ...

register_provider("anthropic", AnthropicProvider)
```

Then `python curate.py --provider anthropic`. The validator/enricher/runner
code stays identical.

The reference shape of the LLM I/O for both passes lives in
`samples/validation_io_example.json`.

---

## Independence guarantees

- **AI / LLM-independent.** The extractor, marks mapper, and QA layer never
  import a vendor SDK or make a network call. The curation layer's only
  built-in provider is `MockProvider` — also fully offline. A grep for
  `openai`, `anthropic`, `requests`, `urllib`, `httpx`, `claude`, `gpt-`
  in `src/`, `extract_marks.py`, and `qa_report.py` returns only field
  names, comments, and prompt strings. **You can run the entire pipeline
  offline forever.**
- **Python-only.** No JS, no database, no web framework. Pure Python with
  PyMuPDF, pdfplumber, Pillow, opencv-python, numpy, pandas. `pytesseract`
  is listed as an optional fallback only — it is **not** invoked in the
  current code path.

---

## Extending it later

- **Real LLM-backed validation / enrichment:** implement and `register_provider`
  an `AnthropicProvider` or `OpenAIProvider`. The runner code doesn't change.
- **Real image captions:** feed `caption_llm_ready_payload` (image path +
  nearby text + suggested role) into your captioning model and write the
  result back into the `caption` field.
- **More subjects:** add the subject code to `SUBJECT_CODES` in `src/utils.py`.
  Most layout heuristics carry over.
- **Different DPI:** pass `--dpi 300` for sharper images. Crop quality scales.
- **Admin review UI:** read `curated_questions.review.json`, walk the
  `flagged` items, let the admin edit `enrichment` and toggle
  `review_status`, write `curated_questions.approved.json` for the website
  importer.
- **Additional QA signals:** add a flag + reason to `_question_problems` in
  `qa_report.py` and a recommended resolution in
  `.cache/build_blocker_worklist.py`. Both are additive — they don't alter
  the extracted JSON.

---

## Notes

- Cover, Data, and Formulae pages are auto-detected and excluded from question
  searching.
- Page headers, footers, page numbers, copyright lines, and the vertical
  cover-page barcode are all outside the content rect and never enter the
  pipeline.
- Marker-driven and force-split crops are intentionally generous on the right
  and left edges (`region.x_left → region.x_right` for the outer crops). If a
  paper uses unusually narrow margins this is fine; the option content is
  centred inside its slice.
- `correct_answer` is populated by `extract_marks.py`. If you skip that step
  every value stays `null`, which is also valid.
- The QA report only counts papers that actually have an `output/papers/<stem>/`
  directory — stale `manifest.json` entries from a previous batch are
  ignored unless they explicitly recorded an extraction error.
