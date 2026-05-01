"""Main extraction pipeline for Cambridge MCQ PDF papers."""
from __future__ import annotations

import json
import re
import shutil
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import fitz  # PyMuPDF
import pdfplumber
from PIL import Image

from .captions import (
    caption_for_option_image,
    caption_for_option_table,
    caption_for_question_diagram,
    caption_for_unknown,
)
from .debug import draw_debug_page, draw_debug_question
from .models import (
    CaptionPayload,
    DiagramLabel,
    ImageAsset,
    Option,
    OptionTable,
    Paper,
    PaperOutput,
    Question,
)
from .options import (
    OPTION_LABELS,
    OptionMarker,
    Span,
    classify_option_layout,
    extract_option_text,
    find_option_markers,
    get_lines,
    get_spans,
)
from .utils import (
    BBox,
    bbox_inside,
    bbox_union,
    pad_bbox,
    parse_paper_metadata,
    pdf_bbox_to_pixel,
    safe_filename,
)
from .expansion import expand_diagram_bbox, merge_split_diagrams
from .visuals import (
    VisualRegion,
    detect_tables_pdfplumber,
    detect_visual_regions,
    merge_tables_into_regions,
)

HEADER_MARGIN = 50.0
FOOTER_MARGIN = 60.0
SIDE_MARGIN = 36.0


# ---------------------------------------------------------------------------
# Page filtering
# ---------------------------------------------------------------------------

def _is_cover_page(page_text: str) -> bool:
    t = page_text.lower()
    return (
        "cambridge international" in t
        and "instructions" in t
        and "answer sheet" in t
    )


def _is_data_formulae_page(page_text: str) -> bool:
    t = page_text.lower()
    has_data = bool(re.search(r"\bdata\b", t)) and bool(
        re.search(r"acceleration of free fall|speed of light|elementary charge", t)
    )
    has_formulae = "formulae" in t
    return has_data or has_formulae


# ---------------------------------------------------------------------------
# Question number detection
# ---------------------------------------------------------------------------

@dataclass
class QuestionStart:
    number: int
    page: int  # 1-based
    bbox: BBox  # bbox of the number span
    y: float


def _find_question_starts(
    pages: List["PageInfo"], expected_max: int = 40
) -> List[QuestionStart]:
    """Greedy match increasing question numbers 1..N at the left margin."""
    candidates: List[QuestionStart] = []
    for p in pages:
        if not p.is_question_page:
            continue
        for span in p.spans:
            text = span.text.strip()
            if not text.isdigit():
                continue
            num = int(text)
            if num < 1 or num > expected_max:
                continue
            x0 = span.bbox[0]
            if x0 > 90:
                continue
            if not span.is_bold and span.size < 9.5:
                continue
            # ignore numbers that share their line with other digit-only bold spans
            # at similar y (these belong to vertical option grids etc.)
            if _is_in_table_cell(span.bbox, p):
                continue
            candidates.append(
                QuestionStart(
                    number=num,
                    page=p.page_index_1,
                    bbox=span.bbox,
                    y=span.bbox[1],
                )
            )

    candidates.sort(key=lambda c: (c.page, c.y))

    # Greedy: take the first occurrence of 1, then first occurrence of 2 after that, ...
    chosen: List[QuestionStart] = []
    expected = 1
    for c in candidates:
        if c.number != expected:
            continue
        if chosen and (c.page, c.y) <= (chosen[-1].page, chosen[-1].y):
            continue
        chosen.append(c)
        expected += 1
        if expected > expected_max:
            break
    return chosen


def _is_in_table_cell(bbox: BBox, page: "PageInfo") -> bool:
    for t in page.tables:
        if bbox_inside(bbox, t.bbox, tol=2.0):
            return True
    return False


# ---------------------------------------------------------------------------
# Per-page info
# ---------------------------------------------------------------------------

@dataclass
class PageInfo:
    page_index_1: int  # 1-based
    rect: BBox
    content_rect: BBox
    spans: List[Span]
    lines: List[dict]
    visuals: List[VisualRegion]
    tables: List[VisualRegion]
    text: str
    is_question_page: bool
    image_path: Path


def _build_page_info(
    page: fitz.Page,
    plumber_page,
    page_idx_1: int,
    image_path: Path,
) -> PageInfo:
    rect = (0.0, 0.0, float(page.rect.width), float(page.rect.height))
    content_rect = (
        SIDE_MARGIN,
        HEADER_MARGIN,
        rect[2] - SIDE_MARGIN,
        rect[3] - FOOTER_MARGIN,
    )

    spans = get_spans(page)
    lines = get_lines(page)
    text = page.get_text("text")

    is_question_page = not (_is_cover_page(text) or _is_data_formulae_page(text))

    visuals: List[VisualRegion] = []
    tables: List[VisualRegion] = []
    if is_question_page:
        # Word bboxes used to filter out tiny vector glyph paths from drawings (rare).
        word_bboxes = [s.bbox for s in spans]
        visuals = detect_visual_regions(page, content_rect, word_bboxes)
        tables = detect_tables_pdfplumber(plumber_page, content_rect)
        visuals = merge_tables_into_regions(visuals, tables)
        # Stage 1 of Diagram Context Expansion: merge regions that share a baseline
        # or column with a small gap (Q6 graph axes vs curve, Q29 mic + speaker).
        visuals = merge_split_diagrams(visuals)

    return PageInfo(
        page_index_1=page_idx_1,
        rect=rect,
        content_rect=content_rect,
        spans=spans,
        lines=lines,
        visuals=visuals,
        tables=tables,
        text=text,
        is_question_page=is_question_page,
        image_path=image_path,
    )


# ---------------------------------------------------------------------------
# Question region & extraction
# ---------------------------------------------------------------------------

@dataclass
class QuestionRegion:
    number: int
    page_start: int
    page_end: int
    slices: List[Tuple[int, BBox]]  # (page_index_1, bbox on that page)


def _build_question_regions(
    pages: List[PageInfo], starts: List[QuestionStart]
) -> List[QuestionRegion]:
    regions: List[QuestionRegion] = []
    page_map = {p.page_index_1: p for p in pages}
    question_page_indices = sorted(p.page_index_1 for p in pages if p.is_question_page)

    for i, qs in enumerate(starts):
        next_q = starts[i + 1] if i + 1 < len(starts) else None
        slices: List[Tuple[int, BBox]] = []

        # Collect every question page from qs.page through next boundary
        if next_q is None:
            end_page = question_page_indices[-1] if question_page_indices else qs.page
            end_y = page_map[end_page].content_rect[3]
        else:
            end_page = next_q.page
            end_y = next_q.y - 1

        for pidx in [p for p in question_page_indices if qs.page <= p <= end_page]:
            page = page_map[pidx]
            cr = page.content_rect
            top = qs.y if pidx == qs.page else cr[1]
            bottom = end_y if pidx == end_page else cr[3]
            if bottom - top < 5:
                continue
            slices.append((pidx, (cr[0], top, cr[2], bottom)))

        regions.append(
            QuestionRegion(
                number=qs.number,
                page_start=qs.page,
                page_end=end_page if next_q else slices[-1][0] if slices else qs.page,
                slices=slices,
            )
        )
    return regions


def _gather_visuals(
    qregion: QuestionRegion, pages_by_idx: Dict[int, PageInfo]
) -> List[Tuple[int, VisualRegion]]:
    out: List[Tuple[int, VisualRegion]] = []
    for pidx, slice_bbox in qregion.slices:
        page = pages_by_idx[pidx]
        for v in page.visuals:
            if bbox_inside(v.bbox, slice_bbox, tol=4.0):
                out.append((pidx, v))
    return out


def _gather_lines(
    qregion: QuestionRegion, pages_by_idx: Dict[int, PageInfo]
) -> List[Tuple[int, dict]]:
    out: List[Tuple[int, dict]] = []
    for pidx, slice_bbox in qregion.slices:
        page = pages_by_idx[pidx]
        for line in page.lines:
            if bbox_inside(line["bbox"], slice_bbox, tol=2.0):
                out.append((pidx, line))
    return out


def _gather_spans(
    qregion: QuestionRegion, pages_by_idx: Dict[int, PageInfo]
) -> List[Tuple[int, Span]]:
    out: List[Tuple[int, Span]] = []
    for pidx, slice_bbox in qregion.slices:
        page = pages_by_idx[pidx]
        for s in page.spans:
            if bbox_inside(s.bbox, slice_bbox, tol=2.0):
                out.append((pidx, s))
    return out


def _crop_asset(
    page_image_path: Path,
    bbox: BBox,
    dpi: int,
    out_path: Path,
    pad: int = 6,
) -> None:
    img = Image.open(page_image_path).convert("RGB")
    px = pdf_bbox_to_pixel(bbox, dpi)
    px = (
        max(0, px[0] - pad),
        max(0, px[1] - pad),
        min(img.width, px[2] + pad),
        min(img.height, px[3] + pad),
    )
    crop = img.crop(px)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    crop.save(out_path)


# ---------------------------------------------------------------------------
# Main pipeline
# ---------------------------------------------------------------------------

class PaperExtractor:
    def __init__(self, pdf_path: Path, output_root: Path, dpi: int = 200):
        self.pdf_path = pdf_path
        self.output_root = output_root
        self.dpi = dpi

        self.paper_dir = output_root / "papers" / safe_filename(pdf_path.stem)
        self.pages_dir = self.paper_dir / "pages"
        self.layout_dir = self.paper_dir / "layout"
        self.images_dir = self.paper_dir / "images"
        self.debug_dir = self.paper_dir / "debug"
        for d in (self.pages_dir, self.layout_dir, self.images_dir, self.debug_dir):
            d.mkdir(parents=True, exist_ok=True)

        self.warnings: List[str] = []

    # ---- rendering & layout dump ---------------------------------------

    def _render_page(self, page: fitz.Page, page_idx_1: int) -> Path:
        out = self.pages_dir / f"page_{page_idx_1:03d}.png"
        if not out.exists():
            mat = fitz.Matrix(self.dpi / 72.0, self.dpi / 72.0)
            pix = page.get_pixmap(matrix=mat, alpha=False)
            pix.save(out)
        return out

    def _dump_layout(self, page: fitz.Page, page_idx_1: int) -> None:
        out = self.layout_dir / f"page_{page_idx_1:03d}_layout.json"
        words = page.get_text("words")
        payload = {
            "page": page_idx_1,
            "size": [page.rect.width, page.rect.height],
            "words": [
                {
                    "bbox": [w[0], w[1], w[2], w[3]],
                    "text": w[4],
                    "block": w[5],
                    "line": w[6],
                    "word": w[7],
                }
                for w in words
            ],
        }
        out.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    # ---- main run ------------------------------------------------------

    def run(self) -> PaperOutput:
        meta = parse_paper_metadata(self.pdf_path)
        paper = Paper(**meta)

        doc = fitz.open(self.pdf_path)
        plumber = pdfplumber.open(self.pdf_path)
        try:
            pages: List[PageInfo] = []
            for i in range(len(doc)):
                page = doc[i]
                pidx_1 = i + 1
                img_path = self._render_page(page, pidx_1)
                self._dump_layout(page, pidx_1)
                p = _build_page_info(page, plumber.pages[i], pidx_1, img_path)
                pages.append(p)

            pages_by_idx = {p.page_index_1: p for p in pages}
            starts = _find_question_starts(pages)
            if len(starts) != 40:
                self.warnings.append(
                    f"Found {len(starts)} questions; expected 40."
                )

            regions = _build_question_regions(pages, starts)
            questions: List[Question] = []
            debug_annos: Dict[int, list] = {p.page_index_1: [] for p in pages}

            for region in regions:
                q, q_annos = self._extract_question(region, pages_by_idx)
                questions.append(q)
                for pidx, ann in q_annos:
                    debug_annos.setdefault(pidx, []).append(ann)

            paper.total_questions = len(questions)

            # Emit per-page debug images
            for p in pages:
                if not p.is_question_page:
                    continue
                annos = debug_annos.get(p.page_index_1, [])
                draw_debug_page(
                    p.image_path,
                    self.debug_dir / f"page_{p.page_index_1:03d}_debug.png",
                    annos,
                    self.dpi,
                )

            output = PaperOutput(paper=paper, questions=questions)
            (self.paper_dir / "questions.json").write_text(
                json.dumps(output.to_dict(), indent=2, ensure_ascii=False),
                encoding="utf-8",
            )
            return output
        finally:
            plumber.close()
            doc.close()

    # ---- question extraction ------------------------------------------

    def _extract_question(
        self,
        region: QuestionRegion,
        pages_by_idx: Dict[int, PageInfo],
    ) -> Tuple[Question, List[Tuple[int, Tuple[BBox, str, str]]]]:
        spans_q = _gather_spans(region, pages_by_idx)
        lines_q = _gather_lines(region, pages_by_idx)
        visuals_q = _gather_visuals(region, pages_by_idx)

        # Option markers live wherever the question's A/B/C/D actually appear; that
        # is usually (but not always) on the question's start page. Try each slice
        # and keep the one with the most markers found.
        markers: List[OptionMarker] = []
        last_pidx = region.slices[-1][0]
        last_slice = region.slices[-1][1]
        for pidx, slice_bbox in region.slices:
            page = pages_by_idx[pidx]
            ms = find_option_markers(page.spans, slice_bbox)
            if len(ms) > len(markers):
                markers = ms
                last_pidx = pidx
                last_slice = slice_bbox
        marker_layout = classify_option_layout(markers)

        # Boundary y used when extracting vertical option text (page bottom).
        next_boundary_y = last_slice[3]

        # Classify visuals → option_image / question_image / option_table
        debug_annos: List[Tuple[int, Tuple[BBox, str, str]]] = []
        question_images_between: List[ImageAsset] = []
        question_images_after: List[ImageAsset] = []
        option_images_by_label: Dict[str, List[ImageAsset]] = {l: [] for l in OPTION_LABELS}
        option_table: Optional[OptionTable] = None
        unknown_visuals: List[ImageAsset] = []

        warnings: List[str] = []
        caption_payloads: List[CaptionPayload] = []

        # --- question text (everything not inside a visual region or after a marker) ---
        question_lines = self._compose_question_lines(lines_q, markers, last_pidx, visuals_q)
        question_text = " ".join(t for _, _, t in question_lines).strip()
        nearby_text = question_text

        # 2x2 image-grid options (Q8/Q13 style) — crop option regions directly from
        # the rendered page using marker positions. This is much more reliable than
        # clustering vector paths when an option contains many strokes (balls,
        # arrows, axes, ticks, labels).
        grid_crops: Dict[str, BBox] = {}
        if marker_layout == "grid":
            grid_crops = self._compute_grid_option_crops(markers, last_slice)
            for label, crop_bbox in grid_crops.items():
                img_id = f"q{region.number:03d}_option_{label}"
                img_path = self.images_dir / f"{img_id}.png"
                _crop_asset(
                    pages_by_idx[last_pidx].image_path,
                    crop_bbox,
                    self.dpi,
                    img_path,
                    pad=0,
                )
                rel = img_path.relative_to(self.output_root).as_posix()
                cap = caption_for_option_image(label, nearby_text)
                option_images_by_label[label].append(
                    ImageAsset(
                        id=img_id,
                        image_path=rel,
                        page=last_pidx,
                        bbox=list(crop_bbox),
                        role="option_image",
                        caption=cap,
                        confidence=0.85,
                    )
                )
                caption_payloads.append(
                    CaptionPayload(
                        image_path=rel,
                        nearby_text=nearby_text,
                        question_number=region.number,
                        option_label=label,
                        suggested_role="option_image",
                    )
                )
                debug_annos.append((last_pidx, (crop_bbox, "option_image", f"opt {label}")))

        option_table_source_bbox: Optional[BBox] = None
        # Detect option_table: a table whose rows have first cell in {A,B,C,D}
        for pidx, v in visuals_q:
            if v.kind != "table":
                continue
            data = v.table_data or {}
            rows = data.get("rows", [])
            first_col = [
                (r[0].strip() if r and r[0] else "") for r in rows
            ]
            if any(c in OPTION_LABELS for c in first_col):
                page = pages_by_idx[pidx]
                # find page slice for this pidx
                slice_for_page = next((sb for (pp, sb) in region.slices if pp == pidx), last_slice)
                other_bboxes = [vv.bbox for (pp, vv) in visuals_q if pp == pidx and vv is not v]
                # Expand to absorb table key/legend (e.g. Q38 tick/cross meanings).
                expanded_bbox, table_labels = self._expand_visual(
                    v.bbox, page, slice_for_page, other_bboxes
                )
                option_table_source_bbox = v.bbox
                img_id = f"q{region.number:03d}_table_01"
                img_path = self.images_dir / f"{img_id}.png"
                _crop_asset(page.image_path, expanded_bbox, self.dpi, img_path)
                with_symbols = _table_has_symbol_rows(rows)
                option_table = OptionTable(
                    headers=data.get("headers", []),
                    rows=rows,
                    image_path=str(img_path.relative_to(self.output_root).as_posix()),
                    bbox=list(expanded_bbox),
                    diagram_labels=table_labels,
                )
                caption_payloads.append(
                    CaptionPayload(
                        image_path=option_table.image_path,
                        nearby_text=nearby_text,
                        question_number=region.number,
                        suggested_role="option_table",
                    )
                )
                debug_annos.append((pidx, (expanded_bbox, "option_table", f"Q{region.number} table")))
                if with_symbols:
                    warnings.append("Option table contains tick/cross symbols; cropped image saved as fallback.")

        # Determine y of first marker (boundary between question body and options)
        first_marker_y_on_last_page = (
            min(m.bbox[1] for m in markers) if markers else last_slice[3]
        )

        # Last text line of the question body (text above the first option marker on the last page)
        body_lines_last_page = [
            l for (pidx, l) in lines_q
            if pidx == last_pidx and l["bbox"][3] <= first_marker_y_on_last_page - 1
        ]
        last_body_y = max((l["bbox"][3] for l in body_lines_last_page), default=last_slice[1])

        # Classify remaining visuals
        for pidx, v in visuals_q:
            if v.kind == "table" and option_table_source_bbox is not None and tuple(v.bbox) == tuple(option_table_source_bbox):
                continue  # already handled

            # In grid layout, anything inside an option crop region is already covered.
            if grid_crops and pidx == last_pidx and any(
                _bbox_intersects(v.bbox, crop) for crop in grid_crops.values()
            ):
                continue

            page = pages_by_idx[pidx]

            # In grid layout, do not try to match visuals to markers — option images
            # are produced by the marker-driven crops above. Anything else here is
            # either a question diagram (e.g. Q8's balls P/Q above the grid) or noise.
            if grid_crops:
                assigned_label = None
            else:
                # Option image: marker exists and visual sits near it (same page)
                assigned_label = self._match_visual_to_option(v, markers, pidx, last_pidx)

            slice_for_page = next((sb for (pp, sb) in region.slices if pp == pidx), last_slice)
            other_bboxes = [vv.bbox for (pp, vv) in visuals_q if pp == pidx and vv is not v]

            if assigned_label:
                expanded_bbox, asset_labels = self._expand_visual(
                    v.bbox, page, slice_for_page, other_bboxes
                )
                idx = len(option_images_by_label[assigned_label]) + 1
                img_id = f"q{region.number:03d}_option_{assigned_label}{('_'+str(idx)) if idx > 1 else ''}"
                img_path = self.images_dir / f"{img_id}.png"
                _crop_asset(page.image_path, expanded_bbox, self.dpi, img_path)
                rel = img_path.relative_to(self.output_root).as_posix()
                cap = caption_for_option_image(assigned_label, nearby_text)
                asset = ImageAsset(
                    id=img_id,
                    image_path=rel,
                    page=pidx,
                    bbox=list(expanded_bbox),
                    role="option_image",
                    caption=cap,
                    confidence=0.7,
                    diagram_labels=asset_labels,
                )
                option_images_by_label[assigned_label].append(asset)
                caption_payloads.append(
                    CaptionPayload(
                        image_path=rel,
                        nearby_text=nearby_text,
                        question_number=region.number,
                        option_label=assigned_label,
                        suggested_role="option_image",
                    )
                )
                debug_annos.append((pidx, (expanded_bbox, "option_image", f"opt {assigned_label}")))
                continue

            # Question diagram (between vs after text)
            placement = "after"
            if pidx != last_pidx:
                placement = "between"
            else:
                # If there is question text below the visual (still on last page, before markers)
                has_text_below = any(
                    last_body_y > v.bbox[3]
                    and l["bbox"][1] > v.bbox[3] - 1
                    and l["bbox"][3] <= first_marker_y_on_last_page - 1
                    for l in body_lines_last_page
                )
                placement = "between" if has_text_below else "after"

            kind_label = (
                "question_image_between_text" if placement == "between" else "question_image_after_text"
            )
            idx = len(question_images_between) + len(question_images_after) + 1
            img_id = f"q{region.number:03d}_question_diagram_{idx:02d}"
            img_path = self.images_dir / f"{img_id}.png"
            # Stage 2: expand to include force values (Q2: 1200N/1600N), axis labels
            # (Q6, Q25), p/q/r markers (Q31), V arrow labels (Q35), etc.
            expanded_bbox, asset_labels = self._expand_visual(
                v.bbox, page, slice_for_page, other_bboxes,
                # diagrams typically have more horizontal headroom for labels than vertical
                h_threshold=80.0,
                v_threshold=30.0,
            )
            _crop_asset(page.image_path, expanded_bbox, self.dpi, img_path)
            rel = img_path.relative_to(self.output_root).as_posix()
            cap = caption_for_question_diagram(nearby_text, placement)
            asset = ImageAsset(
                id=img_id,
                image_path=rel,
                page=pidx,
                bbox=list(expanded_bbox),
                role=kind_label,
                caption=cap,
                confidence=0.7,
                diagram_labels=asset_labels,
            )
            if placement == "between":
                question_images_between.append(asset)
            else:
                question_images_after.append(asset)
            caption_payloads.append(
                CaptionPayload(
                    image_path=rel,
                    nearby_text=nearby_text,
                    question_number=region.number,
                    suggested_role=kind_label,
                )
            )
            debug_annos.append((pidx, (v.bbox, kind_label, f"Q{region.number}")))

        # Build options list
        options = self._build_options(
            markers, lines_q, last_pidx, next_boundary_y, option_images_by_label, option_table
        )

        # Validate options A-D
        present = {o.label for o in options}
        for label in OPTION_LABELS:
            if label not in present:
                warnings.append(f"Option {label} not found.")

        # Layout type
        layout_type = self._derive_layout_type(
            options, option_table, question_images_between, question_images_after
        )

        # Question region debug image (from start page only, for compactness)
        start_pidx = region.slices[0][0]
        start_slice = region.slices[0][1]
        q_debug_annos = [(ann[0], ann[1], ann[2]) for (pidx, ann) in debug_annos if pidx == start_pidx]
        try:
            draw_debug_question(
                pages_by_idx[start_pidx].image_path,
                self.debug_dir / f"q{region.number:03d}_debug.png",
                start_slice,
                q_debug_annos,
                self.dpi,
            )
        except Exception:
            pass

        # Page-level debug annotations
        page_debug_annos = [
            (pidx, (start_slice, "question", f"Q{region.number}"))
            for pidx, sb in [(start_pidx, start_slice)]
        ]
        page_debug_annos.extend(debug_annos)
        # Add option markers
        for m in markers:
            page_debug_annos.append((last_pidx, (m.bbox, "option_marker", m.label)))

        all_assets: List[ImageAsset] = []
        all_assets.extend(question_images_between)
        all_assets.extend(question_images_after)
        for label in OPTION_LABELS:
            all_assets.extend(option_images_by_label[label])

        needs_review = bool(warnings) or layout_type == "mixed"

        before_text, after_text = self._split_text_by_between_image(
            question_lines, question_images_between
        )

        question = Question(
            question_number=region.number,
            page_start=region.page_start,
            page_end=region.page_end,
            question_text=question_text,
            image_between_question_before_text=before_text,
            image_between_question_after_text=after_text,
            question_images_between_text=question_images_between,
            question_images_after_text=question_images_after,
            options=options,
            option_table=option_table,
            assets=all_assets,
            correct_answer=None,
            layout_type=layout_type,
            needs_review=needs_review,
            warnings=warnings,
            caption_llm_ready_payload=caption_payloads,
        )
        return question, page_debug_annos

    # ---- helpers -------------------------------------------------------

    def _compose_question_lines(
        self,
        lines_q: List[Tuple[int, dict]],
        markers: List[OptionMarker],
        last_pidx: int,
        visuals_q: List[Tuple[int, VisualRegion]],
    ) -> List[Tuple[int, BBox, str]]:
        """Return ordered ``(page, line_bbox, text)`` tuples that make up the
        question's prose. Used by both ``question_text`` and the
        before/after-image split for layout reconstruction.
        """
        first_marker_y = min((m.bbox[1] for m in markers), default=10**9)

        def _in_any_visual(line_bbox: BBox, pidx: int) -> bool:
            for vp, v in visuals_q:
                if vp != pidx:
                    continue
                if bbox_inside(line_bbox, v.bbox, tol=2.0):
                    return True
            return False

        out: List[Tuple[int, BBox, str]] = []
        for pidx, line in lines_q:
            if pidx == last_pidx and line["bbox"][1] >= first_marker_y - 1:
                continue
            if _in_any_visual(line["bbox"], pidx):
                continue
            text = line["text"].strip()
            if not text:
                continue
            if not out:
                # Strip leading question-number digits on the first kept line
                text = re.sub(r"^\s*\d{1,2}\s+", "", text)
            out.append((pidx, tuple(line["bbox"]), text))
        return out

    def _compose_question_text(
        self,
        lines_q: List[Tuple[int, dict]],
        markers: List[OptionMarker],
        last_pidx: int,
        visuals_q: List[Tuple[int, VisualRegion]],
    ) -> str:
        return " ".join(
            t for _, _, t in self._compose_question_lines(lines_q, markers, last_pidx, visuals_q)
        ).strip()

    def _split_text_by_between_image(
        self,
        question_lines: List[Tuple[int, BBox, str]],
        between_assets: List[ImageAsset],
    ) -> Tuple[Optional[str], Optional[str]]:
        """Split ``question_lines`` at the bottom edge of the topmost between-text
        diagram. Lines on earlier pages, or on the same page with center-y above
        the diagram's bottom, are ``before``; the rest are ``after``.

        Returns ``(None, None)`` when there is no between-text diagram so the
        fields stay null in the JSON.
        """
        if not between_assets:
            return None, None
        topmost = min(between_assets, key=lambda a: (a.page, a.bbox[1]))
        cut_page = topmost.page
        cut_y = topmost.bbox[3]
        before: List[str] = []
        after: List[str] = []
        for pidx, lb, text in question_lines:
            if pidx < cut_page:
                before.append(text)
                continue
            if pidx > cut_page:
                after.append(text)
                continue
            cy = (lb[1] + lb[3]) / 2
            if cy < cut_y:
                before.append(text)
            else:
                after.append(text)
        return (" ".join(before).strip() or None, " ".join(after).strip() or None)

    def _match_visual_to_option(
        self,
        visual: VisualRegion,
        markers: List[OptionMarker],
        visual_pidx: int,
        marker_pidx: int,
    ) -> Optional[str]:
        if not markers or visual_pidx != marker_pidx:
            return None
        layout = classify_option_layout(markers)

        # In all option-image layouts, every marker should sit just above its image.
        # Find the marker whose center-x is closest to the visual's center-x AND
        # which lies above the visual within a reasonable distance.
        vcx = (visual.bbox[0] + visual.bbox[2]) / 2
        vcy = (visual.bbox[1] + visual.bbox[3]) / 2
        v_top = visual.bbox[1]

        # Markers above the visual
        above = [m for m in markers if m.bbox[3] <= visual.bbox[1] + 4]
        if above:
            best = min(above, key=lambda m: (abs(((m.bbox[0]+m.bbox[2])/2) - vcx), abs(m.bbox[3] - v_top)))
            # Sanity: not too far horizontally (within visual's width plus a bit)
            mx = (best.bbox[0] + best.bbox[2]) / 2
            if abs(mx - vcx) <= max(80, (visual.bbox[2] - visual.bbox[0]) * 0.6):
                # And not too far above (within ~120pt)
                if v_top - best.bbox[3] <= 120:
                    return best.label

        # Inline horizontal layout: visual on the right of marker on the same line
        if layout == "horizontal":
            for m in markers:
                if abs(m.y - vcy) <= 12 and m.bbox[2] <= visual.bbox[0] + 4:
                    return m.label
        return None

    def _build_options(
        self,
        markers: List[OptionMarker],
        lines_q: List[Tuple[int, dict]],
        last_pidx: int,
        next_boundary_y: float,
        option_images: Dict[str, List[ImageAsset]],
        option_table: Optional[OptionTable],
    ) -> List[Option]:
        options: List[Option] = []

        # 1) If we have an option_table, synthesize 4 Options from its rows.
        if option_table:
            for row in option_table.rows:
                if not row:
                    continue
                first = (row[0] or "").strip()
                if first not in OPTION_LABELS:
                    continue
                rest = [c.strip() if c else "" for c in row[1:]]
                text = " | ".join([c for c in rest if c])
                options.append(Option(label=first, text=text))
            # ensure all 4
            present = {o.label for o in options}
            for lbl in OPTION_LABELS:
                if lbl not in present:
                    options.append(Option(label=lbl))
            options.sort(key=lambda o: OPTION_LABELS.index(o.label))
            return options

        # 2) Otherwise use markers on the last page
        if not markers:
            return options

        # Page lines on the last page only (markers always share a page)
        last_page_lines = [l for (pidx, l) in lines_q if pidx == last_pidx]

        text_by_label = extract_option_text(last_page_lines, markers, next_boundary_y)

        for label in OPTION_LABELS:
            if label not in {m.label for m in markers}:
                continue
            options.append(
                Option(
                    label=label,
                    text=text_by_label.get(label, "").strip(),
                    images=option_images.get(label, []),
                )
            )
        return options

    def _expand_visual(
        self,
        bbox: BBox,
        page: "PageInfo",
        slice_bbox: BBox,
        other_bboxes: List[BBox],
        h_threshold: float = 70.0,
        v_threshold: float = 35.0,
    ) -> Tuple[BBox, List[DiagramLabel]]:
        """Stage 2 of Diagram Context Expansion: grow ``bbox`` to include nearby
        aligned text labels. Stops at ``slice_bbox`` and refuses to grow into any
        ``other_bboxes`` (other diagrams in the same question on the same page).
        """
        candidates: List[dict] = []
        for line in page.lines:
            lb = tuple(line["bbox"])
            if not bbox_inside(lb, slice_bbox, tol=2.0):
                continue
            if bbox_inside(lb, bbox, tol=1.0):
                continue
            text = line["text"].strip()
            if not text:
                continue
            candidates.append({"bbox": lb, "text": text})
        expanded, raw_labels = expand_diagram_bbox(
            bbox,
            candidates,
            slice_bbox,
            other_bboxes=other_bboxes,
            h_threshold=h_threshold,
            v_threshold=v_threshold,
        )
        labels = [
            DiagramLabel(text=t, bbox=list(b), confidence=c) for (b, t, c) in raw_labels
        ]
        return expanded, labels

    def _compute_grid_option_crops(
        self, markers: List[OptionMarker], region_bbox: BBox
    ) -> Dict[str, BBox]:
        """For 2x2 image grids, derive a crop bbox per option from marker positions.

        Top row gets the smaller y; left column the smaller x. The crop extends from
        just below the marker label down to (a) the row of markers below for the top
        row, or (b) the bottom of the question region for the bottom row. Horizontal
        split is the midpoint between the two markers in each row.
        """
        if len(markers) < 4:
            return {}
        by_y = sorted(markers, key=lambda m: m.bbox[1])
        # Split into top and bottom row using the gap between the 2nd and 3rd marker
        gap_y = (by_y[1].bbox[3] + by_y[2].bbox[1]) / 2
        top = sorted([m for m in by_y if m.bbox[1] < gap_y], key=lambda m: m.bbox[0])
        bot = sorted([m for m in by_y if m.bbox[1] >= gap_y], key=lambda m: m.bbox[0])
        if len(top) != 2 or len(bot) != 2:
            return {}

        x_left = region_bbox[0]
        x_right = region_bbox[2]
        x_mid = (
            ((top[0].bbox[2] + top[1].bbox[0]) / 2)
            + ((bot[0].bbox[2] + bot[1].bbox[0]) / 2)
        ) / 2

        y_top_top = max(top[0].bbox[3], top[1].bbox[3]) + 2
        y_top_bot = min(bot[0].bbox[1], bot[1].bbox[1]) - 4
        y_bot_top = max(bot[0].bbox[3], bot[1].bbox[3]) + 2
        y_bot_bot = region_bbox[3]

        return {
            top[0].label: (x_left, y_top_top, x_mid, y_top_bot),
            top[1].label: (x_mid, y_top_top, x_right, y_top_bot),
            bot[0].label: (x_left, y_bot_top, x_mid, y_bot_bot),
            bot[1].label: (x_mid, y_bot_top, x_right, y_bot_bot),
        }

    def _derive_layout_type(
        self,
        options: List[Option],
        option_table: Optional[OptionTable],
        between: List[ImageAsset],
        after: List[ImageAsset],
    ) -> str:
        has_q_diagram = bool(between or after)
        has_option_images = any(o.images for o in options)
        has_option_table = option_table is not None

        if has_option_table:
            return "option_table"
        if has_q_diagram and has_option_images:
            return "question_diagram_and_option_images"
        if has_option_images:
            return "option_images"
        if has_q_diagram:
            return "question_diagram"
        if not has_q_diagram and not has_option_images and not has_option_table and any(o.text for o in options):
            return "text_only"
        return "mixed"


def _bbox_intersects(a: BBox, b: BBox) -> bool:
    return not (a[2] < b[0] or b[2] < a[0] or a[3] < b[1] or b[3] < a[1])


def _table_has_symbol_rows(rows: list[list[str]]) -> bool:
    """Heuristic: rows whose non-A/B/C/D cells are mostly empty or full of unmapped
    glyphs (Symbol-font ticks/crosses come through as `(cid:NN)` or U+F000-range chars)."""
    non_label_cells = 0
    suspect_cells = 0
    for r in rows:
        for j, c in enumerate(r):
            if j == 0 and (c or "").strip() in OPTION_LABELS:
                continue
            non_label_cells += 1
            text = (c or "").strip()
            if not text:
                suspect_cells += 1
                continue
            if "(cid:" in text or any(0xE000 <= ord(ch) <= 0xF8FF for ch in text):
                suspect_cells += 1
    return non_label_cells > 0 and suspect_cells / non_label_cells >= 0.5


# ---------------------------------------------------------------------------
# Top-level driver
# ---------------------------------------------------------------------------

def extract_paper(pdf_path: Path, output_root: Path, dpi: int = 200) -> dict:
    extractor = PaperExtractor(pdf_path, output_root, dpi=dpi)
    output = extractor.run()
    summary = {
        "source_file": pdf_path.name,
        "paper_code": output.paper.paper_code,
        "subject": output.paper.subject,
        "session": output.paper.session,
        "total_questions": output.paper.total_questions,
        "warnings": extractor.warnings + [
            f"Q{q.question_number}: {w}" for q in output.questions for w in q.warnings
        ],
        "output_dir": str(extractor.paper_dir.relative_to(output_root).as_posix()),
    }
    return summary


def extract_folder(input_dir: Path, output_dir: Path, dpi: int = 200) -> dict:
    output_dir.mkdir(parents=True, exist_ok=True)
    pdfs = sorted([p for p in input_dir.glob("*.pdf")])
    if not pdfs:
        print(f"[warn] no PDFs found in {input_dir}")

    manifest = {"papers": []}
    for pdf in pdfs:
        print(f"[info] processing {pdf.name}")
        try:
            summary = extract_paper(pdf, output_dir, dpi=dpi)
        except Exception as exc:  # noqa: BLE001
            print(f"[error] {pdf.name}: {exc!r}")
            summary = {"source_file": pdf.name, "error": repr(exc)}
        manifest["papers"].append(summary)
        for w in summary.get("warnings", []):
            print(f"[warn] {pdf.name}: {w}")

    (output_dir / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    return manifest
