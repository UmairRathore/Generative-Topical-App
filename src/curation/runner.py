"""Top-level curation runner.

Reads the deterministic ``questions.json`` written by ``src/extractor.py`` and
emits ``curated_questions.review.json`` next to it. Refuses to overwrite
admin-approved questions unless ``force`` is set.
"""
from __future__ import annotations

import json
from dataclasses import asdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional

from .enricher import enrich_question
from .provider import LLMProvider, get_provider
from .schemas import (
    CurationMetadata,
    CuratedPaper,
    CuratedQuestion,
    Enrichment,
    ValidationIssue,
    ValidationResult,
)
from .validator import validate_question


REVIEW_FILENAME = "curated_questions.review.json"


def _now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def _derive_review_status(validation: ValidationResult) -> str:
    if validation.status == "ok":
        return "ai_reviewed"
    return "flagged"


def _load_existing_review(path: Path) -> Optional[Dict[str, Any]]:
    if not path.exists():
        return None
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return None


def curate_paper(
    questions_json_path: Path,
    output_root: Path,
    provider: LLMProvider,
    force: bool = False,
) -> Path:
    """Curate one paper's ``questions.json`` and write ``.review.json`` beside it.

    ``output_root`` is the folder containing ``papers/`` (image paths in the
    JSON are relative to this).
    """
    paper_dir = questions_json_path.parent
    review_path = paper_dir / REVIEW_FILENAME

    raw = json.loads(questions_json_path.read_text(encoding="utf-8"))
    paper = raw.get("paper", {})
    questions: List[Dict[str, Any]] = raw.get("questions", [])

    existing = _load_existing_review(review_path) if not force else None
    approved_by_qno: Dict[int, Dict[str, Any]] = {}
    if existing:
        for cq in existing.get("questions", []):
            extracted = cq.get("extracted") or {}
            qno = extracted.get("question_number")
            if cq.get("review_status") in ("admin_approved", "rejected") and qno is not None:
                approved_by_qno[qno] = cq

    curated_qs: List[CuratedQuestion] = []
    for q in questions:
        qno = q.get("question_number")
        if qno in approved_by_qno:
            # Preserve admin decisions unchanged
            keep = approved_by_qno[qno]
            curated_qs.append(
                CuratedQuestion(
                    extracted=keep.get("extracted") or q,
                    validation=ValidationResult(
                        **{
                            **keep.get("validation", {}),
                            "issues": [
                                ValidationIssue(**i)
                                for i in keep.get("validation", {}).get("issues", [])
                            ],
                        }
                    ),
                    enrichment=Enrichment(**(keep.get("enrichment") or {})),
                    review_status=keep.get("review_status", "admin_approved"),
                    admin_notes=keep.get("admin_notes", ""),
                )
            )
            continue

        validation = validate_question(q, paper_dir, output_root, provider)
        enrichment = enrich_question(paper, q, provider)
        curated_qs.append(
            CuratedQuestion(
                extracted=q,
                validation=validation,
                enrichment=enrichment,
                review_status=_derive_review_status(validation),
                admin_notes="",
            )
        )

    curated = CuratedPaper(
        paper=paper,
        questions=curated_qs,
        curation_metadata=CurationMetadata(
            provider=provider.info.name,
            model=provider.info.model,
            curated_at=_now(),
            source_questions_json=str(questions_json_path.relative_to(output_root).as_posix()),
        ),
    )

    review_path.write_text(json.dumps(asdict(curated), indent=2), encoding="utf-8")
    return review_path


def curate_all(
    output_root: Path,
    provider_name: str = "mock",
    force: bool = False,
) -> List[Path]:
    """Walk every paper in ``output_root/papers`` and curate each one."""
    provider = get_provider(provider_name)
    papers_root = output_root / "papers"
    out: List[Path] = []
    if not papers_root.is_dir():
        return out
    for paper_dir in sorted(p for p in papers_root.iterdir() if p.is_dir()):
        qjson = paper_dir / "questions.json"
        if not qjson.exists():
            continue
        out.append(curate_paper(qjson, output_root, provider, force=force))
    return out
