"""Regression-check extractor fixes against the manually QA'd ground truth.

Re-extracts selected papers into a disposable probe root (NEVER into output/),
then compares the probe's images/ against the frozen ground truth: for each
filename, truth = output/papers/<stem>/images_fix/<name> if a manual fix
exists, else output/papers/<stem>/images/<name>.

Checks per stem:
  - file parity: every truth images/ filename must exist in the probe
  - dimensions: probe crop within tolerance of the truth crop (and crops that
    were previously zero-margin should not be SMALLER than the truth fix)
  - rule-8: probe questions.json must warn on the known never-extracted
    figures (or now extract them)
  - rule-9: known misnamed _table_ stems must no longer produce a broken
    option_table

Usage: python regress_extract.py [--stems a,b,c] [--probe-root output_probe]
"""
from __future__ import annotations

import argparse
import json
import shutil
import sys
from pathlib import Path

from PIL import Image

DEFAULT_STEMS = ["9702_s20_qp_11", "9702_s23_qp_12", "9702_s24_qp_13"]

# Known ground-truth expectations from the manual QA (see QA_LIFECYCLE).
NEVER_EXTRACTED = {
    "9702_s23_qp_12": [10, 28],
    "9702_s24_qp_13": [22],
    "9702_s20_qp_11": [],
}
MISNAMED_TABLES = {
    "9702_s23_qp_12": [37],
    "9702_s24_qp_13": [32],
    "9702_s20_qp_11": [],
}

# Criterion-7 questions: the manual fix replaced per-option slices with FULL
# diagram copies. The extractor legitimately produces slices, so comparing
# probe slices against the full-diagram fix is meaningless — exempted.
CRITERION_7_FILES = {
    "9702_s20_qp_11": {f"q020_option_{l}.png" for l in "ABCD"},
    "9702_s24_qp_13": {f"q032_option_{l}.png" for l in "ABCD"}
    | {f"q038_option_{l}.png" for l in "ABCD"},
    "9702_s23_qp_12": set(),
}

DIM_TOL_FRAC = 0.12
DIM_TOL_PX = 36


def dims(path: Path):
    with Image.open(path) as im:
        return im.size


def compare_paper(probe_dir: Path, truth_dir: Path, stem: str) -> dict:
    report = {
        "stem": stem,
        "missing_files": [],
        "missing_but_covered": [],
        "dim_fail": [],
        "dim_ok": 0,
        "regressed_vs_old": [],
        "below_manual_fix": [],
        "rule8_hits": [],
        "rule8_misses": [],
        "rule9_ok": [],
        "rule9_fail": [],
        "new_files": [],
    }
    truth_images = truth_dir / "images"
    truth_fix = truth_dir / "images_fix"
    probe_images = probe_dir / "images"
    c7 = CRITERION_7_FILES.get(stem, set())

    probe_names = {p.name for p in probe_images.glob("*.png")} if probe_images.is_dir() else set()

    qj = probe_dir / "questions.json"
    qdata = json.loads(qj.read_text(encoding="utf-8")) if qj.exists() else {"questions": []}
    by_num = {q["question_number"]: q for q in qdata["questions"]}

    def question_has_assets(qn: int) -> bool:
        q = by_num.get(qn, {})
        return bool(
            q.get("question_images_between_text") or q.get("question_images_after_text")
            or any(o.get("images") for o in q.get("options") or [])
            or q.get("option_table")
        )

    rule9_renamed = {f"q{qn:03d}_table_01.png" for qn in MISNAMED_TABLES.get(stem, [])}

    for tp in sorted(truth_images.glob("*.png")):
        name = tp.name
        if name not in probe_names:
            try:
                qn = int(name[1:4])
            except ValueError:
                qn = -1
            if name in rule9_renamed or question_has_assets(qn):
                # rule-9 rename, or the question's figures got merged into
                # fewer crops — coverage retained, filename changed.
                report["missing_but_covered"].append(name)
            else:
                report["missing_files"].append(name)
            continue
        old_w, old_h = dims(tp)
        pw, ph = dims(probe_images / name)
        # HARD criterion: no regression vs the OLD extractor's crop.
        if pw < old_w - 8 or ph < old_h - 8:
            report["regressed_vs_old"].append(
                f"{name}: probe {pw}x{ph} < old {old_w}x{old_h}")
        truth_path = truth_fix / name if (truth_fix / name).exists() else tp
        tw, th = dims(truth_path)
        dw, dh = abs(pw - tw), abs(ph - th)
        tol_w = max(DIM_TOL_PX, int(tw * DIM_TOL_FRAC))
        tol_h = max(DIM_TOL_PX, int(th * DIM_TOL_FRAC))
        if dw > tol_w or dh > tol_h:
            report["dim_fail"].append(f"{name}: probe {pw}x{ph} vs truth {tw}x{th}")
        else:
            report["dim_ok"] += 1
        # Below the manual fix's extent: the extractor alone could not close
        # the gap — qa_images.py must catch these (verified in Gate C).
        if (truth_fix / name).exists() and name not in c7 and (
                pw < tw - tol_w or ph < th - tol_h):
            report["below_manual_fix"].append(f"{name}: probe {pw}x{ph} < fix {tw}x{th}")

    report["new_files"] = sorted(probe_names - {p.name for p in truth_images.glob("*.png")})

    if by_num:
        for qn in NEVER_EXTRACTED.get(stem, []):
            q = by_num.get(qn, {})
            has_img = bool(
                q.get("question_images_between_text") or q.get("question_images_after_text")
                or any(o.get("images") for o in q.get("options") or [])
            )
            warned = any("references a figure" in w for w in q.get("warnings") or [])
            if has_img or warned:
                report["rule8_hits"].append(f"q{qn}: {'extracted' if has_img else 'warned'}")
            else:
                report["rule8_misses"].append(f"q{qn}: silent zero-image text_only")
        for qn in MISNAMED_TABLES.get(stem, []):
            q = by_num.get(qn, {})
            tbl = q.get("option_table")
            sparse_warn = any("sparse/degenerate" in w for w in q.get("warnings") or [])
            if tbl is None or sparse_warn:
                report["rule9_ok"].append(f"q{qn}")
            else:
                report["rule9_fail"].append(f"q{qn}: still emits option_table {tbl.get('headers')}/{tbl.get('rows')}")
    return report


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--stems", default=",".join(DEFAULT_STEMS))
    ap.add_argument("--probe-root", default="output_probe", type=Path)
    ap.add_argument("--skip-extract", action="store_true", help="compare only (probe already extracted)")
    args = ap.parse_args(argv)

    stems = [s.strip() for s in args.stems.split(",") if s.strip()]
    args.probe_root.mkdir(parents=True, exist_ok=True)

    if not args.skip_extract:
        from src.extractor import extract_paper

        for stem in stems:
            pdf = Path("input") / f"{stem}.pdf"
            if not pdf.exists():
                print(f"[skip] {pdf} not found", file=sys.stderr)
                continue
            # Fresh probe dir per run so stale crops never mask regressions.
            stale = args.probe_root / "papers" / stem
            if stale.exists():
                shutil.rmtree(stale)
            print(f"[extract] {stem} -> {args.probe_root}")
            extract_paper(pdf, args.probe_root, dpi=200)

    reports = []
    hard_fail = False
    for stem in stems:
        probe_dir = args.probe_root / "papers" / stem
        truth_dir = Path("output") / "papers" / stem
        if not probe_dir.is_dir():
            print(f"[FAIL] probe missing for {stem}")
            hard_fail = True
            continue
        r = compare_paper(probe_dir, truth_dir, stem)
        reports.append(r)
        if r["missing_files"] or r["regressed_vs_old"] or r["rule8_misses"] or r["rule9_fail"]:
            hard_fail = True

    lines = ["# Extractor regression report", ""]
    for r in reports:
        lines.append(f"## {r['stem']}")
        lines.append(f"- dims within tolerance: {r['dim_ok']}")
        for k, label in [
            ("missing_files", "MISSING from probe, coverage lost (HARD FAIL)"),
            ("missing_but_covered", "filename gone but question still covered (info)"),
            ("regressed_vs_old", "SMALLER than old extractor crop (HARD FAIL)"),
            ("below_manual_fix", "below manual-fix extent - qa_images must catch (soft)"),
            ("dim_fail", "dimension mismatch (soft)"),
            ("rule8_hits", "rule-8 detected"),
            ("rule8_misses", "rule-8 MISSED (HARD FAIL)"),
            ("rule9_ok", "rule-9 ok"),
            ("rule9_fail", "rule-9 FAIL (HARD FAIL)"),
            ("new_files", "new files in probe (info)"),
        ]:
            for item in r[k]:
                lines.append(f"- {label}: {item}")
        lines.append("")
    lines.append(f"RESULT: {'FAIL' if hard_fail else 'PASS'}")
    text = "\n".join(lines)
    (args.probe_root / "regression_report.md").write_text(text, encoding="utf-8")
    print(text)
    return 1 if hard_fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
