"""Detect visual regions on a PDF page: vector drawings, raster images, tables."""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import List, Tuple

import fitz  # PyMuPDF

from .utils import BBox, bbox_area, bbox_inside, bbox_union, cluster_bboxes


@dataclass
class VisualRegion:
    bbox: BBox
    kind: str = "drawing"  # drawing | image | table
    raster_xref: int | None = None
    table_data: dict | None = None  # {"headers": [...], "rows": [[...]]}


def _drawing_bboxes(page: fitz.Page) -> List[BBox]:
    out: List[BBox] = []
    for d in page.get_drawings():
        rect = d.get("rect")
        if rect is None:
            continue
        x0, y0, x1, y1 = rect
        if x1 - x0 < 1 and y1 - y0 < 1:
            continue
        # skip page-spanning rectangles (often page background)
        page_w = page.rect.width
        if (x1 - x0) > 0.85 * page_w and (y1 - y0) > 0.85 * page.rect.height:
            continue
        out.append((float(x0), float(y0), float(x1), float(y1)))
    return out


def _image_regions(page: fitz.Page) -> List[VisualRegion]:
    out: List[VisualRegion] = []
    for img in page.get_images(full=True):
        xref = img[0]
        try:
            rects = page.get_image_rects(xref)
        except Exception:
            rects = []
        for r in rects:
            out.append(
                VisualRegion(
                    bbox=(float(r.x0), float(r.y0), float(r.x1), float(r.y1)),
                    kind="image",
                    raster_xref=xref,
                )
            )
    return out


def _is_glyph_fragment(b: BBox, text_bboxes: List[BBox], pad: float = 1.5) -> bool:
    """Drop drawings that are fully contained inside a text span — fraction bars,
    root vinculums, parentheses-as-paths, etc. A real diagram element will always
    extend beyond the span bbox of any single label."""
    for tb in text_bboxes:
        if (
            b[0] >= tb[0] - pad
            and b[1] >= tb[1] - pad
            and b[2] <= tb[2] + pad
            and b[3] <= tb[3] + pad
        ):
            return True
    return False


def detect_visual_regions(
    page: fitz.Page,
    content_rect: BBox,
    text_span_bboxes: List[BBox],
    cluster_gap: float = 8.0,
    min_area: float = 1000.0,
) -> List[VisualRegion]:
    """Cluster vector drawings + raster images into visual regions inside content_rect.

    text_span_bboxes is used to drop drawing fragments that overlap text — typically
    fraction bars, square-root vincula, and other equation glyphs that PyMuPDF reports
    as drawings.
    """
    drawings = _drawing_bboxes(page)
    drawings = [b for b in drawings if bbox_inside(b, content_rect, tol=4.0)]

    # Drop equation/glyph fragments (tiny drawings overlapping text spans)
    drawings = [b for b in drawings if not _is_glyph_fragment(b, text_span_bboxes)]

    # Cluster drawings (vector paths that compose a single diagram)
    clustered = cluster_bboxes(drawings, gap=cluster_gap)
    clustered = [b for b in clustered if bbox_area(b) >= min_area]

    regions: List[VisualRegion] = [VisualRegion(bbox=b, kind="drawing") for b in clustered]

    # Add raster images (and merge with overlapping drawing clusters)
    for img_region in _image_regions(page):
        if not bbox_inside(img_region.bbox, content_rect, tol=4.0):
            continue
        merged = False
        for r in regions:
            if _overlap(r.bbox, img_region.bbox):
                r.bbox = bbox_union(r.bbox, img_region.bbox)
                r.kind = "image"
                r.raster_xref = img_region.raster_xref
                merged = True
                break
        if not merged:
            regions.append(img_region)

    return regions


def _overlap(a: BBox, b: BBox) -> bool:
    return not (a[2] < b[0] or b[2] < a[0] or a[3] < b[1] or b[3] < a[1])


def _normalize_multiline_rows(rows: list[list[str]]) -> list[list[str]]:
    """If a row has \n-separated values per cell (one cell per option), expand into N rows."""
    if not rows:
        return rows
    expanded: list[list[str]] = []
    for row in rows:
        if not row:
            continue
        sub = [(c or "").split("\n") for c in row]
        n = max((len(s) for s in sub), default=1)
        if n == 1:
            expanded.append([(c or "").strip() for c in row])
            continue
        for i in range(n):
            expanded.append([s[i].strip() if i < len(s) else "" for s in sub])
    return expanded


def _table_is_empty(rows: list[list[str]]) -> bool:
    return not rows or all(not (c or "").strip() for r in rows for c in r)


def detect_tables_pdfplumber(plumber_page, content_rect: BBox) -> List[VisualRegion]:
    """Use pdfplumber to find tables; return regions inside content_rect.

    Drops spurious empty tables (lines inside diagrams sometimes look like a grid).
    Splits multi-line cells when the table packs all four options into one row.
    """
    out: List[VisualRegion] = []
    try:
        tables = plumber_page.find_tables()
    except Exception:
        return out
    for t in tables:
        bbox = (float(t.bbox[0]), float(t.bbox[1]), float(t.bbox[2]), float(t.bbox[3]))
        if not bbox_inside(bbox, content_rect, tol=6.0):
            continue
        try:
            data = t.extract()
        except Exception:
            data = []
        if not data:
            continue
        normalized = _normalize_multiline_rows(data)
        if _table_is_empty(normalized):
            continue
        # First row is headers if its first cell is empty/non-option; else everything is data
        headers: list[str] = []
        rows: list[list[str]] = list(normalized)
        first = rows[0]
        first_cell = (first[0] or "").strip()
        if first_cell not in {"A", "B", "C", "D"}:
            headers = first
            rows = rows[1:]
        out.append(
            VisualRegion(
                bbox=bbox,
                kind="table",
                table_data={"headers": headers, "rows": rows},
            )
        )
    return out


def merge_tables_into_regions(
    visuals: List[VisualRegion], tables: List[VisualRegion]
) -> List[VisualRegion]:
    """Replace any visual region overlapping a detected table with the table region."""
    final: List[VisualRegion] = list(tables)
    for v in visuals:
        if any(_overlap(v.bbox, t.bbox) for t in tables):
            continue
        final.append(v)
    return final
