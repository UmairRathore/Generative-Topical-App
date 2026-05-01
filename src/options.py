"""Detect MCQ option markers (A/B/C/D) and parse their text/layout."""
from __future__ import annotations

from dataclasses import dataclass, field
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


def find_option_markers(spans: List[Span], region: BBox) -> List[OptionMarker]:
    """Locate bold standalone A/B/C/D letters inside region.

    When a label has multiple candidates (e.g. a "3 A" current annotation
    inside a circuit diagram + the real option "A" further down), pick the one
    that *aligns* with the already-chosen markers — labels with a single
    candidate are processed first, then ambiguous labels resolve to whichever
    candidate sits closest in x or y to that anchor set.
    """
    candidates: List[OptionMarker] = []
    for s in spans:
        t = s.text.strip()
        if t not in OPTION_LABELS:
            continue
        if not (s.is_bold or s.size >= 9.5):
            continue
        if not _inside(s.bbox, region):
            continue
        candidates.append(OptionMarker(label=t, bbox=s.bbox, line_bbox=s.bbox))

    by_label: dict[str, List[OptionMarker]] = {l: [] for l in OPTION_LABELS}
    for c in candidates:
        by_label[c.label].append(c)

    chosen: List[OptionMarker] = []
    deferred: List[Tuple[str, List[OptionMarker]]] = []
    # Pass 1 — singletons (these are unambiguous anchors).
    for label in OPTION_LABELS:
        items = by_label[label]
        if not items:
            continue
        if len(items) == 1:
            chosen.append(items[0])
        else:
            deferred.append((label, items))

    # Pass 2 — multi-candidate labels: pick the option whose row OR column lines
    # up with an already-chosen marker. Falls back to lowest-y-first when no
    # anchors exist yet.
    for label, items in deferred:
        if chosen:
            ref_ys = [m.y for m in chosen]
            ref_xs = [m.x for m in chosen]

            def _score(m: OptionMarker) -> float:
                dy = min(abs(m.y - ry) for ry in ref_ys)
                dx = min(abs(m.x - rx) for rx in ref_xs)
                return min(dy, dx)

            items_sorted = sorted(items, key=_score)
        else:
            items_sorted = sorted(items, key=lambda m: (m.bbox[1], m.bbox[0]))
        chosen.append(items_sorted[0])

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
