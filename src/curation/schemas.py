"""Curation-layer schemas. These wrap (never mutate) extractor output.

The extractor's ``Question`` JSON is preserved verbatim under ``extracted``;
``validation`` and ``enrichment`` are added by the post-extraction layer.
``review_status`` tracks the workflow:

    extracted     -> only the deterministic extractor ran
    ai_reviewed   -> AI validation + enrichment ran, no issues
    flagged       -> AI validation found issues, admin must look
    admin_approved-> admin signed off, eligible for website import
    rejected      -> admin discarded
"""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Dict, List, Literal


# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------

ValidationStatus = Literal["ok", "needs_review", "broken"]
IssueSeverity = Literal["low", "medium", "high"]

# Closed set of issue codes the validator may emit. Keeping this closed makes
# the website / admin UI easy to filter.
ISSUE_CODES = (
    "MISSING_QUESTION_TEXT",
    "MISSING_OPTION",
    "WRONG_OPTION_IMAGE",
    "MISSING_DIAGRAM",
    "EXTRA_DIAGRAM",
    "BAD_TABLE_ROWS",
    "LABEL_OUT_OF_DIAGRAM",
    "OCR_NEEDED",
    "LAYOUT_AMBIGUOUS",
    "PROVIDER_BAD_JSON",
)


@dataclass
class ValidationIssue:
    code: str
    field: str = ""
    severity: IssueSeverity = "medium"
    message: str = ""


@dataclass
class ValidationResult:
    status: ValidationStatus = "ok"
    confidence: float = 1.0
    issues: List[ValidationIssue] = field(default_factory=list)
    checked_assets: List[str] = field(default_factory=list)


# ---------------------------------------------------------------------------
# Enrichment
# ---------------------------------------------------------------------------

Difficulty = Literal["easy", "medium", "hard"]


@dataclass
class Enrichment:
    topic: str = ""
    subtopic: str = ""
    syllabus_ref: str = ""
    concepts: List[str] = field(default_factory=list)
    difficulty: Difficulty = "medium"
    keywords: List[str] = field(default_factory=list)
    student_friendly_explanation_placeholder: str = ""
    estimated_time_seconds: int = 90


# ---------------------------------------------------------------------------
# Curated wrappers
# ---------------------------------------------------------------------------

ReviewStatus = Literal[
    "extracted",
    "ai_reviewed",
    "flagged",
    "admin_approved",
    "rejected",
]


@dataclass
class CuratedQuestion:
    extracted: Dict[str, Any] = field(default_factory=dict)
    validation: ValidationResult = field(default_factory=ValidationResult)
    enrichment: Enrichment = field(default_factory=Enrichment)
    review_status: ReviewStatus = "extracted"
    admin_notes: str = ""


@dataclass
class CurationMetadata:
    provider: str = "mock"
    model: str = "mock-v1"
    curated_at: str = ""
    source_questions_json: str = ""


@dataclass
class CuratedPaper:
    paper: Dict[str, Any] = field(default_factory=dict)
    questions: List[CuratedQuestion] = field(default_factory=list)
    curation_metadata: CurationMetadata = field(default_factory=CurationMetadata)
