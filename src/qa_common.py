"""Shared helpers for the QA tooling (reconcile_fixes.py, regress_extract.py, qa_images.py).

Coordinate conventions, made explicit because mixing them is the #1 defect risk:
- questions.json bboxes are PDF points (72/inch), [x0, y0, x1, y1].
- claude_log.txt provenance, page PNGs, and PIL all use 200 DPI pixels.
- Pixel boxes in this module are (x, y, w, h) when they describe a crop
  rectangle (matching the log format), and (x0, y0, x1, y1) when they
  describe a bbox; function names say which.
"""
from __future__ import annotations

import json
import re
import shutil
from dataclasses import dataclass
from pathlib import Path
from typing import Iterator, List, Optional, Tuple

from PIL import Image

from .utils import BBox, pdf_to_pixel_scale

QA_DONE_MARKER = "=== DONE ==="
QA_REVIEWED_MARKER = "=== REVIEWED ==="

# The 19 fully QA'd demo papers. Frozen ground truth: automated tooling must
# never write into their images/ or images_fix/ folders.
DEMO_PAPERS = frozenset(
    {
        "9702_s20_qp_11", "9702_s20_qp_12", "9702_s20_qp_13",
        "9702_s21_qp_11", "9702_s21_qp_12", "9702_s21_qp_13",
        "9702_s22_qp_11", "9702_s22_qp_12", "9702_s22_qp_13",
        "9702_s23_qp_11", "9702_s23_qp_12", "9702_s23_qp_13",
        "9702_s24_qp_11", "9702_s24_qp_12", "9702_s24_qp_13",
        "9702_s25_qp_11", "9702_s25_qp_12", "9702_s25_qp_13",
        "9702_s25_qp_14",
    }
)


# ---------------------------------------------------------------------------
# questions.json I/O
# ---------------------------------------------------------------------------

def load_questions(paper_dir: Path) -> dict:
    path = paper_dir / "questions.json"
    with open(path, "r", encoding="utf-8") as fh:
        return json.load(fh)


def save_questions_with_backup(paper_dir: Path, data: dict) -> Path:
    """Write questions.json, creating a one-time .bak of the original first.

    The .bak is never overwritten on later runs, so it always preserves the
    pre-reconciliation original.
    """
    path = paper_dir / "questions.json"
    bak = paper_dir / "questions.json.bak"
    if path.exists() and not bak.exists():
        shutil.copy2(path, bak)
    tmp = paper_dir / "questions.json.tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(data, fh, ensure_ascii=False, indent=2)
    tmp.replace(path)
    return path


def paper_dirs(output_root: Path) -> List[Path]:
    papers = output_root / "papers"
    if not papers.is_dir():
        return []
    return sorted(p for p in papers.iterdir() if (p / "questions.json").is_file())


# ---------------------------------------------------------------------------
# Asset iteration
# ---------------------------------------------------------------------------

@dataclass
class AssetRef:
    """One image reference in questions.json.

    container is one of: "between", "after", "option", "option_table", "assets".
    The same asset id usually appears both in its placement list and in the
    question-level assets[] mirror — edits must touch both copies.
    """

    question_number: int
    container: str
    option_label: Optional[str]
    asset: dict  # the live dict inside the loaded JSON (mutate in place)


def iter_image_assets(qdata: dict) -> Iterator[AssetRef]:
    for q in qdata.get("questions", []):
        qn = q.get("question_number", 0)
        for a in q.get("question_images_between_text") or []:
            yield AssetRef(qn, "between", None, a)
        for a in q.get("question_images_after_text") or []:
            yield AssetRef(qn, "after", None, a)
        for opt in q.get("options") or []:
            for a in opt.get("images") or []:
                yield AssetRef(qn, "option", opt.get("label"), a)
        tbl = q.get("option_table")
        if tbl and tbl.get("image_path"):
            yield AssetRef(qn, "option_table", None, tbl)
        for a in q.get("assets") or []:
            yield AssetRef(qn, "assets", None, a)


def referenced_filenames(qdata: dict) -> set:
    """Every image filename mentioned anywhere in the JSON (basenames)."""
    names = set()
    for ref in iter_image_assets(qdata):
        p = ref.asset.get("image_path", "")
        if p:
            names.add(p.replace("\\", "/").rsplit("/", 1)[-1])
    return names


# ---------------------------------------------------------------------------
# Coordinate conversion
# ---------------------------------------------------------------------------

def pdf_bbox_to_px_rect(bbox: BBox, dpi: int = 200) -> Tuple[int, int, int, int]:
    """PDF-point [x0,y0,x1,y1] -> pixel (x, y, w, h)."""
    s = pdf_to_pixel_scale(dpi)
    x0, y0, x1, y1 = (bbox[0] * s, bbox[1] * s, bbox[2] * s, bbox[3] * s)
    return (int(round(x0)), int(round(y0)), int(round(x1 - x0)), int(round(y1 - y0)))


def px_rect_to_pdf_bbox(rect: Tuple[int, int, int, int], dpi: int = 200) -> List[float]:
    """Pixel (x, y, w, h) -> PDF-point [x0, y0, x1, y1]."""
    s = pdf_to_pixel_scale(dpi)
    x, y, w, h = rect
    return [round(x / s, 2), round(y / s, 2), round((x + w) / s, 2), round((y + h) / s, 2)]


# ---------------------------------------------------------------------------
# Pixel analysis
# ---------------------------------------------------------------------------

def ink_bbox(img: Image.Image, threshold: int = 200) -> Optional[Tuple[int, int, int, int]]:
    """Bounding box (x0, y0, x1, y1) of pixels darker than threshold, or None."""
    import numpy as np

    arr = np.asarray(img.convert("L"))
    mask = arr < threshold
    if not mask.any():
        return None
    rows = np.flatnonzero(mask.any(axis=1))
    cols = np.flatnonzero(mask.any(axis=0))
    return (int(cols[0]), int(rows[0]), int(cols[-1]) + 1, int(rows[-1]) + 1)


def edge_ink_audit(img: Image.Image, band: int = 2, threshold: int = 200) -> dict:
    """Dark-pixel counts in a `band`-px strip along each edge: {T, B, L, R}.

    Mirrors the manual QA procedure recorded in the logs ("edge audit B=20 R=4").
    """
    import numpy as np

    arr = np.asarray(img.convert("L"))
    mask = arr < threshold
    h, w = mask.shape
    b = min(band, h, w)
    return {
        "T": int(mask[:b, :].sum()),
        "B": int(mask[h - b:, :].sum()),
        "L": int(mask[:, :b].sum()),
        "R": int(mask[:, w - b:].sum()),
    }


def locate_crop_on_page(crop_path: Path, page_path: Path, min_score: float = 0.95) -> Optional[Tuple[Tuple[int, int, int, int], float]]:
    """Find a crop's position on its page PNG via template matching.

    Returns ((x, y, w, h), score) in page pixels, or None below min_score.
    """
    import cv2
    import numpy as np

    crop = cv2.imread(str(crop_path), cv2.IMREAD_GRAYSCALE)
    page = cv2.imread(str(page_path), cv2.IMREAD_GRAYSCALE)
    if crop is None or page is None:
        return None
    ch, cw = crop.shape
    ph, pw = page.shape
    if ch > ph or cw > pw:
        return None
    res = cv2.matchTemplate(page, crop, cv2.TM_CCOEFF_NORMED)
    _, score, _, loc = cv2.minMaxLoc(res)
    if score < min_score:
        return None
    return ((int(loc[0]), int(loc[1]), cw, ch), float(score))


# ---------------------------------------------------------------------------
# claude_log.txt provenance
# ---------------------------------------------------------------------------

# The manual logs record re-crop provenance in several hand-written styles:
#   "from page_010 bbox (1010,842,255,578)"          -> x,y,w,h
#   "from page_011 (334,766 987x397)"                -> x,y WxH
#   "from page_006 (143,343,306x312)"                -> x,y,WxH
#   "page_012 rect (387,272,878x485)"                -> x,y,WxH
#   "page_010 rect (483,1123,685x298)"               -> x,y,w,h
_PROV_PATTERNS = [
    re.compile(r"page[_ ](\d{1,3})(?:\.png)?[^()\n]{0,40}\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\)"),
    re.compile(r"page[_ ](\d{1,3})(?:\.png)?[^()\n]{0,40}\((\d+)\s*,\s*(\d+)[, ]\s*(\d+)\s*x\s*(\d+)\)"),
]
_FILENAME_RE = re.compile(r"(q\d{3}[A-Za-z_0-9]*\.png)")


def parse_fix_provenance(log_text: str) -> dict:
    """Map fix filename -> (page_number, (x, y, w, h)) parsed from log lines.

    Best effort: only lines that name exactly one file and carry a parseable
    page+rect are captured. Later sections override earlier ones (the most
    recent re-crop wins).
    """
    out: dict = {}
    for line in log_text.splitlines():
        m_file = _FILENAME_RE.findall(line)
        if len(set(m_file)) != 1:
            continue
        for pat in _PROV_PATTERNS:
            m = pat.search(line)
            if m:
                page = int(m.group(1))
                rect = (int(m.group(2)), int(m.group(3)), int(m.group(4)), int(m.group(5)))
                out[m_file[0]] = (page, rect)
                break
    return out


def log_has_marker(paper_dir: Path, marker: str) -> bool:
    log = paper_dir / "claude_log.txt"
    if not log.is_file():
        return False
    try:
        return marker in log.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return False


# ---------------------------------------------------------------------------
# Selftest
# ---------------------------------------------------------------------------

def _selftest(output_root: Path) -> int:
    paper = output_root / "papers" / "9702_s20_qp_11"
    qdata = load_questions(paper)

    refs = list(iter_image_assets(qdata))
    ref_names = referenced_filenames(qdata)
    img_names = {p.name for p in (paper / "images").glob("*.png")}
    missing = img_names - ref_names
    print(f"[selftest] asset refs: {len(refs)}; images/ files: {len(img_names)}; "
          f"referenced: {len(ref_names & img_names)}; unreferenced images/: {sorted(missing)}")

    log_text = (paper / "claude_log.txt").read_text(encoding="utf-8", errors="replace")
    prov = parse_fix_provenance(log_text)
    print(f"[selftest] provenance entries parsed: {len(prov)}")
    for name, (page, rect) in list(prov.items())[:5]:
        print(f"  {name} -> page_{page:03d} rect {rect}")
    assert len(prov) >= 3, "expected >=3 provenance entries in s20_qp_11 log"

    # Round-trip coordinate check
    bbox = [210.2, 101.4, 429.1, 184.8]
    rect = pdf_bbox_to_px_rect(bbox, 200)
    back = px_rect_to_pdf_bbox(rect, 200)
    assert all(abs(a - b) < 0.5 for a, b in zip(bbox, back)), (bbox, rect, back)
    print(f"[selftest] coord round-trip ok: {bbox} -> {rect} -> {back}")

    # Edge audit on a known-clean fix
    fix = paper / "images_fix" / "q023_question_diagram_01.png"
    if fix.exists():
        with Image.open(fix) as im:
            audit = edge_ink_audit(im)
        print(f"[selftest] edge audit {fix.name}: {audit}")

    # Template match a fix back onto its page (provenance gives truth)
    for name, (page, rect) in prov.items():
        fix_path = paper / "images_fix" / name
        page_path = paper / "pages" / f"page_{page:03d}.png"
        if fix_path.exists() and page_path.exists():
            hit = locate_crop_on_page(fix_path, page_path)
            status = f"({hit[0]}, score={hit[1]:.3f})" if hit else "NO MATCH"
            print(f"[selftest] template match {name}: log rect {rect} vs match {status}")
            if hit:
                assert abs(hit[0][0] - rect[0]) <= 3 and abs(hit[0][1] - rect[1]) <= 3, (
                    f"template position disagrees with log provenance for {name}")
            break

    print("[selftest] PASS")
    return 0


if __name__ == "__main__":
    import sys

    root = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("output")
    raise SystemExit(_selftest(root))
