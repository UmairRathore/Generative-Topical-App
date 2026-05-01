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


# ---------------------------------------------------------------------------
# Checks
# ---------------------------------------------------------------------------

def _has(s: str, *needles: str) -> bool:
    return any(n in s for n in needles)


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

        debug = paper_dir / "debug" / f"q{qno:03d}_debug.png"
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
    lines.append("")
    return "\n".join(lines)


def _render_summary_md(summary: QASummary) -> str:
    counts = summary.counts
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
            if pq.debug_image:
                lines.append(f"- debug: [{pq.debug_image}]({pq.debug_image})")
            for a in pq.asset_paths:
                if a == pq.debug_image:
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

    # Manifest-listed papers that have NO output dir (e.g. crashed before any
    # files were written) still need to appear in the report.
    for stem, entry in manifest_papers.items():
        if stem in seen_stems:
            continue
        report, _ = _analyse_paper(
            output_root, papers_root / stem, expected, entry
        )
        reports.append(report)

    reports.sort(key=lambda r: ({"failed": 0, "review": 1, "pass": 2}[r.status], r.source_file))

    counts = {"pass": 0, "review": 0, "failed": 0}
    for r in reports:
        counts[r.status] = counts.get(r.status, 0) + 1

    return QASummary(
        output_root=str(output_root.as_posix()),
        expected_questions=expected,
        counts=counts,
        papers=reports,
        total_problems=len(all_problems),
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

    summary_dict = {
        "output_root": summary.output_root,
        "expected_questions": summary.expected_questions,
        "counts": summary.counts,
        "total_problems": summary.total_problems,
        "papers": [asdict(p) for p in summary.papers],
    }
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
