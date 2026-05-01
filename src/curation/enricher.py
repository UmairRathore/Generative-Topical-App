"""Enrichment pass: ask the provider for topic / subtopic / syllabus / etc."""
from __future__ import annotations

from typing import Any, Dict

from .prompts import ENRICHMENT_SCHEMA, ENRICHMENT_SYSTEM, build_enrichment_user_prompt
from .provider import LLMProvider, ProviderError
from .schemas import Enrichment


def enrich_question(
    paper: Dict[str, Any],
    question: Dict[str, Any],
    provider: LLMProvider,
) -> Enrichment:
    user = build_enrichment_user_prompt(paper, question)
    try:
        raw = provider.complete_json(ENRICHMENT_SYSTEM, user, [], ENRICHMENT_SCHEMA)
    except ProviderError:
        # Fallback: leave a blank enrichment so the admin can fill it manually.
        return Enrichment()
    return Enrichment(
        topic=raw.get("topic", ""),
        subtopic=raw.get("subtopic", ""),
        syllabus_ref=raw.get("syllabus_ref", ""),
        concepts=list(raw.get("concepts", []) or []),
        difficulty=raw.get("difficulty", "medium"),
        keywords=list(raw.get("keywords", []) or []),
        student_friendly_explanation_placeholder=raw.get(
            "student_friendly_explanation_placeholder", ""
        ),
        estimated_time_seconds=int(raw.get("estimated_time_seconds", 90)),
    )
