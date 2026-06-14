"""Prompt templates and JSON schemas for the curation passes.

Both passes target *strict* JSON. Real providers must enforce the schema using
their native mechanism (Anthropic tool use, OpenAI Structured Outputs, etc.).
The schema ``$id`` is the discriminator MockProvider uses to route requests.
"""
from __future__ import annotations

VALIDATION_SCHEMA = {
    "$id": "validation_result.v1",
    "type": "object",
    "required": ["status", "confidence", "issues"],
    "properties": {
        "status": {"type": "string", "enum": ["ok", "needs_review", "broken"]},
        "confidence": {"type": "number", "minimum": 0.0, "maximum": 1.0},
        "issues": {
            "type": "array",
            "items": {
                "type": "object",
                "required": ["code", "severity", "message"],
                "properties": {
                    "code": {"type": "string"},
                    "field": {"type": "string"},
                    "severity": {"type": "string", "enum": ["low", "medium", "high"]},
                    "message": {"type": "string"},
                },
            },
        },
        "checked_assets": {"type": "array", "items": {"type": "string"}},
    },
}


ENRICHMENT_SCHEMA = {
    "$id": "enrichment.v1",
    "type": "object",
    "required": ["topic", "difficulty", "keywords"],
    "properties": {
        "topic": {"type": "string"},
        "subtopic": {"type": "string"},
        "syllabus_ref": {"type": "string"},
        "concepts": {"type": "array", "items": {"type": "string"}},
        "difficulty": {"type": "string", "enum": ["easy", "medium", "hard"]},
        "keywords": {"type": "array", "items": {"type": "string"}},
        "student_friendly_explanation_placeholder": {"type": "string"},
        "estimated_time_seconds": {"type": "integer", "minimum": 10},
    },
}


VALIDATION_SYSTEM = (
    "You are an exam paper QA tool. You are given the JSON we extracted for one "
    "Cambridge MCQ question, the debug image showing how we cropped it, and the "
    "actual cropped images. Verify whether the JSON is faithful to the page. "
    "Do not invent content. If something is unclear, mark it for human review. "
    "Reply ONLY with JSON matching the provided schema. Allowed issue codes: "
    "MISSING_QUESTION_TEXT, MISSING_OPTION, WRONG_OPTION_IMAGE, MISSING_DIAGRAM, "
    "EXTRA_DIAGRAM, BAD_TABLE_ROWS, LABEL_OUT_OF_DIAGRAM, OCR_NEEDED, "
    "LAYOUT_AMBIGUOUS."
)


def build_validation_user_prompt(question: dict) -> str:
    opts = {o["label"]: o for o in question.get("options", [])}
    lines = [
        f"QUESTION_NUMBER: {question.get('question_number')}",
        f"LAYOUT_TYPE: {question.get('layout_type')}",
        f"QUESTION_TEXT: {question.get('question_text') or '<empty>'}",
        "OPTIONS:",
    ]
    for letter in ("A", "B", "C", "D"):
        o = opts.get(letter, {})
        text = (o.get("text") or "").strip() or "<empty>"
        n_imgs = len(o.get("images") or [])
        lines.append(f"  {letter}: {text}  images={n_imgs}")
    lines.append(f"HAS_OPTION_TABLE: {bool(question.get('option_table'))}")
    lines.append(f"EXTRACTOR_WARNINGS: {question.get('warnings', [])}")
    return "\n".join(lines)


ENRICHMENT_SYSTEM = (
    "You are a Cambridge International AS/A-Level subject expert. Classify the "
    "given question against the syllabus. Return strict JSON. If the syllabus "
    "reference is uncertain, leave it blank rather than guessing."
)


def build_enrichment_user_prompt(paper: dict, question: dict) -> str:
    opts = question.get("options", [])
    options_preview = "; ".join(
        f"{o['label']}: {(o.get('text') or '')[:60]}" for o in opts
    )
    return "\n".join(
        [
            f"PAPER: {paper.get('paper_code')} ({paper.get('subject')}, {paper.get('session')})",
            f"QUESTION_NUMBER: {question.get('question_number')}",
            f"QUESTION_TEXT: {question.get('question_text') or '<empty>'}",
            f"OPTIONS_PREVIEW: {options_preview}",
            f"LAYOUT_TYPE: {question.get('layout_type')}",
        ]
    )
