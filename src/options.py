"""Detect MCQ option markers (A/B/C/D) and parse their text/layout."""
from __future__ import annotations

import re
from dataclasses import dataclass, field
from itertools import product
from typing import List, Optional, Tuple

import fitz

from .utils import BBox

OPTION_LABELS = ("A", "B", "C", "D")


@dataclass
class Span:
    text: str
    bbox: BBox
    font: str
    size: float
    flags: int

    @property
    def is_bold(self) -> bool:
        if self.flags & 16:  # PyMuPDF bold flag
            return True
        return "Bold" in self.font or "bold" in self.font


@dataclass
class OptionMarker:
    label: str
    bbox: BBox  # bbox of the letter only
    line_bbox: BBox  # bbox of the whole line containing the marker
    is_bold: bool = False

    @property
    def x(self) -> float:
        return self.bbox[0]

    @property
    def y(self) -> float:
        return (self.bbox[1] + self.bbox[3]) / 2


def get_spans(page: fitz.Page) -> List[Span]:
    out: List[Span] = []
    data = page.get_text("dict")
    for block in data.get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            for span in line.get("spans", []):
                bbox = tuple(span["bbox"])
                out.append(
                    Span(
                        text=span.get("text", ""),
                        bbox=bbox,
                        font=span.get("font", ""),
                        size=float(span.get("size", 0.0)),
                        flags=int(span.get("flags", 0)),
                    )
                )
    return out


def get_lines(page: fitz.Page) -> List[dict]:
    """Return list of {bbox, spans, text} for every text line on the page."""
    out: List[dict] = []
    data = page.get_text("dict")
    for block in data.get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            spans = [
                Span(
                    text=s.get("text", ""),
                    bbox=tuple(s["bbox"]),
                    font=s.get("font", ""),
                    size=float(s.get("size", 0.0)),
                    flags=int(s.get("flags", 0)),
                )
                for s in line.get("spans", [])
            ]
            text = "".join(s.text for s in spans)
            bbox = tuple(line.get("bbox", (0, 0, 0, 0)))
            out.append({"bbox": bbox, "spans": spans, "text": text})
    return out


_NUMERIC_RE = re.compile(r"^[+\-]?\d+(\.\d+)?(\s*[°µ°µ%]?)?$|\d")


def _bbox_contains(outer: BBox, inner: BBox, tol: float = 1.5) -> bool:
    return (
        inner[0] >= outer[0] - tol
        and inner[1] >= outer[1] - tol
        and inner[2] <= outer[2] + tol
        and inner[3] <= outer[3] + tol
    )


def _looks_numeric(text: str) -> bool:
    """Return True if ``text`` is a number-bearing token (e.g. "0.15", "3", "1.0",
    "10⁻³"). Used to detect "<number> A" unit-symbol patterns."""
    t = (text or "").strip()
    if not t:
        return False
    return any(ch.isdigit() for ch in t)


def _spans_immediately_left_of(
    target: "OptionMarker", spans: List[Span], y_tol: float = 3.0, max_gap: float = 18.0
) -> List[Span]:
    """Return spans on the same line as ``target`` that end within ``max_gap``
    points to the left of ``target.bbox[0]``."""
    cy = target.y
    out: List[Span] = []
    for s in spans:
        sy = (s.bbox[1] + s.bbox[3]) / 2
        if abs(sy - cy) > y_tol:
            continue
        if s.text.strip() == target.label and abs(s.bbox[0] - target.bbox[0]) < 0.5:
            continue  # the candidate itself
        gap = target.bbox[0] - s.bbox[2]
        if 0.0 <= gap <= max_gap:
            out.append(s)
    return out


def _other_option_letters_on_same_line(
    target: "OptionMarker", spans: List[Span], y_tol: float = 3.0
) -> int:
    """Count other A/B/C/D-text spans on the same line as ``target``.

    A horizontal-row option layout (`A 1.4×10⁴  B 1.5×10¹⁵  C 1.8×10¹⁶  D 9.0×10¹⁶`)
    puts each marker right after the previous option's numeric value. When that
    happens we must NOT treat the markers as unit symbols just because numbers
    sit immediately to their left.
    """
    cy = target.y
    n = 0
    for s in spans:
        t = s.text.strip()
        if t not in OPTION_LABELS:
            continue
        sy = (s.bbox[1] + s.bbox[3]) / 2
        if abs(sy - cy) > y_tol:
            continue
        # Skip the candidate itself
        if t == target.label and abs(s.bbox[0] - target.bbox[0]) < 0.5:
            continue
        n += 1
    return n


def _looks_like_unit(target: "OptionMarker", spans: List[Span]) -> bool:
    """``"0.15 A"`` / ``"3 A"`` / ``"1.2 V"`` style: a numeric token sits
    immediately to the left of the candidate on the same line.

    Suppressed when the same line carries other A/B/C/D candidates — that
    indicates a horizontal-row option layout where each marker legitimately
    follows a numeric option-text segment.
    """
    if _other_option_letters_on_same_line(target, spans) >= 1:
        return False
    for s in _spans_immediately_left_of(target, spans):
        if _looks_numeric(s.text):
            return True
    return False


def _horizontal_score(combo: Tuple[Optional["OptionMarker"], ...]) -> float:
    present = [(i, m) for i, m in enumerate(combo) if m is not None]
    if len(present) < 2:
        return 1e6
    ys = [m.y for _, m in present]
    xs = [m.x for _, m in present]
    y_spread = max(ys) - min(ys)
    inv = 0
    for i in range(len(present)):
        for j in range(i + 1, len(present)):
            if xs[i] >= xs[j]:
                inv += 1
    return y_spread + inv * 50.0


def _vertical_score(combo: Tuple[Optional["OptionMarker"], ...]) -> float:
    present = [(i, m) for i, m in enumerate(combo) if m is not None]
    if len(present) < 2:
        return 1e6
    ys = [m.y for _, m in present]
    xs = [m.x for _, m in present]
    x_spread = max(xs) - min(xs)
    inv = 0
    for i in range(len(present)):
        for j in range(i + 1, len(present)):
            if ys[i] >= ys[j]:
                inv += 1
    return x_spread + inv * 50.0


def _grid_score(combo: Tuple[Optional["OptionMarker"], ...]) -> float:
    """2x2 grid: A=top-left, B=top-right, C=bottom-left, D=bottom-right."""
    if any(m is None for m in combo):
        return 1e6
    a, b, c, d = combo  # type: ignore[misc]
    ys = sorted([a.y, b.y, c.y, d.y])
    median_y = (ys[1] + ys[2]) / 2
    above = [m for m in (a, b, c, d) if m.y < median_y]
    below = [m for m in (a, b, c, d) if m.y >= median_y]
    if len(above) != 2 or len(below) != 2:
        return 1e6
    row_gap = min(m.y for m in below) - max(m.y for m in above)
    if row_gap < 8:
        return 1e6
    above_y_spread = max(m.y for m in above) - min(m.y for m in above)
    below_y_spread = max(m.y for m in below) - min(m.y for m in below)
    above_sorted = sorted(above, key=lambda m: m.bbox[0])
    below_sorted = sorted(below, key=lambda m: m.bbox[0])
    if above_sorted[0] is not a or above_sorted[1] is not b:
        return 1e6
    if below_sorted[0] is not c or below_sorted[1] is not d:
        return 1e6
    return above_y_spread + below_y_spread


def _tuple_score(
    combo: Tuple[Optional["OptionMarker"], ...],
    visual_bboxes: List[BBox],
) -> float:
    """Best-of horizontal/vertical/grid quality for one candidate tuple, plus
    penalties for missing labels and visual-region membership."""
    base = min(_horizontal_score(combo), _vertical_score(combo), _grid_score(combo))
    if base >= 1e5:
        # No reasonable interpretation; use a softer fallback so we still rank
        # tuples relatively rather than treating them all as identical garbage.
        present = [m for m in combo if m is not None]
        if len(present) < 2:
            return 1e6
        ys = [m.y for m in present]
        xs = [m.x for m in present]
        base = (max(ys) - min(ys)) + (max(xs) - min(xs))
    # Missing-marker penalty — prefer 4-marker tuples strongly.
    missing = sum(1 for m in combo if m is None)
    base += missing * 200.0
    # Visual-region penalty — soft signal, not absolute rejection.
    for m in combo:
        if m is None:
            continue
        for vb in visual_bboxes:
            if _bbox_contains(vb, m.bbox, tol=2.0):
                base += 25.0
                break
    # Bold-preference tiebreaker. Real Cambridge option markers are rendered
    # bold; non-bold "A"-spans are usually inline option text or units.
    for m in combo:
        if m is None:
            continue
        if not m.is_bold:
            base += 5.0
    return base


def find_option_markers(
    spans: List[Span],
    region: BBox,
    visual_bboxes: Optional[List[BBox]] = None,
) -> List[OptionMarker]:
    """Locate bold/large standalone A/B/C/D letters inside ``region`` using
    combinatorial alignment scoring.

    Why: a 1-letter "A" can come from many sources — a real option marker, a
    unit symbol like "0.15 A", a formula variable like "Avρ", a circuit-current
    annotation "3 A", or an axis label. The earlier two-pass pick (singletons
    first, then nearest-aligned) ties when many false candidates share a row or
    column with the real markers, and the wrong one wins.

    The new selection picks the (A, B, C, D) tuple with the lowest alignment
    score across three layouts:

        horizontal — small y-spread, A < B < C < D in **x**.
        vertical   — small x-spread, A < B < C < D in **y**.
        grid 2x2   — A=top-left, B=top-right, C=bottom-left, D=bottom-right with
                     a meaningful inter-row gap.

    Penalties:
        * unit-symbol candidates ("0.15 A") are filtered up front.
        * candidates inside any ``visual_bboxes`` get a soft penalty rather
          than absolute rejection (Q8/Q13-style image-grid markers can sit
          adjacent to an option diagram).
        * missing labels are penalised heavily so 4-marker tuples are strongly
          preferred when all four labels have at least one candidate.

    Per-label candidates are capped at 6 by left-margin proximity before the
    cartesian product, keeping the search bounded at ~6**4 = 1296 tuples.
    """
    visual_bboxes = list(visual_bboxes or [])

    # 1) Raw candidates: text is exactly A/B/C/D, bold or size>=9.5, inside region.
    raw: List[OptionMarker] = []
    for s in spans:
        t = s.text.strip()
        if t not in OPTION_LABELS:
            continue
        if not (s.is_bold or s.size >= 9.5):
            continue
        if not _inside(s.bbox, region):
            continue
        raw.append(
            OptionMarker(
                label=t, bbox=s.bbox, line_bbox=s.bbox, is_bold=s.is_bold,
            )
        )

    if not raw:
        return []

    # 2) Pre-filter: drop "<number> A" / "1.2 V" unit-symbol candidates.
    pre = [c for c in raw if not _looks_like_unit(c, spans)]
    if not pre:
        # Everything looked unit-like — fall back to raw rather than returning
        # nothing. Combinatorial scoring will still pick the best of a bad set.
        pre = raw

    # 3) Group by label and cap to 6 per label. Preference order before the cap:
    #    bold first, then candidates outside any visual region, then by left-x
    #    (real option markers tend to start at the left margin).
    def _candidate_priority(m: OptionMarker) -> Tuple[int, int, float, float]:
        in_visual = any(_bbox_contains(v, m.bbox, tol=2.0) for v in visual_bboxes)
        return (
            0 if m.is_bold else 1,
            1 if in_visual else 0,
            m.bbox[0],
            m.bbox[1],
        )

    by_label: dict[str, List[OptionMarker]] = {l: [] for l in OPTION_LABELS}
    for c in pre:
        by_label[c.label].append(c)
    for lbl, items in by_label.items():
        items.sort(key=_candidate_priority)
        if len(items) > 6:
            by_label[lbl] = items[:6]

    # 4) Cartesian product: missing-label slots get a single None placeholder.
    options_per_label: dict[str, List[Optional[OptionMarker]]] = {}
    for l in OPTION_LABELS:
        if by_label[l]:
            options_per_label[l] = list(by_label[l])
        else:
            options_per_label[l] = [None]

    if all(opts == [None] for opts in options_per_label.values()):
        return []

    best_combo: Optional[Tuple[Optional[OptionMarker], ...]] = None
    best_score = float("inf")
    for combo in product(*(options_per_label[l] for l in OPTION_LABELS)):
        present = [m for m in combo if m is not None]
        if len(present) < 2:
            continue
        # Skip combos with duplicate markers (same span chosen twice from a
        # multi-label collision — shouldn't happen, but guard).
        ids = {id(m) for m in present}
        if len(ids) != len(present):
            continue
        score = _tuple_score(combo, visual_bboxes)
        if score < best_score:
            best_score = score
            best_combo = combo

    if best_combo is None:
        return []
    chosen = [m for m in best_combo if m is not None]
    chosen.sort(key=lambda m: (m.bbox[1], m.bbox[0]))
    return chosen


def classify_option_layout(markers: List[OptionMarker]) -> str:
    """Return one of: vertical | horizontal | grid | partial."""
    if len(markers) < 4:
        return "partial"
    ys = [m.y for m in markers]
    xs = [m.x for m in markers]
    y_spread = max(ys) - min(ys)
    x_spread = max(xs) - min(xs)
    if y_spread < 8:
        return "horizontal"
    if x_spread < 10:
        return "vertical"
    return "grid"


def extract_option_text(
    page_lines: List[dict],
    markers: List[OptionMarker],
    next_boundary_y: float,
) -> dict[str, str]:
    """For inline-text options, gather text following each marker until the next marker."""
    layout = classify_option_layout(markers)
    out: dict[str, str] = {m.label: "" for m in markers}
    if not markers:
        return out

    if layout == "horizontal":
        # Markers sit on one visible row, but PyMuPDF may emit a separate line-dict
        # per marker. Gather spans from every line within a small y-band of the markers.
        line_y = sum(m.y for m in markers) / len(markers)
        spans_band: List[Span] = []
        x_max = 0.0
        for line in page_lines:
            ly = (line["bbox"][1] + line["bbox"][3]) / 2
            if abs(ly - line_y) <= 7.0:
                spans_band.extend(line["spans"])
                x_max = max(x_max, line["bbox"][2])
        sorted_m = sorted(markers, key=lambda m: m.bbox[0])
        for i, m in enumerate(sorted_m):
            x_start = m.bbox[2]
            x_end = sorted_m[i + 1].bbox[0] - 0.5 if i + 1 < len(sorted_m) else x_max
            out[m.label] = _spans_text_in_x(spans_band, x_start, x_end)
        return out

    if layout == "vertical":
        sorted_m = sorted(markers, key=lambda m: m.y)
        for i, m in enumerate(sorted_m):
            y_start = m.bbox[1] - 1
            y_end = sorted_m[i + 1].bbox[1] - 1 if i + 1 < len(sorted_m) else next_boundary_y
            out[m.label] = _collect_text_block(page_lines, m.bbox[2], y_start, y_end)
        return out

    # Grid / partial layouts: text-after-marker on the same line if any.
    # Bound the text on the right by any same-row neighbour marker so we don't
    # accidentally pull the next marker letter ('B') into A's text.
    for m in markers:
        line = _find_line_at_y(page_lines, m.y)
        if not line:
            continue
        same_row = sorted(
            [mm for mm in markers if abs(mm.y - m.y) <= 4],
            key=lambda mm: mm.bbox[0],
        )
        try:
            i = same_row.index(m)
        except ValueError:
            i = -1
        if 0 <= i < len(same_row) - 1:
            x_end = same_row[i + 1].bbox[0] - 1
        else:
            x_end = line["bbox"][2]
        out[m.label] = _spans_text_in_x(line["spans"], m.bbox[2], x_end)
    return out


def _inside(bbox: BBox, region: BBox) -> bool:
    cx = (bbox[0] + bbox[2]) / 2
    cy = (bbox[1] + bbox[3]) / 2
    return region[0] <= cx <= region[2] and region[1] <= cy <= region[3]


def _find_line_at_y(lines: List[dict], y: float, tol: float = 6.0) -> Optional[dict]:
    best: Optional[dict] = None
    best_d = tol
    for line in lines:
        ly = (line["bbox"][1] + line["bbox"][3]) / 2
        d = abs(ly - y)
        if d <= best_d:
            best = line
            best_d = d
    return best


def _spans_text_in_x(spans: List[Span], x0: float, x1: float) -> str:
    pieces: List[str] = []
    for s in spans:
        cx = (s.bbox[0] + s.bbox[2]) / 2
        if x0 <= cx <= x1:
            pieces.append(s.text)
    return "".join(pieces).strip()


def _collect_text_block(lines: List[dict], x_start: float, y_start: float, y_end: float) -> str:
    pieces: List[str] = []
    for line in lines:
        ly = (line["bbox"][1] + line["bbox"][3]) / 2
        if not (y_start <= ly <= y_end):
            continue
        # skip text fully to the left of the marker (avoids picking up other column markers)
        text = "".join(s.text for s in line["spans"] if s.bbox[0] >= x_start - 4)
        text = text.strip()
        if text:
            pieces.append(text)
    return " ".join(pieces).strip()
