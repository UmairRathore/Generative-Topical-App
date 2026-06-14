# cambpast — Cambridge MCQ PDF extractor

Python-only pipeline that turns Cambridge-style multiple-choice exam PDFs
(e.g. Physics 9702 Paper 1) into structured JSON + cropped image assets
that a future dashboard can import for topical tests, mock exams, and
question banks.

This is **not** a web app, has **no database**, has **no frontend**, and
makes **no network calls**.

---

## Quick start

From the project root (`D:\generative topical\cambpast\`):

```bash
pip install -r requirements.txt
# drop any number of Cambridge MCQ PDFs into input/
python extract.py --input input --output output --dpi 200
```

Then point your downstream tool at `output/papers/<paper>/questions.json`
and treat the per-paper `images/` folder as the asset bucket.
`output/manifest.json` is the top-level index across all processed papers.

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
| `cambpast/output/papers/<pdf-stem>/debug/` | Annotated PNGs (red question boxes, coloured visual boxes, orange option markers). | never |
| `cambpast/src/` | All Python modules. | yes if extending |

---

## Top-level files

| File | What it is |
|---|---|
| `extract.py` | The single CLI entry point. Parses `--input`, `--output`, `--dpi` and calls `src.extractor.extract_folder`. **This is what you run.** |
| `requirements.txt` | Pinned dependencies (PyMuPDF, pdfplumber, Pillow, opencv-python, numpy, pandas, optional pytesseract). The project uses stdlib `dataclasses` instead of pydantic. |
| `README.md` | This file. |
| `summary.md.txt` | Original spec brief, kept for reference. |
| `9702_m24_qp_12.pdf` | Sample paper at the root (also copied into `input/`). |

---

## Output files per paper

| Path | What it contains |
|---|---|
| `output/manifest.json` | One entry per processed PDF: paper code, subject, session, total questions, warnings, output dir. |
| `papers/<stem>/questions.json` | The structured truth: paper metadata + every question with text, images, options, option_table, layout_type, captions, `diagram_labels`, warnings. **This is the file the dashboard imports.** |
| `papers/<stem>/pages/page_NNN.png` | Page render at the chosen DPI. Source of every crop. |
| `papers/<stem>/layout/page_NNN_layout.json` | All words + bboxes on that page. Lets you re-extract differently without re-rendering. |
| `papers/<stem>/images/qNNN_question_diagram_NN.png` | A question diagram crop, with **Diagram Context Expansion** applied so labels (`1200 N`, `velocity / m s⁻¹`, `p`, `q`, `r`, …) are included. |
| `papers/<stem>/images/qNNN_table_01.png` | An option-table crop (Q1 / Q38 style). Includes the key/legend when present. |
| `papers/<stem>/images/qNNN_option_A.png` (B/C/D) | A single option's image (for diagram/graph option grids — Q8, Q13). |
| `papers/<stem>/debug/page_NNN_debug.png` | Whole page with every detected box overlaid. |
| `papers/<stem>/debug/qNNN_debug.png` | Just the question region with overlays — quickest way to spot a misclassification. |

### JSON schema (per question)

```json
{
  "question_number": 8,
  "page_start": 5,
  "page_end": 5,
  "question_text": "Two balls P and Q ...",
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

---

## `src/` modules — what each file does

| Module | Responsibility |
|---|---|
| `src/__init__.py` | Empty package marker. |
| `src/models.py` | Dataclasses for the JSON schema: `Paper`, `Question`, `Option`, `OptionTable`, `ImageAsset`, `DiagramLabel`, `CaptionPayload`, `PaperOutput`. |
| `src/utils.py` | Pure helpers: bbox math (`bbox_union`, `bbox_inside`, `cluster_bboxes`), DPI ↔ pixel scaling, Cambridge filename parser (`9702_m24_qp_12` → Physics, 9702/12, Feb/Mar 2024). |
| `src/visuals.py` | Visual-region detection. Vector drawings via PyMuPDF, raster images via `page.get_image_rects`, glyph/equation fragments dropped, drawings clustered into diagrams, tables via pdfplumber with multi-line cell normalisation. |
| `src/expansion.py` | **Diagram Context Expansion**: `merge_split_diagrams` (joins graph axes/curve, mic+speaker, etc.) and `expand_diagram_bbox` (grows the crop to include force values, axis ticks, `p`/`q`/`r` markers, table keys). Refuses to grow into another diagram or pull paragraph text. |
| `src/options.py` | Finds bold standalone A/B/C/D markers, classifies layout (`horizontal / vertical / grid / partial`), extracts each option's text. Handles Cambridge's quirk of emitting one line-dict per marker on horizontal rows. |
| `src/captions.py` | Rule-based caption generator using a topic keyword table (forces, motion, graph, circuit, oscillation, …). No LLM call — pure regex + templates. |
| `src/debug.py` | Draws coloured bounding boxes onto rendered PNGs (per-page and per-question). Pure Pillow. |
| `src/extractor.py` | The orchestrator. Renders pages, dumps layout JSON, finds question starts, builds (multi-page) question regions, classifies each visual, applies expansion, crops and saves assets, generates captions + LLM-ready payloads, builds debug images, writes `questions.json` and `manifest.json`. |

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
      - Detect 2×2 option-image grids (Q8, Q13 style)
      - Classify each visual into `option_table` / `option_image` / `question_image_between_text` / `question_image_after_text`
      - **Stage 2 — `expand_diagram_bbox`** for every crop. Attaches `diagram_labels` (text, bbox, confidence) for force values, axis labels, ticks, `p/q/r`, V arrows, table keys
      - Crop the PNG using the **expanded** bbox
      - Generate a rule-based caption + a `caption_llm_ready_payload` for later enrichment
      - Compose `question_text` (excluding text inside visuals and below the first option marker)
      - Decide `layout_type`, raise warnings, set `needs_review`
   9. Write debug images per page and per question
   10. Write `questions.json`
3. Write `manifest.json`

---

## Layout cases handled

| Pattern | Example questions | What you get |
|---|---|---|
| Text-only options | Q4, Q7, Q10, Q15 | `options[*].text`, no images |
| Inline horizontal options | Q2, Q5, Q9, Q14 | `options[*].text` |
| Vertical options | Q4, Q7, Q10 | `options[*].text` |
| Question diagram between text | Q2, Q3, Q12 | `question_images_between_text` with `diagram_labels` |
| Question diagram after text | Q6, Q8, Q14, Q17 | `question_images_after_text` with `diagram_labels` |
| Image / graph option grid (2×2) | Q8, Q13 | `options[A..D].images[*]` |
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

   Paragraph-style sentences are rejected (line width > 160 pt, or text ending in `?`),
   so question prompts above a diagram never get pulled into the crop. Expansion stops
   at the question region boundary and refuses to grow into another diagram on the same page.

   Each attached label is recorded in `asset.diagram_labels` as `{ text, bbox, confidence }`.

---

## Validation & warnings

The runner prints warnings when:

- a paper has anything other than 40 detected questions
- a question is missing one of `A/B/C/D`
- an option table looks like it contains tick/cross symbols (saved as a cropped image fallback)
- a visual region could not be classified

Every page also gets a debug PNG with coloured bounding boxes for manual review.

`needs_review = true` is set on questions that produced any warning, or whose
`layout_type` came out as `"mixed"`.

---

## Independence guarantees

- **AI / LLM-independent.** Zero `openai`, `anthropic`, `requests`, `urllib`,
  `httpx`, or any HTTP / SDK call anywhere in `src/`. The only `llm` string in
  the code is the field name `caption_llm_ready_payload` (a payload meant for
  *future* enrichment) and a docstring confirming "No external LLM — leaves
  payloads for later enrichment." Captions are produced by `src/captions.py`,
  which is regex + a topic keyword table. **You can run this offline forever.**
- **Python-only.** No JS, no database, no web framework. Pure Python with
  PyMuPDF, pdfplumber, Pillow, opencv-python, numpy, pandas. `pytesseract` is
  listed as an optional fallback only — it is **not** invoked in the current
  code path.

---

## Extending it later

- **Mark schemes / `correct_answer`:** parse the `er` (examiner report) or `ms`
  (mark scheme) PDF and merge by `paper_code` + `question_number`. Hook into
  `Question.correct_answer`.
- **Real captions:** feed `caption_llm_ready_payload` (image path + nearby text
  + suggested role) into your captioning model and write the result back into
  the `caption` field. The pipeline never has to know.
- **More subjects:** add the subject code to `SUBJECT_CODES` in `src/utils.py`.
  Most layout heuristics carry over.
- **Different DPI:** pass `--dpi 300` for sharper images. Crop quality scales.

---

## Notes

- Cover, Data, and Formulae pages are auto-detected and excluded from question
  searching.
- Page headers, footers, page numbers, copyright lines, and the vertical
  cover-page barcode are all outside the content rect and never enter the
  pipeline.
- `correct_answer` is left `null`; mark schemes are not handled here.
