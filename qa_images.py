"""Deterministic image-QA pipeline for extracted paper crops.

Implements the auto-fixable rules of the manual-QA taxonomy (see
QA_LIFECYCLE.md) and flags the judgment rules for Claude review:

  AUTO-FIX (writes corrected crop to images_fix/ under the same filename):
    rule 1 — zero-margin crop (ink flush at >=2 edges)
    rule 2 — edge truncation (ink flush at an edge AND the page shows content
             continuing past the crop boundary)
    rule 6 — option letter missing from a qNNN_option_X crop top band
  FLAG ONLY (recorded in qa_flags.json for a Claude review session):
    rule 3 — possible satellite content near the crop (conservative: flagged,
             not auto-merged)
    rule 4 — stem/prose bleed inside the crop
    rule 5 — one figure split across multiple crops
    rule 8 — figure-referencing question with zero image assets
    rule 9 — _table_ file whose option_table data is degenerate

Hard rules:
  - the 19 manually QA'd demo papers are never touched (hardcoded exclusion)
  - pre-existing images_fix files are never overwritten (PREEXISTING_FIX)
  - fixes are fresh crops from the native 200 DPI page PNG — no rescaling
  - papers whose claude_log.txt contains === DONE === are skipped

Usage:
  python qa_images.py --papers 9702_w20_qp_11            # one paper
  python qa_images.py --limit 10                         # first 10 pending
  python qa_images.py --validate --papers <qa'd stems>   # sandbox + compare
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import asdict, dataclass, field
from datetime import date
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from PIL import Image

from src.qa_common import (
    DEMO_PAPERS,
    QA_DONE_MARKER,
    edge_ink_audit,
    ink_bbox,
    load_questions,
    paper_dirs,
    pdf_bbox_to_px_rect,
)

DPI = 200
SCRIPT_VERSION = "v1"
FIGURE_REF_RE = re.compile(r"(?i)\b(diagrams?|figure|graph|circuit|shown)\b")
OPTION_FILE_RE = re.compile(r"_option_([ABCD])(?:_\d+)?\.png$")
TABLE_FILE_RE = re.compile(r"_table_\d+\.png$")

# Manual fixes that replaced per-option slices with FULL-diagram copies
# (criterion-7) or reclassified tables — judgment fixes a per-image detector
# cannot reproduce; excluded from Gate C recall.
JUDGMENT_FIXES = {
    "9702_s20_qp_11": {f"q020_option_{l}.png" for l in "ABCD"},
    "9702_s24_qp_13": {f"q032_option_{l}.png" for l in "ABCD"}
    | {f"q038_option_{l}.png" for l in "ABCD"} | {"q032_table_01.png"}
    # q014: one figure split across 3 crops; the human replaced each with the
    # merged full figure — rule-5 flag territory, not per-image geometry.
    | {f"q014_question_diagram_{i:02d}.png" for i in (1, 2, 3)},
}

EDGE_BAND = 2
EDGE_INK_MIN = 2          # dark px in a band to call an edge "flush"
FIX_MARGIN = 16           # margin for re-crops
INK_EXTEND_MAX = 40       # per-edge budget when following flush ink outward
INK_EXTEND_STEP = 10
LABEL_EXTEND = 38         # rule 6: how far above the crop to look for the letter


@dataclass
class ImageVerdict:
    file: str
    verdict: str                      # OK | FIXED | FLAGGED | PREEXISTING_FIX
    rules: List[str] = field(default_factory=list)
    detail: str = ""
    page: Optional[int] = None
    old_px_rect: Optional[List[int]] = None
    new_px_rect: Optional[List[int]] = None
    fix_path: str = ""


# ---------------------------------------------------------------------------
# asset metadata from questions.json
# ---------------------------------------------------------------------------

def asset_index(qdata: dict) -> Dict[str, dict]:
    """filename -> {page, bbox, qnum, role} from every reference location."""
    out: Dict[str, dict] = {}

    def note(asset: dict, qnum: int, role: str):
        path = (asset.get("image_path") or "").replace("\\", "/")
        if not path:
            return
        name = path.rsplit("/", 1)[-1]
        out.setdefault(name, {
            "page": asset.get("page"),
            "bbox": asset.get("bbox") or [],
            "qnum": qnum,
            "role": role,
        })

    for q in qdata.get("questions", []):
        qn = q.get("question_number", 0)
        for a in q.get("question_images_between_text") or []:
            note(a, qn, "question_diagram")
        for a in q.get("question_images_after_text") or []:
            note(a, qn, "question_diagram")
        for opt in q.get("options") or []:
            for a in opt.get("images") or []:
                note(a, qn, "option_image")
        tbl = q.get("option_table")
        if tbl and tbl.get("image_path"):
            note({"image_path": tbl["image_path"], "page": None, "bbox": tbl.get("bbox") or []},
                 qn, "option_table")
        for a in q.get("assets") or []:
            note(a, qn, "asset")
    return out


def neighbor_rects(meta: Dict[str, dict], name: str) -> List[Tuple[int, int, int, int]]:
    """Pixel rects (x0,y0,x1,y1) of other assets on the same page."""
    me = meta.get(name) or {}
    page = me.get("page")
    if page is None:
        return []
    rects = []
    for other, m in meta.items():
        if other == name or m.get("page") != page or not m.get("bbox"):
            continue
        x, y, w, h = pdf_bbox_to_px_rect(m["bbox"], DPI)
        rects.append((x - 4, y - 4, x + w + 4, y + h + 4))
    return rects


# ---------------------------------------------------------------------------
# pixel helpers
# ---------------------------------------------------------------------------

def _page_arr(page_img: Image.Image):
    import numpy as np
    return np.asarray(page_img.convert("L"))


def _band_has_ink(arr, x0, y0, x1, y1, threshold=200) -> bool:
    h, w = arr.shape
    x0, y0 = max(0, x0), max(0, y0)
    x1, y1 = min(w, x1), min(h, y1)
    if x1 <= x0 or y1 <= y0:
        return False
    return bool((arr[y0:y1, x0:x1] < threshold).any())


def _clamp_to_neighbors(rect, neighbors, page_w, page_h):
    """Shrink an extension rect so it does not enter any neighbor rect.

    Conservative: an edge is pulled back to the neighbor boundary only when the
    rect actually overlaps that neighbor.
    """
    x0, y0, x1, y1 = rect
    for nx0, ny0, nx1, ny1 in neighbors:
        if x0 < nx1 and nx0 < x1 and y0 < ny1 and ny0 < y1:
            # Overlap: pull back the smallest-intrusion edge.
            intrusions = {
                "L": nx1 - x0 if x0 >= nx0 else 10**9,
                "R": x1 - nx0 if x1 <= nx1 else 10**9,
                "T": ny1 - y0 if y0 >= ny0 else 10**9,
                "B": y1 - ny0 if y1 <= ny1 else 10**9,
            }
            edge = min(intrusions, key=intrusions.get)
            if edge == "L":
                x0 = nx1
            elif edge == "R":
                x1 = nx0
            elif edge == "T":
                y0 = ny1
            else:
                y1 = ny0
    return (max(0, x0), max(0, y0), min(page_w, x1), min(page_h, y1))


def _ink_extend(arr, rect, budgets, neighbors, step=INK_EXTEND_STEP):
    """Grow rect edges whose border band has ink; clamp at neighbors/page."""
    h, w = arr.shape
    x0, y0, x1, y1 = rect
    bl, bt, br, bb = budgets
    for _ in range(12):
        grew = False
        if bl > 0 and x0 > 0 and _band_has_ink(arr, x0, y0, x0 + EDGE_BAND, y1):
            d = min(step, bl, x0); x0 -= d; bl -= d; grew = True
        if bt > 0 and y0 > 0 and _band_has_ink(arr, x0, y0, x1, y0 + EDGE_BAND):
            d = min(step, bt, y0); y0 -= d; bt -= d; grew = True
        if br > 0 and x1 < w and _band_has_ink(arr, x1 - EDGE_BAND, y0, x1, y1):
            d = min(step, br, w - x1); x1 += d; br -= d; grew = True
        if bb > 0 and y1 < h and _band_has_ink(arr, x0, y1 - EDGE_BAND, x1, y1):
            d = min(step, bb, h - y1); y1 += d; bb -= d; grew = True
        if not grew:
            break
        x0, y0, x1, y1 = _clamp_to_neighbors((x0, y0, x1, y1), neighbors, w, h)
    return (x0, y0, x1, y1)


def _detect_stem_bleed(img: Image.Image) -> Optional[str]:
    """Wide text-like band at the top or bottom of the crop, separated from the
    content by a blank gap — the rule-4 signature. Returns 'top'/'bottom'/None."""
    import numpy as np
    arr = np.asarray(img.convert("L"))
    mask = arr < 200
    h, w = mask.shape
    if h < 80 or w < 200:
        return None

    def band_is_prose(rows) -> bool:
        frac = mask[rows].sum(axis=1) / w
        band_rows = np.flatnonzero(frac > 0.02)
        if band_rows.size == 0:
            return False
        lo, hi = rows.start + band_rows.min(), rows.start + band_rows.max()
        if hi - lo > 40:  # taller than a text line or two
            return False
        band = mask[lo:hi + 1]
        cols = np.flatnonzero(band.any(axis=0))
        if cols.size == 0:
            return False
        if (cols.max() - cols.min()) / w < 0.6:
            return False
        # blank gap separating the band from the figure
        if rows.start == 0:
            gap = mask[hi + 1:hi + 13]
        else:
            gap = mask[max(0, lo - 12):lo]
        return gap.size > 0 and not gap.any()

    top = min(48, h // 3)
    if band_is_prose(slice(0, top)):
        return "top"
    if band_is_prose(slice(h - top, h)):
        return "bottom"
    return None


def _letter_strip(arr, x, y, w) -> Optional[int]:
    """Rule 6: look for an isolated letter-sized ink blob in the strip just
    above the content bbox. Returns the strip's ink top (page y) or None.

    The old extractor's marker-driven crops start BELOW the A/B/C/D letter by
    construction, so the letter sits in this strip. Wide coverage means prose,
    not a letter — rejected.
    """
    import numpy as np
    h_page, w_page = arr.shape
    y1 = max(0, y - 2)
    y0 = max(0, y - LABEL_EXTEND)
    x1 = min(w_page, x + w)
    if y1 <= y0 or x1 <= x:
        return None
    strip = arr[y0:y1, x:x1] < 200
    if not strip.any():
        return None
    cols = np.flatnonzero(strip.any(axis=0))
    coverage = (cols.max() - cols.min() + 1) / max(1, x1 - x)
    ink_px = int(strip.sum())
    # A bold letter at 200 DPI is a compact blob; prose spans the width.
    if coverage > 0.5 or ink_px > 2500:
        return None
    rows = np.flatnonzero(strip.any(axis=1))
    return y0 + int(rows.min())


def _region_ink_bbox(arr, x0, y0, x1, y1):
    """Ink bbox (page coords) inside a page region, or None."""
    import numpy as np
    h, w = arr.shape
    x0, y0 = max(0, x0), max(0, y0)
    x1, y1 = min(w, x1), min(h, y1)
    if x1 <= x0 or y1 <= y0:
        return None
    sub = arr[y0:y1, x0:x1] < 200
    if not sub.any():
        return None
    rows = np.flatnonzero(sub.any(axis=1))
    cols = np.flatnonzero(sub.any(axis=0))
    return (x0 + int(cols.min()), y0 + int(rows.min()),
            x0 + int(cols.max()) + 1, y0 + int(rows.max()) + 1)


# ---------------------------------------------------------------------------
# per-image audit
# ---------------------------------------------------------------------------

def audit_image(
    name: str,
    paper_dir: Path,
    meta: Dict[str, dict],
    fix_dir: Path,
) -> ImageVerdict:
    img_path = paper_dir / "images" / name
    v = ImageVerdict(file=name, verdict="OK")

    # Never overwrite an existing fix at the DESTINATION (in sandbox mode the
    # destination is the sandbox, so validation sees the honest detection).
    if (fix_dir / name).exists():
        v.verdict = "PREEXISTING_FIX"
        with Image.open(fix_dir / name) as im:
            audit = edge_ink_audit(im, band=EDGE_BAND)
        flush = [e for e, n in audit.items() if n > EDGE_INK_MIN]
        if flush:
            v.rules.append("rule2")
            v.detail = f"pre-existing manual fix still has flush edges {flush} - flag for review"
            v.verdict = "FLAGGED"
        return v

    m = meta.get(name) or {}
    page = m.get("page")
    with Image.open(img_path) as im:
        im_w, im_h = im.size
        audit = edge_ink_audit(im, band=EDGE_BAND)
        bleed = _detect_stem_bleed(im)

    flush_edges = [e for e, n in audit.items() if n > EDGE_INK_MIN]

    if bleed:
        v.rules.append("rule4")
        v.detail = f"probable stem/prose band at crop {bleed}"
        v.verdict = "FLAGGED"
        return v

    is_option = bool(OPTION_FILE_RE.search(name))

    page_png = paper_dir / "pages" / f"page_{page:03d}.png" if page else None
    if not (page_png and page_png.exists() and m.get("bbox")):
        if flush_edges:
            v.verdict = "FLAGGED"
            v.rules.append("rule2")
            v.detail = f"flush edges {flush_edges} but no usable page/bbox metadata"
        return v

    x, y, w, h = pdf_bbox_to_px_rect(m["bbox"], DPI)
    with Image.open(page_png) as page_img:
        arr = _page_arr(page_img)
        ph, pw = arr.shape
        neighbors = neighbor_rects(meta, name)

        # The saved crop is the bbox plus the old extractor's uniform padding;
        # infer the actual crop rect on the page from the file dimensions.
        # (Ink may legitimately occupy the pad zone — label overhangs,
        # descenders — so all boundary tests run at the CROP edge, not the
        # bbox edge.)
        pad_x = max(0, (im_w - w) // 2)
        pad_y = max(0, (im_h - h) // 2)
        kx0, ky0 = max(0, x - pad_x), max(0, y - pad_y)
        kx1, ky1 = min(pw, x + w + pad_x), min(ph, y + h + pad_y)

        content = _region_ink_bbox(arr, kx0, ky0, kx1, ky1)
        if content is None:
            v.verdict = "FLAGGED"
            v.rules.append("error")
            v.detail = "asset crop region on page contains no ink"
            return v
        cx0, cy0, cx1, cy1 = content

        reasons: List[str] = []

        # rule 6 — letter strip above the crop for option files
        letter_top = _letter_strip(arr, kx0, ky0, kx1 - kx0) if is_option else None
        if letter_top is not None:
            reasons.append("rule6")

        # rule 1/2 — truncation: ink crosses the CROP boundary (same columns/
        # rows carry ink in the 3-px strips on BOTH sides of the edge — the
        # signature of a stroke cut mid-line; nearby-but-separate text fails
        # the contiguity requirement).
        truncated = []
        import numpy as np
        for edge in "LRTB":
            if edge == "L":
                if kx0 < 3:
                    continue
                inner = arr[cy0:cy1, kx0:kx0 + 3] < 200
                outer = arr[cy0:cy1, kx0 - 3:kx0] < 200
            elif edge == "R":
                if kx1 > pw - 3:
                    continue
                inner = arr[cy0:cy1, kx1 - 3:kx1] < 200
                outer = arr[cy0:cy1, kx1:kx1 + 3] < 200
            elif edge == "T":
                if ky0 < 3:
                    continue
                inner = arr[ky0:ky0 + 3, cx0:cx1] < 200
                outer = arr[ky0 - 3:ky0, cx0:cx1] < 200
            else:
                if ky1 > ph - 3:
                    continue
                inner = arr[ky1 - 3:ky1, cx0:cx1] < 200
                outer = arr[ky1:ky1 + 3, cx0:cx1] < 200
            if inner.size == 0 or outer.size == 0:
                continue
            # For L/R edges contiguity is per-row; for T/B it is per-column.
            axis = 1 if edge in "LR" else 0
            aligned = np.logical_and(inner.any(axis=axis), outer.any(axis=axis))
            if int(aligned.sum()) >= 2:
                truncated.append(edge)
        if truncated or flush_edges:
            reasons.append("rule1" if len(set(truncated) | set(flush_edges)) >= 2 else "rule2")

        # criterion 2 — excess blank space: the crop canvas is much larger
        # than its content (old extractor column crops, split grids etc.)
        blank_trim = False
        content_w, content_h = cx1 - cx0, cy1 - cy0
        if content_w / max(1, im_w) < 0.66 or content_h / max(1, im_h) < 0.66:
            blank_trim = True
            reasons.append("blank")

        if not reasons:
            return v

        # Unified re-crop: tight content bbox + margin, letter strip if found,
        # ink-extend to follow truncated lines, clamped at neighbors/page.
        rect = (
            max(0, cx0 - FIX_MARGIN),
            max(0, min(cy0 - FIX_MARGIN,
                       (letter_top - 6) if letter_top is not None else cy0)),
            min(pw, cx1 + FIX_MARGIN),
            min(ph, cy1 + FIX_MARGIN),
        )
        rect = _clamp_to_neighbors(rect, neighbors, pw, ph)
        rect = _ink_extend(arr, rect, (INK_EXTEND_MAX,) * 4, neighbors)
        if rect[2] - rect[0] < 8 or rect[3] - rect[1] < 8:
            v.verdict = "FLAGGED"
            v.rules = reasons + ["error"]
            v.detail = "re-crop rect degenerate after clamping"
            return v

        # Skip no-op fixes: result within a few px of the existing crop extent.
        if not blank_trim and letter_top is None and all(
                abs(a - b) <= 6 for a, b in zip(rect, (kx0, ky0, kx1, ky1))):
            return v

    with Image.open(page_png) as page_img2:
        crop = page_img2.convert("RGB").crop(rect)
        post = edge_ink_audit(crop, band=EDGE_BAND)
        still = [e for e, n in post.items() if n > EDGE_INK_MIN]
        post_bleed = _detect_stem_bleed(crop)
        fix_dir.mkdir(parents=True, exist_ok=True)
        crop.save(fix_dir / name)

    v.verdict = "FIXED"
    v.rules = sorted(set(reasons))
    v.detail = (f"re-cropped from page {page}: "
                + ", ".join(filter(None, [
                    f"flush {flush_edges}" if flush_edges else "",
                    f"truncated {truncated}" if truncated else "",
                    "blank-trimmed" if blank_trim else "",
                    "letter recovered" if letter_top is not None else "",
                ])))
    v.page = page
    v.old_px_rect = [x, y, x + w, y + h]
    v.new_px_rect = list(rect)
    v.fix_path = str(fix_dir / name)
    if still:
        v.verdict = "FLAGGED"
        v.rules = sorted(set(v.rules + ["rule7"]))
        v.detail += f"; residual flush {still} after clamped extension - review"
    if post_bleed:
        v.verdict = "FLAGGED"
        v.rules = sorted(set(v.rules + ["rule4"]))
        v.detail += f"; prose band still present at {post_bleed} of fix - review"
    return v


# ---------------------------------------------------------------------------
# question-level checks (rules 5, 8, 9)
# ---------------------------------------------------------------------------

def question_flags(qdata: dict, meta: Dict[str, dict]) -> List[ImageVerdict]:
    out: List[ImageVerdict] = []
    for q in qdata.get("questions", []):
        qn = q.get("question_number", 0)
        has_img = bool(
            q.get("question_images_between_text") or q.get("question_images_after_text")
            or any(o.get("images") for o in q.get("options") or [])
            or q.get("option_table")
        )
        text = q.get("question_text") or ""
        if not has_img and FIGURE_REF_RE.search(text):
            out.append(ImageVerdict(
                file=f"q{qn:03d}", verdict="FLAGGED", rules=["rule8"],
                detail="question references a figure but has no image assets"))
        tbl = q.get("option_table")
        if tbl:
            rows = tbl.get("rows") or []
            width = max((len(r) for r in rows), default=0)
            cells = [c for r in rows for c in (r[1:] if r else [])]
            filled = sum(1 for c in cells if (c or "").strip())
            degenerate = len(rows) < 2 or width < 2 or (cells and filled / len(cells) < 0.5)
            if degenerate:
                fname = (tbl.get("image_path") or "").rsplit("/", 1)[-1]
                out.append(ImageVerdict(
                    file=fname or f"q{qn:03d}_table", verdict="FLAGGED", rules=["rule9"],
                    detail=f"option_table data degenerate (rows={rows[:2]}...) - likely a diagram"))
        # rule 5: same-question diagram assets nearly touching on a shared axis
        diags = (q.get("question_images_between_text") or []) + (q.get("question_images_after_text") or [])
        rects = []
        for a in diags:
            if a.get("bbox") and a.get("page") is not None:
                x, y, w, h = pdf_bbox_to_px_rect(a["bbox"], DPI)
                rects.append((a.get("id"), a.get("page"), (x, y, x + w, y + h)))
        for i in range(len(rects)):
            for j in range(i + 1, len(rects)):
                ai, pi, ri = rects[i]
                aj, pj, rj = rects[j]
                if pi != pj:
                    continue
                gap_x = max(ri[0], rj[0]) - min(ri[2], rj[2])
                gap_y = max(ri[1], rj[1]) - min(ri[3], rj[3])
                y_overlap = min(ri[3], rj[3]) - max(ri[1], rj[1])
                x_overlap = min(ri[2], rj[2]) - max(ri[0], rj[0])
                if (0 <= gap_x < 25 and y_overlap > 0) or (0 <= gap_y < 25 and x_overlap > 0):
                    out.append(ImageVerdict(
                        file=f"{ai}+{aj}", verdict="FLAGGED", rules=["rule5"],
                        detail=f"q{qn}: two diagram crops nearly touching (gap_x={gap_x}, gap_y={gap_y}) - possible split figure"))
    return out


# ---------------------------------------------------------------------------
# per-paper driver
# ---------------------------------------------------------------------------

def process_paper(paper_dir: Path, sandbox_root: Optional[Path]) -> dict:
    stem = paper_dir.name
    qdata = load_questions(paper_dir)
    meta = asset_index(qdata)
    fix_dir = (sandbox_root / stem / "images_fix") if sandbox_root else (paper_dir / "images_fix")

    verdicts: List[ImageVerdict] = []
    images = sorted((paper_dir / "images").glob("*.png"))
    for img in images:
        try:
            verdicts.append(audit_image(img.name, paper_dir, meta, fix_dir))
        except Exception as exc:  # defensive: one bad image must not kill the paper
            verdicts.append(ImageVerdict(file=img.name, verdict="FLAGGED",
                                         rules=["error"], detail=f"audit error: {exc}"))
    verdicts.extend(question_flags(qdata, meta))

    totals = {"ok": 0, "fixed": 0, "flagged": 0, "preexisting": 0}
    for v in verdicts:
        key = {"OK": "ok", "FIXED": "fixed", "FLAGGED": "flagged",
               "PREEXISTING_FIX": "preexisting"}[v.verdict]
        totals[key] += 1

    result = {
        "paper": stem,
        "script_version": SCRIPT_VERSION,
        "generated": str(date.today()),
        "totals": totals,
        "images": [asdict(v) for v in verdicts],
    }

    if sandbox_root is None:
        (paper_dir / "qa_flags.json").write_text(
            json.dumps(result, indent=2), encoding="utf-8")
        append_log(paper_dir, verdicts, totals)
    else:
        out_dir = sandbox_root / stem
        out_dir.mkdir(parents=True, exist_ok=True)
        (out_dir / "qa_flags.json").write_text(
            json.dumps(result, indent=2), encoding="utf-8")
    return result


def append_log(paper_dir: Path, verdicts: List[ImageVerdict], totals: dict) -> None:
    log = paper_dir / "claude_log.txt"
    header = f"----- qa_images.py automated pass ({SCRIPT_VERSION}, {date.today()}) -----"
    lines = ["", header]
    for v in verdicts:
        if v.verdict == "OK":
            continue
        tags = "".join(f"[{r}]" for r in v.rules)
        lines.append(f"{v.file}  {v.verdict}  {tags}  {v.detail}")
    lines.append(
        f"Summary: {totals['ok']} OK, {totals['fixed']} fixed, "
        f"{totals['flagged']} flagged, {totals['preexisting']} pre-existing manual fixes")
    lines.append(QA_DONE_MARKER)
    existing = log.read_text(encoding="utf-8", errors="replace") if log.exists() else (
        f"claude_log.txt - {paper_dir.name} image QA\n")
    if header in existing:
        # Replace this script version's previous section (idempotent re-runs).
        existing = existing.split(header)[0].rstrip("\n")
    log.write_text(existing + "\n".join(lines) + "\n", encoding="utf-8")


def discover_papers(output_root: Path, force: bool) -> List[Path]:
    out = []
    for d in paper_dirs(output_root):
        if d.name in DEMO_PAPERS:
            continue  # frozen ground truth - excluded even with --force
        log = d / "claude_log.txt"
        if not force and log.exists() and QA_DONE_MARKER in log.read_text(
                encoding="utf-8", errors="replace"):
            continue
        out.append(d)
    return out


# ---------------------------------------------------------------------------
# validation mode (Gate C)
# ---------------------------------------------------------------------------

def validate(papers: List[str], output_root: Path, sandbox_root: Path) -> int:
    """Run detectors on QA'd papers in a sandbox and compare with the human fixes."""
    from src.qa_common import parse_fix_provenance

    agg = {"human_fixed": 0, "script_fixed_same": 0, "script_missed": [],
           "false_positives": [], "dim_agree": 0, "dim_disagree": []}
    for stem in papers:
        paper_dir = output_root / "papers" / stem
        result = process_paper(paper_dir, sandbox_root)
        by_name = {v["file"]: v for v in result["images"]}

        log_text = (paper_dir / "claude_log.txt").read_text(encoding="utf-8", errors="replace")
        human_fix_names = {p.name for p in (paper_dir / "images_fix").glob("*.png")}
        # Only auto-fixable-rule fixes count toward recall: option/diagram
        # crops that exist in images/ too (additions and judgment fixes are
        # Claude-review territory).
        candidates = {n for n in human_fix_names if (paper_dir / "images" / n).exists()}
        candidates -= JUDGMENT_FIXES.get(stem, set())

        for name in sorted(candidates):
            agg["human_fixed"] += 1
            v = by_name.get(name)
            # In sandbox mode PREEXISTING_FIX never happens (sandbox fix dir is
            # empty), so the script's honest verdict is visible.
            if v and v["verdict"] == "FIXED":
                agg["script_fixed_same"] += 1
                sandbox_fix = sandbox_root / stem / "images_fix" / name
                truth_fix = paper_dir / "images_fix" / name
                if sandbox_fix.exists():
                    with Image.open(sandbox_fix) as a, Image.open(truth_fix) as b:
                        aw, ah = a.size
                        bw, bh = b.size
                    if abs(aw - bw) <= max(36, bw * 0.12) and abs(ah - bh) <= max(36, bh * 0.12):
                        agg["dim_agree"] += 1
                    else:
                        agg["dim_disagree"].append(f"{stem}/{name}: script {aw}x{ah} vs human {bw}x{bh}")
            elif v and v["verdict"] == "FLAGGED":
                agg["script_fixed_same"] += 1  # flagged counts as caught
            else:
                agg["script_missed"].append(f"{stem}/{name}")

        ok_names = {p.name for p in (paper_dir / "images").glob("*.png")} - human_fix_names
        for name in sorted(ok_names):
            v = by_name.get(name)
            if v and v["verdict"] == "FIXED":
                agg["false_positives"].append(f"{stem}/{name}")

    n_ok_total = agg["human_fixed"]
    recall = agg["script_fixed_same"] / n_ok_total * 100 if n_ok_total else 100.0
    print("\n=== Gate C validation ===")
    print(f"human-fixed candidates: {agg['human_fixed']}")
    print(f"caught by script (fixed or flagged): {agg['script_fixed_same']} ({recall:.1f}%)")
    print(f"missed: {len(agg['script_missed'])}")
    for m in agg["script_missed"]:
        print(f"  MISS {m}")
    print(f"dimension agreement on fixes: {agg['dim_agree']}; disagreements: {len(agg['dim_disagree'])}")
    for d in agg["dim_disagree"][:20]:
        print(f"  DIM {d}")
    print(f"false positives (script fixed a human-OK file): {len(agg['false_positives'])}")
    for f in agg["false_positives"][:30]:
        print(f"  FP {f}")
    (sandbox_root / "gate_c_report.json").write_text(json.dumps(agg, indent=2), encoding="utf-8")
    return 0


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--output-root", default="output", type=Path)
    ap.add_argument("--papers", default="")
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--validate", action="store_true",
                    help="sandbox mode: compare against existing manual fixes")
    ap.add_argument("--sandbox-root", default=Path("output_probe") / "qa_validation", type=Path)
    args = ap.parse_args(argv)

    if args.validate:
        stems = [s.strip() for s in args.papers.split(",") if s.strip()]
        if not stems:
            print("--validate requires --papers", file=sys.stderr)
            return 1
        return validate(stems, args.output_root, args.sandbox_root)

    if args.papers:
        wanted = {s.strip() for s in args.papers.split(",") if s.strip()}
        blocked = wanted & DEMO_PAPERS
        if blocked:
            print(f"refusing to touch demo papers: {sorted(blocked)}", file=sys.stderr)
            return 1
        dirs = [d for d in paper_dirs(args.output_root) if d.name in wanted]
    else:
        dirs = discover_papers(args.output_root, args.force)
    if args.limit:
        dirs = dirs[: args.limit]

    print(f"processing {len(dirs)} papers")
    grand = {"ok": 0, "fixed": 0, "flagged": 0, "preexisting": 0}
    for d in dirs:
        r = process_paper(d, None)
        t = r["totals"]
        for k in grand:
            grand[k] += t[k]
        print(f"  {d.name}: {t['ok']} ok, {t['fixed']} fixed, {t['flagged']} flagged, "
              f"{t['preexisting']} pre-existing")
    print(f"TOTAL: {grand}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
