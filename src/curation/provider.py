"""LLM provider abstraction.

The curation layer never imports any vendor SDK directly. All model calls go
through ``LLMProvider.complete_json``, which:

    - takes a system prompt, a user prompt, optional image paths, a JSON schema
    - returns a dict that conforms to the schema (or raises ``ProviderError``)

Phase 1 ships only ``MockProvider`` so the pipeline can be wired up offline.
Phase 2 will add ``AnthropicProvider`` and ``OpenAIProvider`` — they should
implement this same interface and forcibly constrain the model to the schema
(Anthropic tool use, OpenAI structured outputs, or local JSON-mode).
"""
from __future__ import annotations

import re
from abc import ABC, abstractmethod
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Callable, Dict, List


class ProviderError(RuntimeError):
    """Raised when a provider cannot return schema-valid JSON."""


@dataclass
class ProviderInfo:
    name: str
    model: str


class LLMProvider(ABC):
    info: ProviderInfo

    @abstractmethod
    def complete_json(
        self,
        system: str,
        user: str,
        image_paths: List[Path],
        json_schema: Dict[str, Any],
    ) -> Dict[str, Any]:
        """Return a dict conforming to ``json_schema``. Must not raise on
        normal model output — wrap parse / schema errors in ``ProviderError``.
        """


# ---------------------------------------------------------------------------
# Mock provider — deterministic, no network, no model.
# ---------------------------------------------------------------------------

# Topic guesses derived from question keywords. Same idea as src/captions.py
# but with broader Cambridge AS/A-Level coverage. Order matters: first match wins.
_TOPIC_RULES: List[tuple[str, str, str, str]] = [
    # (regex, topic, subtopic, syllabus_ref)
    (r"\bquark|hadron|baryon|meson|lepton\b", "particle physics", "quark model", "9702/12.2"),
    (r"\bcollision|momentum|elastic|inelastic\b", "momentum", "collisions", "9702/3.4"),
    (r"\bprojectile|trajectory|horizontal component\b", "kinematics", "projectiles", "9702/2.1"),
    (r"\bvelocity|speed|acceleration\b", "kinematics", "linear motion", "9702/2.1"),
    (r"\bforce|tension|weight|hinge|trapdoor\b", "forces", "force diagrams", "9702/3.1"),
    (r"\bspring|hooke|extension|elastic potential\b", "deformation", "Hooke's law", "9702/6.1"),
    (r"\boscillation|period|amplitude|simple harmonic\b", "oscillations", "SHM", "9702/17.1"),
    (r"\bwave|wavelength|frequency|interference|diffraction|double slit\b", "waves", "superposition", "9702/14.3"),
    (r"\bcircuit|resistor|emf|voltmeter|ammeter|capacitor\b", "electricity", "DC circuits", "9702/11.1"),
    (r"\bpressure|fluid|liquid|gas|density|borehole|upthrust\b", "fluids", "pressure / upthrust", "9702/4.2"),
    (r"\bbase unit|SI\b", "physical quantities", "SI base units", "9702/1.1"),
]


def _guess_topic(text: str) -> tuple[str, str, str]:
    t = text.lower()
    for pattern, topic, subtopic, ref in _TOPIC_RULES:
        if re.search(pattern, t):
            return topic, subtopic, ref
    return "physics", "", ""


def _guess_difficulty(text: str, layout_type: str, has_diagram: bool, has_table: bool) -> str:
    # Cheap proxy: longer prompts with multiple visual assets tend to be harder.
    length = len(text)
    if has_table or layout_type == "question_diagram_and_option_images":
        return "hard"
    if has_diagram or length > 280:
        return "medium"
    return "easy"


def _short_keywords(text: str, n: int = 6) -> list[str]:
    words = re.findall(r"[A-Za-z][A-Za-z\-]{3,}", text)
    seen: list[str] = []
    stop = {
            "what", "which", "shows", "object", "with", "that", "from", "this",
            "into", "have", "than", "when", "their", "then", "between",
        }
    for w in words:
        wl = w.lower()
        if wl in stop or wl in seen:
            continue
        seen.append(wl)
        if len(seen) >= n:
            break
    return seen


class MockProvider(LLMProvider):
    """Deterministic placeholder. Produces realistic-looking validation +
    enrichment output by inspecting the inputs only — no model is called.

    The validator will *also* surface the extractor's existing warnings as
    validation issues so the curated JSON shows the full picture even when no
    real model has run yet.
    """

    info = ProviderInfo(name="mock", model="mock-v1")

    def complete_json(
        self,
        system: str,
        user: str,
        image_paths: List[Path],
        json_schema: Dict[str, Any],
    ) -> Dict[str, Any]:
        kind = json_schema.get("$id", "")

        if kind == "validation_result.v1":
            return self._mock_validation(user, image_paths)
        if kind == "enrichment.v1":
            return self._mock_enrichment(user)
        raise ProviderError(f"MockProvider has no handler for schema id {kind!r}")

    # ---- mock implementations --------------------------------------------

    def _mock_validation(self, user: str, image_paths: List[Path]) -> Dict[str, Any]:
        # The user prompt embeds the warnings line and option counts, so we
        # parse a few simple cues out of it to produce believable issues.
        issues: list[dict] = []
        text_match = re.search(r"QUESTION_TEXT:\s*(.*)", user)
        qtext = (text_match.group(1).strip() if text_match else "")
        if not qtext or qtext == "<empty>":
            issues.append({
                "code": "MISSING_QUESTION_TEXT",
                "field": "question_text",
                "severity": "high",
                "message": "Question text is empty.",
            })
        for letter in ("A", "B", "C", "D"):
            m = re.search(rf"  {letter}: (.*)\s+images=(\d+)", user)
            if not m:
                issues.append({
                    "code": "MISSING_OPTION",
                    "field": f"options.{letter}",
                    "severity": "high",
                    "message": f"Option {letter} not present.",
                })
                continue
            text = m.group(1).strip()
            n_images = int(m.group(2))
            if not text and n_images == 0:
                issues.append({
                    "code": "MISSING_OPTION",
                    "field": f"options.{letter}",
                    "severity": "high",
                    "message": f"Option {letter} has neither text nor image.",
                })
        # Pass extractor warnings through if any
        warn_match = re.search(r"EXTRACTOR_WARNINGS: (.*)", user)
        if warn_match:
            warn_str = warn_match.group(1).strip()
            if warn_str and warn_str != "[]":
                issues.append({
                    "code": "LAYOUT_AMBIGUOUS",
                    "field": "extractor",
                    "severity": "low",
                    "message": f"Extractor warnings: {warn_str}",
                })

        status = "ok" if not issues else "needs_review"
        if any(i["severity"] == "high" for i in issues):
            status = "needs_review"
        return {
            "status": status,
            "confidence": 0.55,
            "issues": issues,
            "checked_assets": [str(p.as_posix()) for p in image_paths],
        }

    def _mock_enrichment(self, user: str) -> Dict[str, Any]:
        text_match = re.search(r"QUESTION_TEXT:\s*(.*)", user)
        layout_match = re.search(r"LAYOUT_TYPE:\s*(\S+)", user)
        qtext = text_match.group(1).strip() if text_match else ""
        layout = layout_match.group(1) if layout_match else "text_only"
        has_diagram = "diagram" in layout
        has_table = "table" in layout

        topic, subtopic, ref = _guess_topic(qtext)
        difficulty = _guess_difficulty(qtext, layout, has_diagram, has_table)
        keywords = _short_keywords(qtext)
        return {
            "topic": topic,
            "subtopic": subtopic,
            "syllabus_ref": ref,
            "concepts": [],
            "difficulty": difficulty,
            "keywords": keywords,
            "student_friendly_explanation_placeholder": (
                "TODO: AI-generated student-friendly walkthrough."
            ),
            "estimated_time_seconds": 60 if difficulty == "easy" else 90 if difficulty == "medium" else 120,
        }


# ---------------------------------------------------------------------------
# Registry — extension point for real providers
# ---------------------------------------------------------------------------

_REGISTRY: Dict[str, Callable[[], LLMProvider]] = {
    "mock": MockProvider,
}


def register_provider(name: str, factory: Callable[[], LLMProvider]) -> None:
    """Register a provider factory. Real providers (Anthropic / OpenAI / local)
    plug in here without the curation layer importing their SDKs."""
    _REGISTRY[name] = factory


def get_provider(name: str) -> LLMProvider:
    if name not in _REGISTRY:
        raise ProviderError(
            f"Unknown provider {name!r}. Registered: {sorted(_REGISTRY)}"
        )
    return _REGISTRY[name]()
