"""Debug image rendering: draw bounding boxes for questions, visuals, options, tables."""
from __future__ import annotations

from pathlib import Path
from typing import Iterable, Tuple

from PIL import Image, ImageDraw, ImageFont

from .utils import BBox, pdf_bbox_to_pixel

COLORS = {
    "question": (255, 0, 0),
    "question_image_between_text": (0, 128, 255),
    "question_image_after_text": (0, 200, 255),
    "option_image": (0, 200, 0),
    "option_table": (200, 0, 200),
    "option_marker": (255, 165, 0),
    "table": (200, 0, 200),
    "unknown_visual_asset": (128, 128, 128),
}


def _font() -> ImageFont.ImageFont:
    try:
        return ImageFont.truetype("arial.ttf", 14)
    except Exception:
        return ImageFont.load_default()


def draw_debug_page(
    page_image_path: Path,
    out_path: Path,
    annotations: Iterable[Tuple[BBox, str, str]],
    dpi: int,
) -> None:
    """annotations: iterable of (bbox_pdf, role, label)."""
    img = Image.open(page_image_path).convert("RGB")
    draw = ImageDraw.Draw(img)
    font = _font()
    for bbox, role, label in annotations:
        color = COLORS.get(role, (128, 128, 128))
        px = pdf_bbox_to_pixel(bbox, dpi)
        draw.rectangle(px, outline=color, width=3)
        if label:
            draw.text((px[0] + 4, max(0, px[1] - 16)), label, fill=color, font=font)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    img.save(out_path)


def draw_debug_question(
    page_image_path: Path,
    out_path: Path,
    question_bbox: BBox,
    annotations: Iterable[Tuple[BBox, str, str]],
    dpi: int,
    pad: int = 8,
) -> None:
    """Crop the page to the question bbox and draw annotations within it."""
    img = Image.open(page_image_path).convert("RGB")
    qpx = pdf_bbox_to_pixel(question_bbox, dpi)
    qpx = (
        max(0, qpx[0] - pad),
        max(0, qpx[1] - pad),
        min(img.width, qpx[2] + pad),
        min(img.height, qpx[3] + pad),
    )
    crop = img.crop(qpx)
    draw = ImageDraw.Draw(crop)
    font = _font()
    for bbox, role, label in annotations:
        color = COLORS.get(role, (128, 128, 128))
        px = pdf_bbox_to_pixel(bbox, dpi)
        rel = (px[0] - qpx[0], px[1] - qpx[1], px[2] - qpx[0], px[3] - qpx[1])
        draw.rectangle(rel, outline=color, width=3)
        if label:
            draw.text((rel[0] + 4, max(0, rel[1] - 16)), label, fill=color, font=font)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    crop.save(out_path)
