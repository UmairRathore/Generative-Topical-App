# Complete QA Pipeline — Development Lifecycle & Institutional Memory

**Scope so far: all 19 s20–s25 papers FULLY COMPLETE (verified on disk 2026-06-12).**
QA artifacts live in the gitignored `output/papers/<paper>/` folders: corrected crops in
`images_fix/`, audit trail in `claude_log.txt` (dated sections: original pass 2026-05-07,
Re-verification 2026-06-11, Coverage extension / Deep re-check 2026-06-11/12).

## Final audit numbers (verified against disk)

| Metric | Count |
|---|---|
| Papers completed (s20–s25) | 19 |
| Original images in `images/` (never modified) | 746 |
| Logical images incl. fix-only additions | 765 |
| Files in `images_fix/` across the 19 papers | 436 |
| Re-verification fixes | 102 |
| Coverage fixes s20–s22 | 18 |
| Coverage + deep re-check fixes s23–s25 | 44 (35 re-crops + 9 new extractions) |
| Fix-only files reconciled into questions.json | 19 |
| Corpus | 108 papers total → **89 remaining** |

## Manual QA lifecycle (sessions 1–4)

1. **Re-verification** — every existing `images_fix/` file individually read and judged
   against the 7 crop criteria; bad fixes re-cropped from the 200 DPI `pages/` renders.
   374 checked, **102 re-cropped**.
2. **Coverage extension s20–s22** — triggered by the q004 finding in s25_qp_14 (files
   marked "OK" by the original pass had never been deep-verified). 185 checked, **18 fixed**.
3. **Independent QA** — all 18 coverage fixes re-audited (4-edge pixel audit + visual). Pass.
4. **Coverage s23–s25 + s25_qp_14 deep re-check** — 197 checked, **44 new files**
   (35 re-crops + 9 never-extracted diagrams). s23 was the worst session (prose bleeds,
   half-circuits, split figures, 4 never-extracted figures).
5. **Final audit** — every `images/` filename in all 19 papers is either fixed in
   `images_fix/` or explicitly logged OK; zero corrupt files; zero unaccounted images.

## Root-cause taxonomy (9 rules)

1. **Flush/zero-margin bboxes** (most common) — extractor saved crops exactly at the ink
   bbox; letters/dots/arrowheads shaved. *Fixed at source: `_crop_asset` pad 6→14 px +
   per-edge ink-extend; marker crops get vertical pad with locked column dividers.*
2. **Thin-line truncation** — ground lines, arrows, return wires cut at the detected body.
   *Fixed at source: `_ink_extend_px` follows flush ink outward (budget 36 px/edge).*
3. **Missing satellite content** — "NOT TO SCALE", "eye of student", scale arrows excluded.
   *Partially fixed at source: caption whitelist in `_is_label_like`. Detached content
   remains the known escape class (see Known limitations).*
4. **Stem/prose bleed** — crop boundary set inside the question text band. *Flag-only
   (judgment): `_detect_stem_bleed` top/bottom band detector.*
5. **Figure splitting** — one figure emitted as 2–3 partial crops. *Merge gaps widened
   (h 35→50 pt, v 18→28 pt); residual cases flag-only (≥2 near-touching crops).*
6. **Option label omission** — A/B/C/D letter excluded BY CONSTRUCTION (marker-driven
   crops started below the letter row). *Fixed at source: crop geometry now includes the
   marker row; QA script recovers letters via the letter-strip detector.*
7. **Side-by-side column bleed** — *Fixed at source: neighbor-clamped padding; QA script
   clamps every extension at sibling asset rects.*
8. **Never-extracted figures** — figure-referencing question ends with zero image assets
   (root cause: visuals silently dropped or never detected → layout `text_only`).
   *Detection at source: extractor now warns ("question text references a figure but no
   image asset was extracted") and tracks dropped visuals. Flag-only downstream.*
9. **Diagram-as-table misclassification** — circuit wrapped as a degenerate pdfplumber
   "table". *Fixed at source: `_looks_like_real_table` (≥2×2, ≥50 % wordy cells) reroutes
   sparse tables to question diagrams.*

## Tooling (built 2026-06-12, all at repo root)

| Tool | Purpose | Gate |
|---|---|---|
| `src/qa_common.py` | shared helpers: asset iteration (placement lists + `assets[]` mirror), px↔pt conversion, ink/edge audits, log provenance parser, template-match locator | selftest vs s20_qp_11 ✅ |
| `reconcile_fixes.py` | links orphan `images_fix` files into questions.json (19 attached, all with derived page+bbox), reclassifies the 3 diagram-as-table cases; idempotent, one-time `.bak`, dry-run default | **Gate A ✅** — blockers 28→28, problems 893→893, second apply = 0 changes |
| `regress_extract.py` | re-extracts probe stems into `output_probe/` (never `output/`) and compares vs ground truth | **Gate B ✅** — no crop smaller than old extractor; rule-8 warns on q10/q28/q22; rule-9 reroutes q37/q32 |
| `qa_images.py` | deterministic QA over extracted crops: auto-fixes rules 1/2/6 (+blank-trim), flags 3/4/5/7/8/9 to `qa_flags.json`; appends log section + `=== DONE ===`; never overwrites pre-existing fixes; 19 demo papers hard-excluded | **Gate C ✅** — recall 97.2 % (69/71), false positives 0, fixes visually verified |

### Validation methodology (Gate C)
Run `qa_images.py --validate --papers <3 QA'd stems>` → sandbox under
`output_probe/qa_validation/`. Recall = % of human-fixed files (auto-fixable classes) the
script also fixes/flags; FP = % of human-OK files the script "fixes". Judgment fixes
(criterion-7 full-diagram copies, split-figure replacements) are excluded from recall —
they are Claude-review territory by design.

## Known limitations (honest escape classes)

- **Detached satellite content** (2/71 in validation: a second caption line, a separated
  vector) — no contiguity signal; caught only by visual sweep. Document for reviewers.
- **Prose embedded mid-figure** (two stacked graphs with text between) — geometric fix
  keeps the prose; post-fix bleed check downgrades obvious cases to FLAGGED.
- The dimension comparison shows the script trims tighter than humans in some option sets;
  visual spot-checks confirmed the tighter crops are complete (letters, labels, origins).

## Remaining pipeline for the 89 papers

1. `python qa_images.py` — auto-fix + flag sweep (≈ minutes, deterministic).
   ⚠ Requires explicit user approval to lift the original "s20–s25 only" boundary.
2. Claude review sessions consume each paper's `qa_flags.json` (rules 4/5/8/9 + residual
   rule-7), apply judgment fixes into `images_fix/`, append log section, mark
   `=== REVIEWED ===`.
3. Re-run `reconcile_fixes.py --papers <the 89>` to attach review-created fix-only files
   into questions.json.
4. Final sweep: `qa_report.py` — problem counts must not increase; all logs end with
   `=== DONE ===` (+ `=== REVIEWED ===` where flags existed).
5. Future ingestions (new sessions/subjects) start from the FIXED extractor, so the heavy
   manual phase is not repeated.

## Reconciliation registry (already applied to the 19)

**Never extracted (10):** q023 s20_qp_12 · q021 s23_qp_11 · q010+q028 s23_qp_12 ·
q010+q026 s23_qp_13 · q003 s24_qp_11 · q022 s24_qp_13 · q020+q021 s25_qp_14.
**Criterion-7 companions (9):** q020 s20_qp_11 · q031 s20_qp_12 · q029 s22_qp_12 ·
q022 s22_qp_13 · q008 s23_qp_11 · q031 s24_qp_12 · q032+q038 s24_qp_13 · q026 s25_qp_11.
**Reclassified diagram-as-table (3):** q037 s23_qp_12 · q036 s24_qp_12 · q032 s24_qp_13
(JSON-only change; PNG filenames preserved).

All 19+3 now carry `source: manual_qa_reconciliation` assets pointing at
`papers/<stem>/images_fix/...` — importers must honor `images_fix` paths and overlay
same-filename fixes over `images/`.

## Preserved invariants (all sessions, never violated)

- `images/` and `pages/` originals never modified (746 files intact).
- Filename parity: no renames, no deletions; fixes always at the same filename.
- questions.json edits only via `reconcile_fixes.py` with one-time `.bak`.
- One image per model Read; nothing >1800 px viewed without downscaling; temp artifacts
  confined to `C:\Temp\` and `output_probe/`.
- `images_fix` existence ≠ done — only `=== DONE ===` / dated completion sections count.
