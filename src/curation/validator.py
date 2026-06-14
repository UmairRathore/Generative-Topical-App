"""Validation pass: ask the provider to compare extracted JSON against images."""
from __future__ import annotations

from pathlib import Path
from typing import Any, Dict, List

from .prompts import VALIDATION_SCHEMA, VALIDATION_SYSTEM, build_validation_user_prompt
from .provider import LLMProvider, ProviderError
from .schemas import ValidationIssue, ValidationResult


def _collect_image_paths(question: Dict[str, Any], paper_dir: Path, output_root: Path) -> List[Path]:
    """Resolve asset paths to absolute paths the provider can attach.

    Image paths in ``questions.json`` are stored relative to ``output_root`` (the
    folder that contains ``papers/``). The debug image lives next to them.
    """
    paths: List[Path] = []
    qno = question.get("question_number")
    debug = paper_dir / "debug" / f"q{qno:03d}_debug.png"
    if debug.exists():
        paths.append(debug)

    for asset in question.get("assets", []) or []:
        rel = asset.get("image_path") or ""
        if not rel:
            continue
        candidate = (output_root / rel).resolve()
        if candidate.exists():
            paths.append(candidate)
    if question.get("option_table") and question["option_table"].get("image_path"):
        candidate = (output_root / question["option_table"]["image_path"]).resolve()
        if candidate.exists():
            paths.append(candidate)
    return paths


def validate_question(
    question: Dict[str, Any],
    paper_dir: Path,
    output_root: Path,
    provider: LLMProvider,
) -> ValidationResult:
    images = _collect_image_paths(question, paper_dir, output_root)
    user = build_validation_user_prompt(question)
    try:
        raw = provider.complete_json(VALIDATION_SYSTEM, user, images, VALIDATION_SCHEMA)
    except ProviderError as exc:
        return ValidationResult(
            status="needs_review",
            confidence=0.0,
            issues=[
                ValidationIssue(
                    code="PROVIDER_BAD_JSON",
                    field="provider",
                    severity="high",
                    message=str(exc),
                )
            ],
            checked_assets=[str(p.as_posix()) for p in images],
        )
    issues = [ValidationIssue(**i) for i in raw.get("issues", [])]
    return ValidationResult(
        status=raw.get("status", "needs_review"),
        confidence=float(raw.get("confidence", 0.0)),
        issues=issues,
        checked_assets=raw.get("checked_assets") or [str(p.as_posix()) for p in images],
    )
