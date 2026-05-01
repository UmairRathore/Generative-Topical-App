from __future__ import annotations

from dataclasses import asdict, dataclass, field
from typing import Any, List, Optional


@dataclass
class DiagramLabel:
    text: str
    bbox: List[float] = field(default_factory=list)
    confidence: float = 0.9


@dataclass
class ImageAsset:
    id: str
    image_path: str
    page: int
    bbox: List[float] = field(default_factory=list)
    role: str = "unknown_visual_asset"
    caption: str = ""
    ocr_text: str = ""
    confidence: float = 0.0
    diagram_labels: List[DiagramLabel] = field(default_factory=list)


@dataclass
class CaptionPayload:
    image_path: str
    nearby_text: str
    question_number: int
    suggested_role: str
    option_label: Optional[str] = None


@dataclass
class Option:
    label: str
    text: str = ""
    images: List[ImageAsset] = field(default_factory=list)


@dataclass
class OptionTable:
    headers: List[str] = field(default_factory=list)
    rows: List[List[str]] = field(default_factory=list)
    image_path: str = ""
    bbox: List[float] = field(default_factory=list)
    diagram_labels: List[DiagramLabel] = field(default_factory=list)


@dataclass
class Question:
    question_number: int
    page_start: int
    page_end: int
    question_text: str = ""
    # Layout reconstruction: when at least one diagram sits between question
    # text, these scalar fields split ``question_text`` at that diagram's
    # bottom edge. Concatenated with a single space they reproduce
    # ``question_text`` verbatim. Both are None when no between-text diagram
    # exists (text-only / question_diagram_after_text / option_table cases).
    image_between_question_before_text: Optional[str] = None
    image_between_question_after_text: Optional[str] = None
    question_images_between_text: List[ImageAsset] = field(default_factory=list)
    question_images_after_text: List[ImageAsset] = field(default_factory=list)
    options: List[Option] = field(default_factory=list)
    option_table: Optional[OptionTable] = None
    assets: List[ImageAsset] = field(default_factory=list)
    correct_answer: Optional[str] = None
    layout_type: str = "text_only"
    needs_review: bool = False
    warnings: List[str] = field(default_factory=list)
    caption_llm_ready_payload: List[CaptionPayload] = field(default_factory=list)


@dataclass
class Paper:
    source_file: str
    paper_code: str = ""
    subject: str = ""
    session: str = ""
    total_questions: int = 0


@dataclass
class PaperOutput:
    paper: Paper
    questions: List[Question]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)
