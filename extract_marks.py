"""extract_marks.py — extract answers from Cambridge mark scheme PDFs and
merge them into the matching ``questions.json`` files.

Cambridge MCQ mark schemes use one of two layouts:

  Modern (~2016 onward) — three columns per row:
      Question | Answer | Marks
            1 |   A    |  1
            2 |   B    |  1
            ...

  Old (~2010-2015) — two side-by-side columns of ``(Number, Answer)`` pairs,
  20 questions per column:
            1  B   21  D
            2  D   22  C
            ...

We extract via PyMuPDF word boxes — for every digit token in [1, 40] we find
the nearest A/B/C/D to its right within a small y-band, scoring by horizontal
distance + a small y-mismatch penalty. The same algorithm handles both layouts.

After extraction the script:

  1. Writes ``correct_answer`` into each question of
     ``output/papers/<stem>/questions.json``.
  2. Writes ``output/marks_manifest.json`` (per-paper extraction summary).
  3. Writes ``output/qa/marks_qa.{json,md}`` — a QA pass that flags partial
     extractions, missing mark schemes, and invalid answers.

Usage::

    python extract_marks.py --marks papers/marksheets --output output

Add ``--dry-run`` to see what would be written without modifying any file.

No extractor changes. No LLM. No network calls.
"""
from __future__ import annotations

import argparse
import json
import sys
from collections import Counter
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import fitz

_VALID_ANSWERS = {"A", "B", "C", "D"}
_EXPECTED_QUESTIONS = 40
_Y_TOL = 10.0          # max abs(ly - ny) when pairing a number with a letter
_MAX_DX = 220.0        # don't pair across columns (modern: ~150, old: ~80)


# ---------------------------------------------------------------------------
# Data model
# ---------------------------------------------------------------------------

@dataclass
class MarkScheme:
    source_file: str
    paper_stem: str  # e.g. "9702_m24_qp_12"
    answers: Dict[int, str] = field(default_factory=dict)
    pages: int = 0
    extracted_count: int = 0


@dataclass
class MarkQA:
    paper_stem: str
    ms_source: str
    expected: int
    extracted: int
    mapped_to_questions: int
    missing_qnos: List[int] = field(default_factory=list)
    invalid_answers: List[Tuple[int, str]] = field(default_factory=list)
    questions_json_present: bool = True
    status: str = "pass"  # pass | partial | failed


# ---------------------------------------------------------------------------
# PDF extraction
# ---------------------------------------------------------------------------

def extract_answers_from_pdf(pdf_path: Path) -> Tuple[Dict[int, str], int]:
    """Return ``({qno: answer_letter}, page_count)``.

    Walks every page, collects digit tokens in [1, 40] as candidate question
    numbers and ``A/B/C/D`` tokens as candidate answers. For each number, the
    nearest letter to the right of it within ``_Y_TOL`` y and ``_MAX_DX`` x is
    chosen as its answer. ``_MAX_DX`` is small enough that a number in the
    left column does not pair with a letter in the right column of the
    two-column old layout.
    """
    doc = fitz.open(pdf_path)
    n_pages = len(doc)
    answers: Dict[int, str] = {}

    for pi in range(n_pages):
        page = doc[pi]
        words = page.get_text("words")  # (x0, y0, x1, y1, text, block, line, word_no)

        nums: List[Tuple[float, float, int]] = []
        letters: List[Tuple[float, float, str]] = []
        for w in words:
            text = (w[4] or "").strip()
            if not text:
                continue
            cx = (w[0] + w[2]) / 2.0
            cy = (w[1] + w[3]) / 2.0
            if text.isdigit():
                n = int(text)
                if 1 <= n <= _EXPECTED_QUESTIONS:
                    nums.append((cx, cy, n))
            elif text in _VALID_ANSWERS:
                letters.append((cx, cy, text))

        # For each candidate question number, find the nearest letter to the
        # right within the same row. Score = horizontal distance + 3x
        # vertical mismatch (penalises slight y-drift more than column gap).
        for nx, ny, n in nums:
            best_letter: Optional[str] = None
            best_score = float("inf")
            best_was_assigned = n in answers  # may already have a candidate
            for lx, ly, lt in letters:
                if lx <= nx + 5:
                    continue
                if abs(ly - ny) > _Y_TOL:
                    continue
                dx = lx - nx
                if dx > _MAX_DX:
                    continue
                score = abs(ly - ny) * 3.0 + dx
                if score < best_score:
                    best_score = score
                    best_letter = lt
            if best_letter is None:
                continue
            # Prefer the closer pairing if we somehow saw this n twice (the
            # "1" in the marks column on modern layouts can coincidentally
            # land in nums, but never matches a letter to its right).
            if not best_was_assigned:
                answers[n] = best_letter
            else:
                # Pick whichever score was lower — but we don't store the
                # previous score, so be conservative: keep existing.
                pass

    doc.close()
    return answers, n_pages


# ---------------------------------------------------------------------------
# Mapping
# ---------------------------------------------------------------------------

def _stem_for_qp(ms_stem: str) -> str:
    """Convert a mark-scheme stem to its question-paper stem.

    ``9702_m24_ms_12`` → ``9702_m24_qp_12``.
    """
    return ms_stem.replace("_ms_", "_qp_")


def _stem_for_ms(qp_stem: str) -> str:
    return qp_stem.replace("_qp_", "_ms_")


# ---------------------------------------------------------------------------
# Top-level CLI
# ---------------------------------------------------------------------------

def main(argv: Optional[List[str]] = None) -> int:
    parser = argparse.ArgumentParser(
        description="Extract Cambridge MCQ mark scheme answers and merge into questions.json."
    )
    parser.add_argument(
        "--marks", default="papers/marksheets",
        help="Folder of mark scheme PDFs (default: papers/marksheets).",
    )
    parser.add_argument(
        "--output", default="output",
        help="Output root produced by extract.py (default: output).",
    )
    parser.add_argument(
        "--dry-run", action="store_true",
        help="Compute mapping but do not modify questions.json or write QA files.",
    )
    args = parser.parse_args(argv)

    marks_dir = Path(args.marks).resolve()
    out_root = Path(args.output).resolve()
    if not marks_dir.is_dir():
        print(f"[error] marks folder not found: {marks_dir}")
        return 2
    if not out_root.is_dir():
        print(f"[error] output folder not found: {out_root}")
        return 2

    papers_root = out_root / "papers"
    qa_dir = out_root / "qa"
    qa_dir.mkdir(parents=True, exist_ok=True)

    schemes: List[MarkScheme] = []
    qas: List[MarkQA] = []

    for ms_pdf in sorted(marks_dir.glob("*.pdf")):
        ms_stem = ms_pdf.stem
        if "_ms_" not in ms_stem:
            print(f"[warn] skipping non-marks file: {ms_pdf.name}")
            continue
        paper_stem = _stem_for_qp(ms_stem)
        questions_json = papers_root / paper_stem / "questions.json"

        try:
            answers, n_pages = extract_answers_from_pdf(ms_pdf)
        except Exception as e:  # noqa: BLE001
            print(f"[error] failed to read {ms_pdf.name}: {e!r}")
            answers, n_pages = {}, 0

        scheme = MarkScheme(
            source_file=ms_pdf.name,
            paper_stem=paper_stem,
            answers=answers,
            pages=n_pages,
            extracted_count=len(answers),
        )
        schemes.append(scheme)

        mapped = 0
        missing: List[int] = []
        invalid: List[Tuple[int, str]] = []
        questions_json_present = questions_json.exists()

        if not questions_json_present:
            qa = MarkQA(
                paper_stem=paper_stem,
                ms_source=ms_pdf.name,
                expected=_EXPECTED_QUESTIONS,
                extracted=len(answers),
                mapped_to_questions=0,
                missing_qnos=[n for n in range(1, _EXPECTED_QUESTIONS + 1) if n not in answers],
                invalid_answers=invalid,
                questions_json_present=False,
                status="failed",
            )
            qas.append(qa)
            print(f"[warn] {ms_pdf.name}: no questions.json for {paper_stem}")
            continue

        data = json.loads(questions_json.read_text(encoding="utf-8"))
        questions = data.get("questions") or []
        by_qno = {int(q["question_number"]): q for q in questions}

        for n in range(1, _EXPECTED_QUESTIONS + 1):
            if n not in by_qno:
                # Extractor may have missed this question entirely — still
                # record the missing answer slot.
                missing.append(n)
                continue
            ans = answers.get(n, "")
            if not ans:
                missing.append(n)
                # Don't overwrite an existing correct_answer with empty.
                continue
            if ans not in _VALID_ANSWERS:
                invalid.append((n, ans))
                continue
            by_qno[n]["correct_answer"] = ans
            mapped += 1

        if not args.dry_run:
            questions_json.write_text(
                json.dumps(data, indent=2, ensure_ascii=False),
                encoding="utf-8",
            )

        if mapped == _EXPECTED_QUESTIONS:
            status = "pass"
        elif mapped > 0:
            status = "partial"
        else:
            status = "failed"

        qa = MarkQA(
            paper_stem=paper_stem,
            ms_source=ms_pdf.name,
            expected=_EXPECTED_QUESTIONS,
            extracted=len(answers),
            mapped_to_questions=mapped,
            missing_qnos=missing,
            invalid_answers=invalid,
            questions_json_present=True,
            status=status,
        )
        qas.append(qa)

    # ---- manifest ---------------------------------------------------------
    counts = Counter(q.status for q in qas)
    manifest = {
        "marks_dir": str(marks_dir.as_posix()),
        "output_root": str(out_root.as_posix()),
        "papers_with_marks": counts.get("pass", 0),
        "papers_partial_marks": counts.get("partial", 0),
        "papers_failed_marks": counts.get("failed", 0),
        "total_marks_pdfs": len(schemes),
        "total_answers_extracted": sum(s.extracted_count for s in schemes),
        "schemes": [asdict(s) for s in schemes],
    }
    if not args.dry_run:
        (out_root / "marks_manifest.json").write_text(
            json.dumps(manifest, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )

    # ---- detect papers with NO mark scheme -------------------------------
    papers_seen = {s.paper_stem for s in schemes}
    orphan_papers: List[str] = []
    if papers_root.is_dir():
        for d in sorted(papers_root.iterdir()):
            if d.is_dir() and d.name not in papers_seen:
                orphan_papers.append(d.name)

    # ---- QA outputs -------------------------------------------------------
    qa_summary = {
        "marks_dir": str(marks_dir.as_posix()),
        "output_root": str(out_root.as_posix()),
        "papers": len(qas),
        "pass": counts.get("pass", 0),
        "partial": counts.get("partial", 0),
        "failed": counts.get("failed", 0),
        "total_answers_extracted": sum(q.extracted for q in qas),
        "total_answers_mapped": sum(q.mapped_to_questions for q in qas),
        "questions_per_paper_expected": _EXPECTED_QUESTIONS,
        "papers_without_mark_scheme": orphan_papers,
        "results": [asdict(q) for q in qas],
    }

    qa_json_path = qa_dir / "marks_qa.json"
    qa_md_path = qa_dir / "marks_qa.md"

    if not args.dry_run:
        qa_json_path.write_text(
            json.dumps(qa_summary, indent=2, ensure_ascii=False), encoding="utf-8"
        )
        md = [
            "# Marks QA",
            "",
            f"- marks dir: `{qa_summary['marks_dir']}`",
            f"- papers seen: **{qa_summary['papers']}** "
            f"(pass **{qa_summary['pass']}**, "
            f"partial **{qa_summary['partial']}**, "
            f"failed **{qa_summary['failed']}**)",
            f"- expected answers per paper: {qa_summary['questions_per_paper_expected']}",
            f"- total answers extracted: **{qa_summary['total_answers_extracted']}**",
            f"- total answers mapped to questions: **{qa_summary['total_answers_mapped']}**",
        ]
        if orphan_papers:
            md.append(
                f"- ⚠️ extracted papers with no mark scheme: **{len(orphan_papers)}** "
                f"({', '.join(orphan_papers)})"
            )
        md.append("")
        for label, key in (("Failed", "failed"), ("Partial", "partial"), ("Pass", "pass")):
            bucket = [q for q in qas if q.status == key]
            md.append(f"## {label} ({len(bucket)})")
            md.append("")
            if not bucket:
                md.append("_none_")
                md.append("")
                continue
            for q in bucket:
                md.append(f"### {q.paper_stem}")
                md.append(
                    f"- ms: `{q.ms_source}`  "
                    f"extracted={q.extracted}/{q.expected}  "
                    f"mapped={q.mapped_to_questions}/{q.expected}"
                )
                if not q.questions_json_present:
                    md.append("- ⚠️ no `questions.json` for this paper (run `extract.py` first)")
                if q.missing_qnos:
                    md.append(
                        f"- missing answers for: Q"
                        f"{', Q'.join(map(str, q.missing_qnos))}"
                    )
                if q.invalid_answers:
                    md.append(f"- invalid answers (qno, value): {q.invalid_answers}")
                md.append("")
        qa_md_path.write_text("\n".join(md).rstrip() + "\n", encoding="utf-8")

    print(
        f"[done] mark schemes processed: {len(schemes)} "
        f"(pass={counts.get('pass',0)} "
        f"partial={counts.get('partial',0)} "
        f"failed={counts.get('failed',0)})"
    )
    print(f"  total answers extracted: {qa_summary['total_answers_extracted']}")
    print(f"  total answers mapped:    {qa_summary['total_answers_mapped']}")
    if orphan_papers:
        print(
            f"  ⚠️  {len(orphan_papers)} paper(s) without a mark scheme: "
            f"{orphan_papers[:3]}{'...' if len(orphan_papers) > 3 else ''}"
        )
    if not args.dry_run:
        rel_qa = qa_json_path.relative_to(out_root).as_posix()
        print(f"[done] wrote marks_manifest.json and {rel_qa}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
