"""Batch QA / reporting over an extractor output folder.

Reads ``output/manifest.json`` and every ``output/papers/<stem>/questions.json``
and produces four artefacts under ``output/qa/``:

    qa_summary.json       — structured per-paper summary (machine-readable)
    qa_summary.md         — same, grouped failed/review/clean (human-readable)
    problem_cases.json    — flat list of every problem question with reasons
    problem_cases.md      — same, with clickable file references

Status rules:
    pass    — total questions == expected, no warnings, no needs_review
    review  — total == expected but at least one warning or needs_review
    failed  — extractor crashed (error in manifest), questions.json missing /
              unreadable, or total != expected

No LLM / network calls. Pure filesystem + JSON. Designed to scale to ~100 PDFs.

Usage::

    python qa_report.py --output output --expected 40
"""
from __future__ import annotations

import argparse
import json
import sys
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

OPTION_LABELS = ("A", "B", "C", "D")


# ---------------------------------------------------------------------------
# Data model
# ---------------------------------------------------------------------------

@dataclass
class ProblemQuestion:
    paper: str
    question_number: int
    layout_type: str
    needs_review: bool
    reasons: List[str] = field(default_factory=list)
    warnings: List[str] = field(default_factory=list)
    questions_json: str = ""
    debug_image: str = ""
    asset_paths: List[str] = field(default_factory=list)
    # Additional context useful for triaging website-rendering problems
    question_text_snippet: str = ""
    option_texts: List[str] = field(default_factory=list)
    question_image_paths: List[str] = field(default_factory=list)
    option_image_paths: List[str] = field(default_factory=list)
    severity: str = "PASS"  # PASS | REVIEW | RENDER_FIX | ACCEPTABLE_FALLBACK | BLOCKER


@dataclass
class PaperReport:
    source_file: str
    paper_dir: str
    total_questions: int
    expected_questions: int
    status: str  # pass | review | failed
    error: Optional[str] = None
    warning_count: int = 0
    needs_review_count: int = 0
    missing_option_questions: List[int] = field(default_factory=list)
    empty_option_questions: List[int] = field(default_factory=list)
    short_image_option_questions: List[int] = field(default_factory=list)
    giant_crop_questions: List[int] = field(default_factory=list)
    option_table_fallback_questions: List[int] = field(default_factory=list)
    mixed_layout_questions: List[int] = field(default_factory=list)
    crop_skipped_questions: List[int] = field(default_factory=list)
    # Semantic / website-rendering signals
    misclassified_option_images_questions: List[int] = field(default_factory=list)
    repeated_axis_label_questions: List[int] = field(default_factory=list)
    option_text_in_question_text_questions: List[int] = field(default_factory=list)
    option_image_crop_review_questions: List[int] = field(default_factory=list)
    # Severity rollup (per-question counts inside this paper)
    blocker_questions: List[int] = field(default_factory=list)
    render_fix_questions: List[int] = field(default_factory=list)
    review_questions: List[int] = field(default_factory=list)
    acceptable_fallback_questions: List[int] = field(default_factory=list)
    severity_tier: str = "pass"  # pass | fallback_ok | render_fix_only | review | blocked | failed
    problem_count: int = 0
    paper_code: str = ""
    subject: str = ""
    session: str = ""


@dataclass
class QASummary:
    output_root: str
    expected_questions: int
    counts: Dict[str, int]                       # pass / review / failed
    papers: List[PaperReport]
    total_problems: int
    # Severity-tiered batch metrics (added for production-QA workflow)
    total_papers: int = 0
    total_questions: int = 0
    failed_papers: int = 0
    blocked_papers: int = 0
    review_papers: int = 0
    render_fix_only_papers: int = 0
    fallback_ok_papers: int = 0
    pass_papers: int = 0
    blocker_question_count: int = 0
    render_fix_question_count: int = 0
    review_question_count: int = 0
    acceptable_fallback_question_count: int = 0
    pass_question_count: int = 0
    estimated_demo_safe_question_count: int = 0
    estimated_success_rate_percent: float = 0.0
    blocker_rate_percent: float = 0.0
    target_success_rate_percent: float = 98.0


# ---------------------------------------------------------------------------
# Severity classification
# ---------------------------------------------------------------------------

# Closed set of severity buckets for individual questions.
SEV_PASS = "PASS"
SEV_REVIEW = "REVIEW"
SEV_RENDER_FIX = "RENDER_FIX"
SEV_FALLBACK = "ACCEPTABLE_FALLBACK"
SEV_BLOCKER = "BLOCKER"


def _question_severity(q: Dict[str, Any], flags: Dict[str, bool]) -> str:
    """Map per-question flags to a severity tier.

    Rules (highest precedence first):
      BLOCKER — cannot be safely published without an extractor / manual fix:
        * missing A/B/C/D
        * empty option(s) AND no usable option_table image fallback
        * image-option layout with fewer than 4 image crops
        * giant-crop warning (one option image spanning multiple options)
        * probable_option_images_misclassified_as_question_diagram
        * mixed layout with empty options
      ACCEPTABLE_FALLBACK — usable with cropped table image fallback
        * option_table_fallback (tick/cross / unreadable cells) WHEN the
          table image asset exists on disk.
      RENDER_FIX — likely usable after import-side cleanup
        * question_text contains option text (we strip the duplication on
          import; clickable A/B/C/D is unaffected).
      REVIEW — needs spot-check but probably usable
        * option_image_crop_needs_visual_review
        * repeated_axis_label_option_text not already classified above
        * crop_skipped warnings
        * mixed layout that has at least some option text/images
      PASS otherwise.
    """
    has_table_image = bool(
        (q.get("option_table") or {}).get("image_path")
    )
    options = q.get("options") or []
    n_with_text = sum(1 for o in options if (o.get("text") or "").strip())
    n_with_imgs = sum(1 for o in options if o.get("images"))

    # ---- BLOCKER ----------------------------------------------------------
    if flags["missing_option"]:
        return SEV_BLOCKER
    if flags["empty_option"] and not has_table_image:
        return SEV_BLOCKER
    if flags["short_image_options"]:
        return SEV_BLOCKER
    if flags["giant_crop"]:
        return SEV_BLOCKER
    if flags["misclassified_option_images"]:
        return SEV_BLOCKER
    if flags["mixed_layout"] and (n_with_text + n_with_imgs) == 0:
        return SEV_BLOCKER

    # ---- ACCEPTABLE_FALLBACK ---------------------------------------------
    if flags["option_table_fallback"] and has_table_image:
        return SEV_FALLBACK

    # ---- RENDER_FIX -------------------------------------------------------
    if flags["option_text_in_question_text"]:
        return SEV_RENDER_FIX

    # ---- REVIEW -----------------------------------------------------------
    if flags["option_image_crop_review"]:
        return SEV_REVIEW
    if flags["repeated_axis_label_options"]:
        return SEV_REVIEW
    if flags["crop_skipped"]:
        return SEV_REVIEW
    if flags["mixed_layout"]:
        return SEV_REVIEW

    return SEV_PASS


# ---------------------------------------------------------------------------
# Checks
# ---------------------------------------------------------------------------

def _has(s: str, *needles: str) -> bool:
    return any(n in s for n in needles)


# --- Semantic / website-rendering detectors --------------------------------

# Distinctive axis-label words in physics MCQs (lowercase). Single letters are
# intentionally excluded — they're too common in real option text.
_AXIS_LABEL_TOKENS = frozenset({
    "time", "velocity", "current", "voltage", "weight", "force",
    "pressure", "speed", "displacement", "acceleration", "energy",
    "temperature", "frequency", "wavelength", "intensity",
    "amplitude", "momentum", "potential", "extension", "charge",
})

# Conservative page-level extents used when checking option-image padding.
# (Match SIDE_MARGIN/HEADER_MARGIN/FOOTER_MARGIN from src/extractor.py.)
_PAGE_LEFT = 36.0
_PAGE_RIGHT = 559.0
_PAGE_TOP = 50.0
_PAGE_BOTTOM = 782.0
_EDGE_TOL = 4.0


def _is_axis_label_text(text: str) -> bool:
    """Reads like a graph axis-label phrase: short, contains a distinctive
    physics-quantity word (time/velocity/current/...). Single-letter or
    plain-numeric option texts (e.g. "1.4×10⁴") never match."""
    t = (text or "").strip().lower()
    if not t:
        return False
    # Strip common decorations
    cleaned = t.replace("/", " ").replace(",", " ").replace(";", " ")
    tokens = [tok for tok in cleaned.split() if tok]
    if not tokens or len(tokens) > 6:
        return False
    return any(tok in _AXIS_LABEL_TOKENS for tok in tokens)


def _options_share_text(options: List[Dict[str, Any]], min_count: int = 3) -> bool:
    """At least ``min_count`` options carry the *same* non-empty text."""
    from collections import Counter
    texts = [(o.get("text") or "").strip() for o in options]
    texts = [t for t in texts if t]
    if not texts:
        return False
    counts = Counter(texts)
    return counts.most_common(1)[0][1] >= min_count


def _is_misclassified_option_block(q: Dict[str, Any]) -> bool:
    """Q6-of-9702_m19_qp_12 pattern: question_diagram layout with a tall
    after-text image and option texts that look like graph axis labels.

    The "after-text image" is in fact a 2x2 / horizontal block of option
    diagrams that the extractor failed to split, so the website would render
    the block as a single inline image with no clickable options.
    """
    if (q.get("layout_type") or "") != "question_diagram":
        return False
    after = q.get("question_images_after_text") or []
    if not after:
        return False
    has_large = False
    for a in after:
        # Assets attached by the manual-QA reconciliation pass were visually
        # verified as genuine question diagrams — never an unsplit option block.
        if a.get("source") == "manual_qa_reconciliation":
            continue
        bb = a.get("bbox") or []
        if len(bb) < 4:
            continue
        h = bb[3] - bb[1]
        w = bb[2] - bb[0]
        if h > 200 or (h * w) > 30000:
            has_large = True
            break
    if not has_large:
        return False
    options = q.get("options") or []
    if _options_share_text(options, min_count=3):
        return True
    n_axis = sum(1 for o in options if _is_axis_label_text(o.get("text") or ""))
    return n_axis >= 2


def _has_repeated_axis_labels(options: List[Dict[str, Any]]) -> bool:
    """Three or more option texts read like graph axis labels. The threshold is
    deliberately strict — two coincidental matches are common."""
    n = sum(1 for o in options if _is_axis_label_text(o.get("text") or ""))
    return n >= 3


def _options_inside_question_text(q: Dict[str, Any]) -> List[str]:
    """Return labels whose option text appears verbatim **after the last `?`**
    in question_text.

    Cambridge MCQs always end the question stem with "?", so option texts that
    appear *after* it are inline-option leaks the extractor folded into
    question_text. Matches inside the stem itself (e.g. "10 kN as shown ...
    What is the value of F?" with option "10 kN") are coincidental and skipped.
    """
    qt = (q.get("question_text") or "").strip()
    if not qt:
        return []
    if "?" in qt:
        tail = qt[qt.rfind("?") + 1 :].strip()
    else:
        tail = qt
    if not tail:
        return []
    matches: List[str] = []
    for o in (q.get("options") or []):
        text = (o.get("text") or "").strip()
        if len(text) < 4:
            continue
        if text in tail:
            matches.append((o.get("label") or "").strip())
    return matches


def _option_image_needs_review(asset: Dict[str, Any]) -> bool:
    """Crop-edge risk:

      * unusually small (width < 80 or height < 60), or
      * touches a page boundary AND is **also** flat (height < 90), suggesting
        an incomplete strip rather than a healthy 2x2 grid cell.

    Standard 2x2 image-option grids touch the left/right page edges by design,
    so plain "touches edge" is *not* a flag — Q4 of 9702_m19_qp_12 is fine
    structurally; only suspiciously small/flat crops are surfaced here.
    """
    bb = asset.get("bbox") or []
    if len(bb) < 4:
        return False
    x0, y0, x1, y1 = bb[:4]
    w = x1 - x0
    h = y1 - y0
    too_small = w < 80 or h < 60
    touches_edge = (
        x0 <= _PAGE_LEFT + _EDGE_TOL
        or x1 >= _PAGE_RIGHT - _EDGE_TOL
        or y0 <= _PAGE_TOP + _EDGE_TOL
        or y1 >= _PAGE_BOTTOM - _EDGE_TOL
    )
    flat_at_edge = touches_edge and h < 90
    return too_small or flat_at_edge


def _option_images_needing_review(q: Dict[str, Any]) -> List[str]:
    flagged: List[str] = []
    for o in (q.get("options") or []):
        for img in (o.get("images") or []):
            if _option_image_needs_review(img):
                lbl = (o.get("label") or "").strip()
                if lbl and lbl not in flagged:
                    flagged.append(lbl)
                break
    return flagged


def _question_problems(q: Dict[str, Any]) -> Tuple[List[str], Dict[str, bool]]:
    """Inspect one question for problems. Returns (reasons, flags)."""
    reasons: List[str] = []
    flags = {
        "missing_option": False,
        "empty_option": False,
        "short_image_options": False,
        "giant_crop": False,
        "option_table_fallback": False,
        "mixed_layout": False,
        "crop_skipped": False,
        # Semantic / website-rendering signals
        "misclassified_option_images": False,
        "repeated_axis_label_options": False,
        "option_text_in_question_text": False,
        "option_image_crop_review": False,
    }

    layout = (q.get("layout_type") or "").strip()
    options = q.get("options") or []
    present = {(o.get("label") or "").strip() for o in options}
    missing = [lbl for lbl in OPTION_LABELS if lbl not in present]
    if missing:
        flags["missing_option"] = True
        reasons.append(f"missing option(s): {','.join(missing)}")

    empty = [
        (o.get("label") or "?")
        for o in options
        if not (o.get("text") or "").strip() and not (o.get("images") or [])
    ]
    if empty:
        flags["empty_option"] = True
        reasons.append(f"option(s) with no text and no image: {','.join(empty)}")

    if layout in ("option_images", "question_diagram_and_option_images"):
        n_with_images = sum(1 for o in options if o.get("images"))
        if 0 < n_with_images < 4:
            flags["short_image_options"] = True
            reasons.append(
                f"image-option layout has {n_with_images}/4 image options"
            )

    if layout == "mixed":
        flags["mixed_layout"] = True
        reasons.append("layout_type=mixed")

    warnings = q.get("warnings") or []
    for w in warnings:
        if "appears to span multiple options" in w:
            flags["giant_crop"] = True
            reasons.append(f"giant crop warning: {w}")
        if "tick/cross symbols" in w or "fallback" in w.lower():
            flags["option_table_fallback"] = True
            reasons.append(f"fallback warning: {w}")
        if "degenerate" in w or "skipped" in w.lower():
            flags["crop_skipped"] = True
            reasons.append(f"crop skipped: {w}")

    # ---- Semantic detectors ------------------------------------------------
    if _is_misclassified_option_block(q):
        flags["misclassified_option_images"] = True
        reasons.append(
            "probable_option_images_misclassified_as_question_diagram: "
            "large after-text image + repeated/axis-label option texts."
        )

    if _has_repeated_axis_labels(options):
        flags["repeated_axis_label_options"] = True
        reasons.append(
            "repeated_axis_label_option_text: option texts look like graph axis labels."
        )

    leaks = _options_inside_question_text(q)
    if leaks:
        flags["option_text_in_question_text"] = True
        severity = "high" if len(leaks) >= 3 else "medium"
        reasons.append(
            f"question_text_contains_option_text [{severity}]: "
            f"option(s) {','.join(leaks)} appear inside question_text."
        )

    crop_labels = _option_images_needing_review(q)
    if crop_labels:
        flags["option_image_crop_review"] = True
        reasons.append(
            "option_image_crop_needs_visual_review: option image(s) "
            f"{','.join(crop_labels)} have bbox flush against page edge or "
            "are unusually small — eyeball the crop."
        )

    return reasons, flags


def _resolve_asset_paths(
    output_root: Path, paper_dir: Path, q: Dict[str, Any], max_assets: int = 6
) -> List[str]:
    """Collect a few useful image paths (relative to output_root) for a question."""
    paths: List[str] = []
    qno = q.get("question_number")
    if isinstance(qno, int):
        debug = paper_dir / "debug" / f"q{qno:03d}_debug.png"
        if debug.exists():
            paths.append(str(debug.relative_to(output_root).as_posix()))
    for asset in (q.get("assets") or [])[:max_assets]:
        rel = (asset or {}).get("image_path") or ""
        if not rel:
            continue
        full = (output_root / rel).resolve()
        if full.exists():
            paths.append(rel)
    if q.get("option_table") and q["option_table"].get("image_path"):
        rel = q["option_table"]["image_path"]
        full = (output_root / rel).resolve()
        if full.exists() and rel not in paths:
            paths.append(rel)
    return paths


def _analyse_paper(
    output_root: Path,
    paper_dir: Path,
    expected: int,
    manifest_entry: Optional[Dict[str, Any]],
) -> Tuple[PaperReport, List[ProblemQuestion]]:
    name = paper_dir.name
    questions_json = paper_dir / "questions.json"
    rel_questions = (
        str(questions_json.relative_to(output_root).as_posix())
        if questions_json.exists()
        else ""
    )

    # Manifest-recorded crash takes precedence — questions.json may be missing.
    manifest_error = (manifest_entry or {}).get("error") if manifest_entry else None
    if manifest_error:
        return (
            PaperReport(
                source_file=(manifest_entry or {}).get("source_file", name + ".pdf"),
                paper_dir=str(paper_dir.relative_to(output_root).as_posix())
                if paper_dir.exists() else "",
                total_questions=0,
                expected_questions=expected,
                status="failed",
                error=str(manifest_error),
            ),
            [],
        )

    if not questions_json.exists():
        return (
            PaperReport(
                source_file=name + ".pdf",
                paper_dir=str(paper_dir.relative_to(output_root).as_posix()),
                total_questions=0,
                expected_questions=expected,
                status="failed",
                error="questions.json not found",
            ),
            [],
        )

    try:
        data = json.loads(questions_json.read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        return (
            PaperReport(
                source_file=name + ".pdf",
                paper_dir=str(paper_dir.relative_to(output_root).as_posix()),
                total_questions=0,
                expected_questions=expected,
                status="failed",
                error=f"questions.json unreadable: {exc!r}",
            ),
            [],
        )

    paper_meta = data.get("paper") or {}
    questions = data.get("questions") or []
    total = paper_meta.get("total_questions") or len(questions)
    source_file = paper_meta.get("source_file") or (name + ".pdf")

    report = PaperReport(
        source_file=source_file,
        paper_dir=str(paper_dir.relative_to(output_root).as_posix()),
        total_questions=int(total),
        expected_questions=expected,
        status="pass",
        paper_code=paper_meta.get("paper_code", ""),
        subject=paper_meta.get("subject", ""),
        session=paper_meta.get("session", ""),
    )

    problems: List[ProblemQuestion] = []
    warning_count = 0
    needs_review_count = 0
    for q in questions:
        qwarnings = q.get("warnings") or []
        warning_count += len(qwarnings)
        if q.get("needs_review"):
            needs_review_count += 1
        reasons, flags = _question_problems(q)
        if not (reasons or qwarnings or q.get("needs_review")):
            continue
        # Only emit a ProblemQuestion entry when there is something actionable
        if not reasons and not q.get("needs_review"):
            continue

        qno = int(q.get("question_number") or 0)
        if flags["missing_option"]:
            report.missing_option_questions.append(qno)
        if flags["empty_option"]:
            report.empty_option_questions.append(qno)
        if flags["short_image_options"]:
            report.short_image_option_questions.append(qno)
        if flags["giant_crop"]:
            report.giant_crop_questions.append(qno)
        if flags["option_table_fallback"]:
            report.option_table_fallback_questions.append(qno)
        if flags["mixed_layout"]:
            report.mixed_layout_questions.append(qno)
        if flags["crop_skipped"]:
            report.crop_skipped_questions.append(qno)
        if flags["misclassified_option_images"]:
            report.misclassified_option_images_questions.append(qno)
        if flags["repeated_axis_label_options"]:
            report.repeated_axis_label_questions.append(qno)
        if flags["option_text_in_question_text"]:
            report.option_text_in_question_text_questions.append(qno)
        if flags["option_image_crop_review"]:
            report.option_image_crop_review_questions.append(qno)

        sev = _question_severity(q, flags)
        if sev == SEV_BLOCKER:
            report.blocker_questions.append(qno)
        elif sev == SEV_FALLBACK:
            report.acceptable_fallback_questions.append(qno)
        elif sev == SEV_RENDER_FIX:
            report.render_fix_questions.append(qno)
        elif sev == SEV_REVIEW:
            report.review_questions.append(qno)

        debug = paper_dir / "debug" / f"q{qno:03d}_debug.png"
        # Per-question semantic context for the markdown report
        qtext = (q.get("question_text") or "").strip()
        snippet = qtext if len(qtext) <= 240 else qtext[:240].rstrip() + "..."
        opt_texts = [(o.get("text") or "") for o in (q.get("options") or [])]
        q_imgs: List[str] = []
        for a in (q.get("question_images_between_text") or []) + (
            q.get("question_images_after_text") or []
        ):
            ip = (a or {}).get("image_path") or ""
            if ip:
                q_imgs.append(ip)
        opt_imgs: List[str] = []
        for o in (q.get("options") or []):
            for img in (o.get("images") or []):
                ip = (img or {}).get("image_path") or ""
                if ip:
                    opt_imgs.append(ip)

        problems.append(
            ProblemQuestion(
                paper=name,
                question_number=qno,
                layout_type=q.get("layout_type", ""),
                needs_review=bool(q.get("needs_review")),
                reasons=reasons,
                warnings=list(qwarnings),
                questions_json=rel_questions,
                debug_image=str(debug.relative_to(output_root).as_posix())
                if debug.exists() else "",
                asset_paths=_resolve_asset_paths(output_root, paper_dir, q),
                question_text_snippet=snippet,
                option_texts=opt_texts,
                question_image_paths=q_imgs,
                option_image_paths=opt_imgs,
                severity=sev,
            )
        )

    report.warning_count = warning_count
    report.needs_review_count = needs_review_count
    report.problem_count = len(problems)

    if int(total) != expected:
        report.status = "failed"
        if not report.error:
            report.error = f"total_questions={total}, expected={expected}"
    elif warning_count or needs_review_count or problems:
        report.status = "review"
    else:
        report.status = "pass"

    # Severity tier rollup for this paper
    if report.status == "failed":
        report.severity_tier = "failed"
    elif report.blocker_questions:
        report.severity_tier = "blocked"
    elif report.review_questions:
        report.severity_tier = "review"
    elif report.render_fix_questions:
        report.severity_tier = "render_fix_only"
    elif report.acceptable_fallback_questions:
        report.severity_tier = "fallback_ok"
    else:
        report.severity_tier = "pass"

    return report, problems


# ---------------------------------------------------------------------------
# Markdown rendering
# ---------------------------------------------------------------------------

_STATUS_BADGE = {
    "pass": "✅ pass",
    "review": "⚠️ review",
    "failed": "❌ failed",
}


def _md_paper_block(p: PaperReport) -> str:
    badge = _STATUS_BADGE.get(p.status, p.status)
    lines = [
        f"### {badge} `{p.source_file}`",
        "",
        f"- paper: `{p.paper_code}` {p.subject} {p.session}".rstrip(),
        f"- total / expected: **{p.total_questions} / {p.expected_questions}**",
        f"- warnings: {p.warning_count}, needs_review: {p.needs_review_count}, problem questions: {p.problem_count}",
    ]
    if p.error:
        lines.append(f"- error: `{p.error}`")
    if p.paper_dir:
        lines.append(f"- output: [{p.paper_dir}]({p.paper_dir})")
    if p.missing_option_questions:
        lines.append(f"- missing option(s): Q{', Q'.join(map(str, p.missing_option_questions))}")
    if p.empty_option_questions:
        lines.append(f"- empty options: Q{', Q'.join(map(str, p.empty_option_questions))}")
    if p.short_image_option_questions:
        lines.append(f"- image-option layouts with <4 images: Q{', Q'.join(map(str, p.short_image_option_questions))}")
    if p.giant_crop_questions:
        lines.append(f"- giant option crop: Q{', Q'.join(map(str, p.giant_crop_questions))}")
    if p.option_table_fallback_questions:
        lines.append(f"- option-table fallback: Q{', Q'.join(map(str, p.option_table_fallback_questions))}")
    if p.mixed_layout_questions:
        lines.append(f"- mixed layout: Q{', Q'.join(map(str, p.mixed_layout_questions))}")
    if p.crop_skipped_questions:
        lines.append(f"- crop-skipped: Q{', Q'.join(map(str, p.crop_skipped_questions))}")
    if p.misclassified_option_images_questions:
        lines.append(f"- probable_option_images_misclassified_as_question_diagram: Q{', Q'.join(map(str, p.misclassified_option_images_questions))}")
    if p.repeated_axis_label_questions:
        lines.append(f"- repeated_axis_label_option_text: Q{', Q'.join(map(str, p.repeated_axis_label_questions))}")
    if p.option_text_in_question_text_questions:
        lines.append(f"- question_text_contains_option_text: Q{', Q'.join(map(str, p.option_text_in_question_text_questions))}")
    if p.option_image_crop_review_questions:
        lines.append(f"- option_image_crop_needs_visual_review: Q{', Q'.join(map(str, p.option_image_crop_review_questions))}")
    lines.append("")
    return "\n".join(lines)


def _render_summary_md(summary: QASummary) -> str:
    counts = summary.counts
    target = summary.target_success_rate_percent
    succ = summary.estimated_success_rate_percent
    target_emoji = "✅" if succ >= target else "❌"
    lines = [
        "# QA summary",
        "",
        f"- output root: `{summary.output_root}`",
        f"- expected questions per paper: **{summary.expected_questions}**",
        f"- papers: **{len(summary.papers)}** "
        f"(failed **{counts.get('failed', 0)}**, "
        f"review **{counts.get('review', 0)}**, "
        f"pass **{counts.get('pass', 0)}**)",
        f"- total problem questions: **{summary.total_problems}**",
        "",
        "## Production-QA metrics",
        "",
        f"- total papers / questions: **{summary.total_papers}** / **{summary.total_questions}**",
        f"- severity-tier paper counts: "
        f"failed **{summary.failed_papers}**, "
        f"blocked **{summary.blocked_papers}**, "
        f"review **{summary.review_papers}**, "
        f"render-fix only **{summary.render_fix_only_papers}**, "
        f"fallback-ok **{summary.fallback_ok_papers}**, "
        f"pass **{summary.pass_papers}**",
        f"- severity-tier question counts: "
        f"BLOCKER **{summary.blocker_question_count}**, "
        f"RENDER_FIX **{summary.render_fix_question_count}**, "
        f"REVIEW **{summary.review_question_count}**, "
        f"ACCEPTABLE_FALLBACK **{summary.acceptable_fallback_question_count}**, "
        f"PASS **{summary.pass_question_count}**",
        f"- demo-safe questions: **{summary.estimated_demo_safe_question_count}** "
        f"(= total − BLOCKER − FAILED-paper questions)",
        f"- estimated success rate: **{summary.estimated_success_rate_percent:.2f}%** "
        f"(target ≥ {target:.0f}%) {target_emoji}",
        f"- blocker rate: **{summary.blocker_rate_percent:.2f}%**",
        "",
    ]
    for label, key in (("Failed", "failed"), ("Review", "review"), ("Clean", "pass")):
        bucket = [p for p in summary.papers if p.status == key]
        lines.append(f"## {label} ({len(bucket)})")
        lines.append("")
        if not bucket:
            lines.append("_none_")
            lines.append("")
            continue
        for p in bucket:
            lines.append(_md_paper_block(p))
    return "\n".join(lines).rstrip() + "\n"


def _render_problems_md(problems: List[ProblemQuestion]) -> str:
    lines = [
        "# Problem cases",
        "",
        f"Total problem questions: **{len(problems)}**",
        "",
    ]
    if not problems:
        lines.append("_none — all extracted questions are clean._")
        lines.append("")
        return "\n".join(lines)

    by_paper: Dict[str, List[ProblemQuestion]] = {}
    for pq in problems:
        by_paper.setdefault(pq.paper, []).append(pq)

    for paper in sorted(by_paper):
        items = sorted(by_paper[paper], key=lambda x: x.question_number)
        lines.append(f"## `{paper}` ({len(items)} problem question(s))")
        lines.append("")
        if items[0].questions_json:
            lines.append(f"- questions.json: [{items[0].questions_json}]({items[0].questions_json})")
        lines.append("")
        for pq in items:
            lines.append(f"### Q{pq.question_number} — `{pq.layout_type}`"
                         + (" (needs_review)" if pq.needs_review else ""))
            for r in pq.reasons:
                lines.append(f"- {r}")
            for w in pq.warnings:
                if not any(w in r for r in pq.reasons):
                    lines.append(f"- warning: {w}")
            if pq.question_text_snippet:
                lines.append(f"- question_text: {pq.question_text_snippet!r}")
            if any((t or "").strip() for t in pq.option_texts):
                preview = "; ".join(
                    f"{lbl}={t!r}" for lbl, t in zip("ABCD", pq.option_texts) if t
                )
                lines.append(f"- option_texts: {preview}")
            if pq.debug_image:
                lines.append(f"- debug: [{pq.debug_image}]({pq.debug_image})")
            for ip in pq.question_image_paths:
                lines.append(f"- question image: [{ip}]({ip})")
            for ip in pq.option_image_paths:
                lines.append(f"- option image: [{ip}]({ip})")
            for a in pq.asset_paths:
                if a == pq.debug_image:
                    continue
                if a in pq.question_image_paths or a in pq.option_image_paths:
                    continue
                lines.append(f"- asset: [{a}]({a})")
            lines.append("")
    return "\n".join(lines).rstrip() + "\n"


# ---------------------------------------------------------------------------
# Top-level driver
# ---------------------------------------------------------------------------

def run_qa(output_root: Path, expected: int) -> QASummary:
    manifest_path = output_root / "manifest.json"
    manifest_papers: Dict[str, Dict[str, Any]] = {}
    if manifest_path.exists():
        try:
            mdata = json.loads(manifest_path.read_text(encoding="utf-8"))
            for entry in mdata.get("papers", []) or []:
                src = (entry.get("source_file") or "").strip()
                stem = Path(src).stem if src else ""
                if stem:
                    manifest_papers[stem] = entry
        except Exception:
            pass

    papers_root = output_root / "papers"
    paper_dirs: List[Path] = (
        sorted(p for p in papers_root.iterdir() if p.is_dir())
        if papers_root.is_dir()
        else []
    )
    seen_stems = {p.name for p in paper_dirs}

    reports: List[PaperReport] = []
    all_problems: List[ProblemQuestion] = []

    for paper_dir in paper_dirs:
        manifest_entry = manifest_papers.get(paper_dir.name)
        report, problems = _analyse_paper(output_root, paper_dir, expected, manifest_entry)
        reports.append(report)
        all_problems.extend(problems)

    # Manifest-listed papers that have NO output dir AND no current crash
    # entry are stale (left over from a previous batch). Skip them — the QA
    # report should reflect the current contents of output/papers/, not the
    # union with old manifest history.
    for stem, entry in manifest_papers.items():
        if stem in seen_stems:
            continue
        if not entry.get("error"):
            continue
        report, _ = _analyse_paper(
            output_root, papers_root / stem, expected, entry
        )
        reports.append(report)

    reports.sort(key=lambda r: ({"failed": 0, "review": 1, "pass": 2}[r.status], r.source_file))

    counts = {"pass": 0, "review": 0, "failed": 0}
    for r in reports:
        counts[r.status] = counts.get(r.status, 0) + 1

    # Severity-tier rollups (per paper + per question)
    tier_counts = {
        "failed": 0, "blocked": 0, "review": 0,
        "render_fix_only": 0, "fallback_ok": 0, "pass": 0,
    }
    blocker_qs = 0
    fallback_qs = 0
    render_fix_qs = 0
    review_qs = 0
    failed_paper_qs = 0
    total_qs = 0
    for r in reports:
        tier_counts[r.severity_tier] = tier_counts.get(r.severity_tier, 0) + 1
        if r.status == "failed":
            failed_paper_qs += expected
        else:
            total_qs += int(r.total_questions or 0)
        blocker_qs += len(r.blocker_questions)
        fallback_qs += len(r.acceptable_fallback_questions)
        render_fix_qs += len(r.render_fix_questions)
        review_qs += len(r.review_questions)

    grand_total_qs = total_qs + failed_paper_qs
    pass_qs = max(0, total_qs - blocker_qs - fallback_qs - render_fix_qs - review_qs)
    demo_safe_qs = max(
        0, grand_total_qs - blocker_qs - failed_paper_qs
    )
    success_pct = (
        100.0 * demo_safe_qs / grand_total_qs if grand_total_qs else 0.0
    )
    blocker_pct = (
        100.0 * blocker_qs / grand_total_qs if grand_total_qs else 0.0
    )

    return QASummary(
        output_root=str(output_root.as_posix()),
        expected_questions=expected,
        counts=counts,
        papers=reports,
        total_problems=len(all_problems),
        total_papers=len(reports),
        total_questions=grand_total_qs,
        failed_papers=tier_counts["failed"],
        blocked_papers=tier_counts["blocked"],
        review_papers=tier_counts["review"],
        render_fix_only_papers=tier_counts["render_fix_only"],
        fallback_ok_papers=tier_counts["fallback_ok"],
        pass_papers=tier_counts["pass"],
        blocker_question_count=blocker_qs,
        render_fix_question_count=render_fix_qs,
        review_question_count=review_qs,
        acceptable_fallback_question_count=fallback_qs,
        pass_question_count=pass_qs,
        estimated_demo_safe_question_count=demo_safe_qs,
        estimated_success_rate_percent=round(success_pct, 2),
        blocker_rate_percent=round(blocker_pct, 2),
    ), all_problems  # type: ignore[return-value]


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Batch QA over an extractor output folder."
    )
    parser.add_argument(
        "--output", default="output",
        help="Root output folder produced by extract.py (default: output).",
    )
    parser.add_argument(
        "--expected", type=int, default=40,
        help="Expected question count per paper (default: 40).",
    )
    args = parser.parse_args(argv)

    output_root = Path(args.output).resolve()
    if not output_root.is_dir():
        print(f"[error] output folder not found: {output_root}", file=sys.stderr)
        return 2

    summary, problems = run_qa(output_root, args.expected)  # type: ignore[misc]
    qa_dir = output_root / "qa"
    qa_dir.mkdir(parents=True, exist_ok=True)

    # Persist the full QASummary including the production-QA metric fields
    # added for severity-tiered reporting.
    summary_dict = asdict(summary)
    (qa_dir / "qa_summary.json").write_text(
        json.dumps(summary_dict, indent=2), encoding="utf-8"
    )
    (qa_dir / "qa_summary.md").write_text(_render_summary_md(summary), encoding="utf-8")

    problems_dict = {
        "total_problems": len(problems),
        "problems": [asdict(p) for p in problems],
    }
    (qa_dir / "problem_cases.json").write_text(
        json.dumps(problems_dict, indent=2), encoding="utf-8"
    )
    (qa_dir / "problem_cases.md").write_text(_render_problems_md(problems), encoding="utf-8")

    c = summary.counts
    print(
        f"[done] {len(summary.papers)} papers | "
        f"failed={c.get('failed', 0)} review={c.get('review', 0)} pass={c.get('pass', 0)} | "
        f"problems={summary.total_problems}"
    )
    print(f"[done] reports written to {qa_dir.as_posix()}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
