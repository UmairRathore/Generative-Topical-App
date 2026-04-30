"""Rule-based caption generation. No external LLM — leaves payloads for later enrichment."""
from __future__ import annotations

import re
from typing import Optional

KEYWORDS = [
    (r"\b(force|forces|tension|weight|normal|friction)\b", "forces"),
    (r"\b(velocity|speed|acceleration|projectile|trajectory)\b", "motion"),
    (r"\b(graph|axis|axes|gradient|y[- ]?axis|x[- ]?axis)\b", "graph"),
    (r"\b(circuit|resistor|voltmeter|ammeter|battery|capacitor|emf)\b", "circuit"),
    (r"\b(spring|oscillation|period|amplitude|simple harmonic)\b", "oscillation"),
    (r"\b(wave|wavelength|frequency|interference|diffraction)\b", "wave"),
    (r"\b(collision|momentum|elastic|inelastic|ball|balls)\b", "collision"),
    (r"\b(table|row|column|tick|cross)\b", "table"),
    (r"\b(quark|hadron|baryon|meson|lepton|nucleus)\b", "particle"),
    (r"\b(pressure|fluid|liquid|gas|density|borehole)\b", "fluid"),
]


def _topic(nearby_text: str) -> str:
    txt = nearby_text.lower()
    for pattern, tag in KEYWORDS:
        if re.search(pattern, txt):
            return tag
    return "physics"


def _short_summary(nearby_text: str, max_words: int = 16) -> str:
    text = re.sub(r"\s+", " ", nearby_text).strip()
    words = text.split(" ")
    if not words:
        return ""
    return " ".join(words[:max_words])


def caption_for_question_diagram(
    nearby_text: str, between_or_after: str
) -> str:
    topic = _topic(nearby_text)
    summary = _short_summary(nearby_text, 18)
    placement = "embedded in" if between_or_after == "between" else "after"
    return f"Question diagram ({topic}) {placement} text: {summary}".strip(": ").strip()


def caption_for_option_image(label: str, nearby_text: str) -> str:
    topic = _topic(nearby_text)
    summary = _short_summary(nearby_text, 14)
    return f"Option {label} diagram ({topic}): {summary}".strip(": ").strip()


def caption_for_option_table(nearby_text: str, with_symbols: bool = False) -> str:
    summary = _short_summary(nearby_text, 14)
    if with_symbols:
        return f"Table of options with tick/cross symbols. {summary}".strip()
    return f"Table of options. {summary}".strip()


def caption_for_unknown(nearby_text: str) -> str:
    summary = _short_summary(nearby_text, 12)
    return f"Unclassified visual asset. {summary}".strip()
