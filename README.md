# cambpast — Cambridge MCQ PDF extractor

Python-only pipeline that turns Cambridge-style multiple-choice exam PDFs
(e.g. Physics 9702 Paper 1) into structured JSON + cropped image assets
that a future dashboard can import for topical tests, mock exams, and
question banks.

This is **not** a web app, has **no database**, has **no frontend**, and
makes **no network calls**.

The pipeline has two layers:

1. **Deterministic extractor** (`extract.py`) — does all the heavy lifting:
   page render, text/word detection, question detection, option marker
   detection, visual region clustering, **diagram context expansion**, table
   parsing, image cropping, debug rendering. Reproducible and offline.
2. **Curation layer** (`curate.py`) — optional, runs *after* extraction:
   validates the JSON against the cropped images and adds enrichment
   metadata (topic, subtopic, syllabus reference, difficulty, keywords).
   Phase 1 ships with a deterministic `MockProvider`; real models can be
   plugged in via the provider registry without touching the extractor.

---

## Quick start

From the project root (`D:\generative topical\cambpast\`):

```bash
pip install -r requirements.txt

# 1. drop any number of Cambridge MCQ PDFs into input/
python extract.py --input input --output output --dpi 200

# 2. (optional) run the curation pass
python curate.py --output output --provider mock
```

Then point your downstream tool at `output/papers/<paper>/questions.json`
(or `curated_questions.review.json` after curation) and treat the per-paper
`images/` folder as the asset bucket. `output/manifest.json` is the
top-level index across all processed papers.

Tested on Python 3.13, Windows.

---

## Directory map

Everything lives inside `cambpast/`. The whole thing is reproducible —
delete `output/` and re-run; nothing else changes.

| Directory | Purpose | Edit by hand? |
|---|---|---|
| `cambpast/` (root) | Project root. Run commands from here. | — |
| `cambpast/input/` | Drop source PDFs here (any number, any Cambridge MCQ paper). | put files in |
| `cambpast/output/` | Generated. Safe to delete and rebuild. | never |
| `cambpast/output/papers/<pdf-stem>/` | One bundle per processed PDF. | never |
| `cambpast/output/papers/<pdf-stem>/pages/` | Full-page PNGs at the chosen DPI (`page_001.png`, …). | never |
| `cambpast/output/papers/<pdf-stem>/layout/` | Per-page JSON of every word + bbox (debug / re-extraction). | never |
| `cambpast/output/papers/<pdf-stem>/images/` | Cropped, reusable assets — diagrams, graphs, tables, option images. **This is what your dashboard imports.** | never |
| `cambpast/output/papers/<pdf-stem>/debug/` | Annotated PNGs (red question boxes, coloured visual boxes, green option crops, orange option markers). | never |
| `cambpast/samples/` | Reference LLM I/O shapes for the curation pass. | reference |
| `cambpast/src/` | All Python modules. | yes if extending |
| `cambpast/src/curation/` | The optional AI-assisted post-processing layer. | yes if extending |

---

## Top-level files

| File | What it is |
|---|---|
| `extract.py` | Deterministic CLI entry point. Parses `--input`, `--output`, `--dpi` and calls `src.extractor.extract_folder`. **This is what you run first.** |
| `curate.py` | Curation CLI. Runs *after* `extract.py`. Validates and enriches the JSON via a pluggable `LLMProvider`. Default provider is `mock` (deterministic, offline). |
| `requirements.txt` | Pinned dependencies (PyMuPDF, pdfplumber, Pillow, opencv-python, numpy, pandas, optional pytesseract). The project uses stdlib `dataclasses` instead of pydantic. |
| `README.md` | This file. |
| `summary.md.txt` | Original spec brief, kept for reference. |
| `9702_w24_qp_12.pdf` | Sample paper at the root (also copied into `input/`). |

---

## Output files per paper

| Path | What it contains |
|---|---|
| `output/manifest.json` | One entry per processed PDF: paper code, subject, session, total questions, warnings, output dir. |
| `papers/<stem>/questions.json` | The structured truth: paper metadata + every question with text, images, options, option_table, layout_type, captions, `diagram_labels`, warnings. **This is the file the dashboard imports.** |
| `papers/<stem>/curated_questions.review.json` | (Optional, written by `curate.py`.) Wraps `questions.json` with per-question `validation`, `enrichment`, and `review_status`. |
| `papers/<stem>/pages/page_NNN.png` | Page render at the chosen DPI. Source of every crop. |
| `papers/<stem>/layout/page_NNN_layout.json` | All words + bboxes on that page. Lets you re-extract differently without re-rendering. |
| `papers/<stem>/images/qNNN_question_diagram_NN.png` | A question diagram crop, with **Diagram Context Expansion** applied so labels (`1200 N`, `velocity / m s⁻¹`, `p`, `q`, `r`, …) are included. |
| `papers/<stem>/images/qNNN_table_01.png` | An option-table crop (Q1 / Q38 style). Includes the key/legend when present. |
| `papers/<stem>/images/qNNN_option_A.png` (B/C/D) | A single option's image. Produced by **marker-driven crops** for both 2×2 grids (Q8, Q13 style) and horizontal 4-option rows (Q12, Q33 style). |
| `papers/<stem>/debug/page_NNN_debug.png` | Whole page with every detected box overlaid. |
| `papers/<stem>/debug/qNNN_debug.png` | Just the question region with overlays — quickest way to spot a misclassification. |

### JSON schema (per question)

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
  "correct_answer": null,
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
| `src/expansion.py` | **Diagram Context Expansion**: `merge_split_diagrams` (joins graph axes/curve, mic+speaker, etc.) and `expand_diagram_bbox` (grows the crop to include force values, axis ticks, `p`/`q`/`r` markers, table keys). Refuses to grow into another diagram and **rejects sentence-like text** (ends in `.`/`?`, > 5 words, > 60 chars, or starts with `They/The/A/An/It/If/When/Which/...`). |
| `src/options.py` | Finds bold standalone A/B/C/D markers, classifies layout (`horizontal / vertical / grid / partial`), extracts each option's text. Handles Cambridge's quirk of emitting one line-dict per marker on horizontal rows. |
| `src/captions.py` | Rule-based caption generator using a topic keyword table (forces, motion, graph, circuit, oscillation, …). No LLM call — pure regex + templates. |
| `src/debug.py` | Draws coloured bounding boxes onto rendered PNGs (per-page and per-question). Pure Pillow. |
| `src/extractor.py` | The orchestrator. Renders pages, dumps layout JSON, finds question starts, builds (multi-page) question regions, runs marker-driven crops for both 2×2 grids and horizontal rows, classifies remaining visuals, applies expansion, crops and saves assets, generates captions + LLM-ready payloads, builds debug images, writes `questions.json` and `manifest.json`. |

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
      - Find option markers, choose layout (`horizontal / vertical / grid / partial`)
      - **Marker-driven option crops** — first the 2×2 grid case, then the horizontal-row case (additive). Each option gets its own image cropped from the rendered page; visuals overlapping these crops are taken out of the regular classifier so a giant cluster never re-enters the loop.
      - Classify each remaining visual into `option_table` / `option_image` / `question_image_between_text` / `question_image_after_text`
      - **Stage 2 — `expand_diagram_bbox`** for every crop. Attaches `diagram_labels` (text, bbox, confidence) for force values, axis labels, ticks, `p/q/r`, V arrows, table keys
      - Crop the PNG using the **expanded** bbox
      - Generate a rule-based caption + a `caption_llm_ready_payload` for later enrichment
      - Compose `question_text` (excluding text inside visuals **and** short labels aligned with any visual region — keeps axis labels and tick numbers out of the prose)
      - Compute `image_between_question_before_text` / `image_between_question_after_text` if a between-text diagram exists
      - Decide `layout_type`, raise warnings, set `needs_review`
   9. Write debug images per page and per question (with the four green `opt A/B/C/D` boxes overlaid in horizontal layouts)
   10. Write `questions.json`
3. Write `manifest.json`

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
| Vertical options | Q4, Q7, Q10 | `options[*].text` |
| Question diagram between text | Q2, Q3, Q4, Q12 | `question_images_between_text` with `diagram_labels`, plus `image_between_question_before_text` / `image_between_question_after_text` |
| Question diagram after text | Q6, Q8, Q14, Q17 | `question_images_after_text` with `diagram_labels` |
| **2×2 image / graph option grid** | Q8, Q13 | `options[A..D].images[*]`, marker-driven crops |
| **Horizontal 4-option image row** | Q12, Q33 | `options[A..D].images[*]`, marker-driven crops (one per option) |
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

## Marker-driven option crops

Option images are produced by computing a crop bbox per A/B/C/D marker —
not by trying to assign a clustered visual region to a marker. Two cases:

- **2×2 grid (`_compute_grid_option_crops`)** — the four markers form two
  rows. Vertical split at the gap between the two rows; horizontal split at
  the midpoint between the two markers in each row. Used for Q8 / Q13 style.
- **Horizontal row (`_compute_horizontal_option_crops`)** — the four markers
  share a y. `x_left = region left`, dividers at midpoints between adjacent
  markers, `x_right = region right`. `y_top = max(marker bottom) + 2`,
  `y_bot = min(region bottom, max(visual_below.bottom) + 6)`. Used for Q12 /
  Q33 style.

Both cases bypass the "match a clustered visual to a marker" path entirely,
which is what used to produce one giant crop spanning all four options.

---

## Validation & warnings

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
- a visual region could not be classified

Every page also gets a debug PNG with coloured bounding boxes for manual
review. In horizontal-row layouts you'll see four green `opt A/B/C/D` boxes
side by side under the markers.

---

## Curation layer

After `extract.py` finishes, you can optionally run:

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

- **AI / LLM-independent.** The extractor never imports a vendor SDK or makes
  a network call. The curation layer's only built-in provider is `MockProvider`
  — also fully offline. A grep for `openai`, `anthropic`, `requests`,
  `urllib`, `httpx`, `claude`, `gpt-` in `src/` returns only field names,
  comments, and prompt strings. **You can run the entire pipeline offline
  forever.**
- **Python-only.** No JS, no database, no web framework. Pure Python with
  PyMuPDF, pdfplumber, Pillow, opencv-python, numpy, pandas. `pytesseract` is
  listed as an optional fallback only — it is **not** invoked in the current
  code path.

---

## Extending it later

- **Real LLM-backed validation / enrichment:** implement and `register_provider`
  an `AnthropicProvider` or `OpenAIProvider`. The runner code doesn't change.
- **Mark schemes / `correct_answer`:** parse the `er` (examiner report) or `ms`
  (mark scheme) PDF and merge by `paper_code` + `question_number`. Hook into
  `Question.correct_answer`.
- **Real image captions:** feed `caption_llm_ready_payload` (image path +
  nearby text + suggested role) into your captioning model and write the
  result back into the `caption` field.
- **More subjects:** add the subject code to `SUBJECT_CODES` in `src/utils.py`.
  Most layout heuristics carry over.
- **Different DPI:** pass `--dpi 300` for sharper images. Crop quality scales.
- **Admin review UI (Phase 3):** read `curated_questions.review.json`, walk the
  `flagged` items, let the admin edit `enrichment` and toggle `review_status`,
  write `curated_questions.approved.json` for the website importer.

---

## Notes

- Cover, Data, and Formulae pages are auto-detected and excluded from question
  searching.
- Page headers, footers, page numbers, copyright lines, and the vertical
  cover-page barcode are all outside the content rect and never enter the
  pipeline.
- `correct_answer` is left `null`; mark schemes are not handled here.
- Marker-driven crops are intentionally generous on the right and left
  edges (`region.x_left → region.x_right` for the outer crops). If a paper
  uses unusually narrow margins this is fine; the option content is centred
  inside its slice.
