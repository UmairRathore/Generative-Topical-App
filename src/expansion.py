"""Diagram Context Expansion.

Cropping the raw vector cluster of a diagram is rarely enough — the diagram is only
semantically complete with its associated labels: force values ("1200 N"), axis
labels ("velocity / m s^-1", "time / s"), tick numbers ("0", "0.5", "1.0"), key
legends, p/q/r markers, etc.

Two stages here:

1. ``merge_split_diagrams`` — merge regions that share a baseline / column with a
   small gap (e.g. a graph whose axes, curve and tick set arrive as separate
   clusters; or a microphone + speaker that conceptually form one apparatus).

2. ``expand_diagram_bbox`` — grow the bbox outward to include nearby short text
   labels that align horizontally or vertically with the diagram. Stops at the
   question region boundary and refuses to grow into another diagram. Returns the
   expanded bbox and the list of attached ``(bbox, text, confidence)`` labels so
   the caller can persist them as ``diagram_labels``.

Filter rules applied throughout:
- ignore visuals fully inside text spans (handled upstream in ``visuals.py``)
- ignore visuals smaller than the area threshold (also upstream)
- ignore paragraph-style sentences when expanding (label-width / question-mark check)
"""
from __future__ import annotations

import re
from typing import List, Tuple

from .utils import BBox, bbox_union
from .visuals import VisualRegion


# ---------------------------------------------------------------------------
# bbox helpers
# ---------------------------------------------------------------------------

def _y_overlap_frac(a: BBox, b: BBox) -> float:
    inter = max(0.0, min(a[3], b[3]) - max(a[1], b[1]))
    h = min(a[3] - a[1], b[3] - b[1])
    return inter / h if h > 0 else 0.0


def _x_overlap_frac(a: BBox, b: BBox) -> float:
    inter = max(0.0, min(a[2], b[2]) - max(a[0], b[0]))
    w = min(a[2] - a[0], b[2] - b[0])
    return inter / w if w > 0 else 0.0


def _gap_x(a: BBox, b: BBox) -> float:
    return max(0.0, max(b[0] - a[2], a[0] - b[2]))


def _gap_y(a: BBox, b: BBox) -> float:
    return max(0.0, max(b[1] - a[3], a[1] - b[3]))


def _bbox_contains(outer: BBox, inner: BBox, tol: float = 1.0) -> bool:
    return (
        inner[0] >= outer[0] - tol
        and inner[1] >= outer[1] - tol
        and inner[2] <= outer[2] + tol
        and inner[3] <= outer[3] + tol
    )


def _bbox_overlap(a: BBox, b: BBox) -> bool:
    return not (a[2] < b[0] or b[2] < a[0] or a[3] < b[1] or b[3] < a[1])


# ---------------------------------------------------------------------------
# Stage 1: merge split diagrams
# ---------------------------------------------------------------------------

def merge_split_diagrams(
    visuals: List[VisualRegion],
    h_gap: float = 50.0,
    v_gap: float = 28.0,
    overlap_thresh: float = 0.4,
) -> List[VisualRegion]:
    """Merge regions that share a baseline / column with a small gap.

    Two regions merge when:
      - they overlap >= ``overlap_thresh`` in the y dimension AND are within
        ``h_gap`` horizontally (graph axes, side-by-side apparatus pieces), OR
      - they overlap >= ``overlap_thresh`` in the x dimension AND are within
        ``v_gap`` vertically (graph curve sitting just above the x-axis).

    Tables are never merged with non-tables; image regions keep precedence over
    pure-drawing regions when merging.
    """
    work = list(visuals)
    changed = True
    while changed:
        changed = False
        for i in range(len(work)):
            for j in range(i + 1, len(work)):
                a, b = work[i], work[j]
                if (a.kind == "table") != (b.kind == "table"):
                    continue
                yo = _y_overlap_frac(a.bbox, b.bbox)
                xo = _x_overlap_frac(a.bbox, b.bbox)
                hd = _gap_x(a.bbox, b.bbox)
                vd = _gap_y(a.bbox, b.bbox)
                if (yo >= overlap_thresh and hd <= h_gap) or (
                    xo >= overlap_thresh and vd <= v_gap
                ):
                    new_kind = a.kind
                    if a.kind == "drawing" and b.kind != "drawing":
                        new_kind = b.kind
                    raster = a.raster_xref or b.raster_xref
                    table_data = a.table_data or b.table_data
                    work[i] = VisualRegion(
                        bbox=bbox_union(a.bbox, b.bbox),
                        kind=new_kind,
                        raster_xref=raster,
                        table_data=table_data,
                    )
                    work.pop(j)
                    changed = True
                    break
            if changed:
                break
    return work


# ---------------------------------------------------------------------------
# Stage 2: bbox expansion + label association
# ---------------------------------------------------------------------------

_SENTENCE_STARTERS = (
    "They ", "The ", "A ", "An ", "It ", "If ", "When ", "Which ", "What ",
    "These ", "This ", "Both ", "How ", "Why ", "Where ", "Each ", "Some ",
    "There ", "Here ", "Their ", "Its ",
)

# Figure captions that belong inside the crop even though they read like
# multi-word text. Manual QA found these systematically excluded ("NOT TO
# SCALE", "before/after collision", "view from above", "eye of student").
_CAPTION_WHITELIST = re.compile(
    r"(?i)^\(?\s*("
    r"not to scale"
    r"|view from (above|the side|behind)"
    r"|(before|after)( the)? (collision|impact)"
    r"|eye of (the )?(student|observer)"
    r"|diagram \d"
    r")\s*\)?$"
)


def _is_label_like(line_bbox: BBox, text: str, max_width: float = 160.0) -> bool:
    """Reject paragraph-style sentences. Genuine diagram labels (force values,
    axis labels, ticks, p/q/r markers, single-word callouts) are always short
    and never look like full sentences."""
    t_raw = (text or "").strip()
    if _CAPTION_WHITELIST.match(t_raw):
        return True
    width = line_bbox[2] - line_bbox[0]
    if width > max_width:
        return False
    t = t_raw
    if not t:
        return False
    if t.endswith("?") or t.endswith("."):
        return False
    if len(t) > 60:
        return False
    words = t.split()
    if len(words) > 5:
        return False
    # Capitalised sentence starts (e.g. "They collide and join together") are
    # never legitimate diagram labels.
    if len(words) >= 3 and any(t.startswith(s) for s in _SENTENCE_STARTERS):
        return False
    return True


def expand_diagram_bbox(
    bbox: BBox,
    page_elements: List[dict],
    region_bbox: BBox,
    other_bboxes: List[BBox] | None = None,
    h_threshold: float = 70.0,
    v_threshold: float = 35.0,
    label_max_width: float = 160.0,
) -> Tuple[BBox, List[Tuple[BBox, str, float]]]:
    """Expand ``bbox`` by attaching nearby aligned text labels.

    ``page_elements`` items must be dicts with at least ``bbox`` and ``text`` keys.
    The expansion stops at ``region_bbox`` (the question slice on the page) and
    refuses to grow into ``other_bboxes`` (other diagrams on the same page).

    Returns ``(expanded_bbox, labels)`` where each label is
    ``(bbox, text, confidence)``. Horizontal expansion is allowed further than
    vertical, matching the typical Cambridge layout where labels sit beside a
    diagram (axes, force arrows, slit markers).
    """
    other = list(other_bboxes or [])
    expanded = bbox
    labels: List[Tuple[BBox, str, float]] = []
    used: set[int] = set()

    while True:
        added = False
        for i, e in enumerate(page_elements):
            if i in used:
                continue
            wb: BBox = tuple(e["bbox"])  # type: ignore[assignment]
            text: str = e.get("text", "")
            if not _is_label_like(wb, text, max_width=label_max_width):
                continue
            if not _bbox_contains(region_bbox, wb, tol=2.0):
                continue
            if any(_bbox_contains(o, wb, tol=2.0) for o in other):
                continue
            if _bbox_contains(expanded, wb, tol=1.0):
                used.add(i)
                continue

            yo = _y_overlap_frac(expanded, wb)
            xo = _x_overlap_frac(expanded, wb)
            hd = _gap_x(expanded, wb)
            vd = _gap_y(expanded, wb)

            attach = False
            confidence = 0.6
            # text on the left/right of the diagram, on the diagram's row(s)
            if yo >= 0.3 and hd <= h_threshold:
                attach = True
                confidence = 0.9 if hd <= 25 else 0.75
            # text above/below the diagram, in the diagram's column(s)
            elif xo >= 0.3 and vd <= v_threshold:
                attach = True
                confidence = 0.85 if vd <= 15 else 0.7
            # touching corner (rare, but happens for sub-/super-script glyphs)
            elif hd <= 6 and vd <= 6:
                attach = True
                confidence = 0.95

            if not attach:
                continue
            tentative = bbox_union(expanded, wb)
            if any(_bbox_overlap(tentative, o) for o in other):
                continue

            expanded = tentative
            labels.append((wb, text.strip(), confidence))
            used.add(i)
            added = True
        if not added:
            break

    return expanded, labels
