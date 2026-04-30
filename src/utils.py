"""Generic helpers: bbox math, clustering, file naming, PDF metadata parsing."""
from __future__ import annotations

import re
from pathlib import Path
from typing import Iterable, List, Sequence, Tuple

BBox = Tuple[float, float, float, float]  # x0, y0, x1, y1


SUBJECT_CODES = {
    "9700": "Biology",
    "9701": "Chemistry",
    "9702": "Physics",
    "9709": "Mathematics",
    "9231": "Further Mathematics",
    "9608": "Computer Science",
    "9618": "Computer Science",
    "9699": "Sociology",
    "9708": "Economics",
}

SESSION_CODES = {
    "s": "May/June",
    "w": "October/November",
    "m": "February/March",
}


def parse_paper_metadata(pdf_path: Path) -> dict:
    """Parse Cambridge filename like 9702_m24_qp_12.pdf."""
    stem = pdf_path.stem
    parts = stem.split("_")
    meta = {"source_file": pdf_path.name, "paper_code": "", "subject": "", "session": "", "total_questions": 0}
    if len(parts) >= 4:
        code, sess, kind, num = parts[0], parts[1], parts[2], parts[3]
        meta["paper_code"] = f"{code}/{num}"
        meta["subject"] = SUBJECT_CODES.get(code, "Unknown")
        m = re.match(r"([smw])(\d{2})", sess)
        if m:
            label = SESSION_CODES.get(m.group(1), "")
            year = 2000 + int(m.group(2))
            meta["session"] = f"{label} {year}".strip()
    return meta


def bbox_area(b: BBox) -> float:
    return max(0.0, b[2] - b[0]) * max(0.0, b[3] - b[1])


def bbox_union(a: BBox, b: BBox) -> BBox:
    return (min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3]))


def bbox_intersects(a: BBox, b: BBox, pad: float = 0.0) -> bool:
    return not (a[2] + pad < b[0] or b[2] + pad < a[0] or a[3] + pad < b[1] or b[3] + pad < a[1])


def bbox_inside(inner: BBox, outer: BBox, tol: float = 2.0) -> bool:
    return (
        inner[0] >= outer[0] - tol
        and inner[1] >= outer[1] - tol
        and inner[2] <= outer[2] + tol
        and inner[3] <= outer[3] + tol
    )


def cluster_bboxes(boxes: Sequence[BBox], gap: float = 8.0) -> List[BBox]:
    """Union-find cluster of nearby bboxes; returns merged bboxes."""
    n = len(boxes)
    if n == 0:
        return []
    parent = list(range(n))

    def find(x: int) -> int:
        while parent[x] != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x

    def union(a: int, b: int) -> None:
        ra, rb = find(a), find(b)
        if ra != rb:
            parent[ra] = rb

    for i in range(n):
        for j in range(i + 1, n):
            if bbox_intersects(boxes[i], boxes[j], pad=gap):
                union(i, j)

    groups: dict[int, BBox] = {}
    for i, b in enumerate(boxes):
        r = find(i)
        groups[r] = bbox_union(groups[r], b) if r in groups else b
    return list(groups.values())


def pdf_to_pixel_scale(dpi: int) -> float:
    return dpi / 72.0


def pdf_bbox_to_pixel(bbox: BBox, dpi: int) -> Tuple[int, int, int, int]:
    s = pdf_to_pixel_scale(dpi)
    return (int(bbox[0] * s), int(bbox[1] * s), int(bbox[2] * s), int(bbox[3] * s))


def pad_bbox(b: BBox, pad: float, page_rect: BBox | None = None) -> BBox:
    out = (b[0] - pad, b[1] - pad, b[2] + pad, b[3] + pad)
    if page_rect:
        out = (
            max(out[0], page_rect[0]),
            max(out[1], page_rect[1]),
            min(out[2], page_rect[2]),
            min(out[3], page_rect[3]),
        )
    return out


def safe_filename(s: str) -> str:
    return re.sub(r"[^A-Za-z0-9_.-]", "_", s)
