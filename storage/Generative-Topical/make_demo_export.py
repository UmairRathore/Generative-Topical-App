"""Build a demo export zip for a set of papers, with QA fixes overlaid.

For each paper, copies questions.json + all images, overlays the corrected
images_fix crops onto images/ (same filename), keeps images_fix/ so the
reconciled-orphan references (papers/<stem>/images_fix/...) still resolve, and
includes the claude_log.txt. Verifies every questions.json image reference
resolves inside the staged tree before zipping.

Usage:
  python make_demo_export.py --name demo_batch4 --papers 9702_w20_qp_11,9702_w20_qp_12
  python make_demo_export.py --name demo_all_done --reviewed   # all === RE-REVIEWED === papers
"""
from __future__ import annotations

import argparse
import json
import shutil
import zipfile
from pathlib import Path

OUT = Path("output")
EXPORTS = OUT / "exports"
STAGE_ROOT = Path("C:/Temp/demo_exports")

README = """Demo export - 9702 Physics QA pipeline (see QA_LIFECYCLE.md).

Per paper (papers/<stem>/):
  questions.json  - image_path values are RELATIVE TO THIS ZIP ROOT
  images/         - all crops, WITH QA FIXES ALREADY OVERLAID
  images_fix/     - the QA-fix set; also the target of the few
                    "papers/<stem>/images_fix/..." references for reconciled
                    diagrams the extractor never emitted
  claude_log.txt  - QA audit trail (reference)

Resolve image_path relative to the zip root; no overlay logic needed.
All papers here passed: deterministic QA pass + Opus full visual sweep
(=== DONE === and === RE-REVIEWED === markers in each log).
"""


def referenced_paths(qdata: dict) -> set:
    paths = set()
    for q in qdata.get("questions", []):
        for a in (q.get("question_images_between_text") or []) + (q.get("question_images_after_text") or []) + (q.get("assets") or []):
            if a.get("image_path"):
                paths.add(a["image_path"])
        for o in q.get("options") or []:
            for a in o.get("images") or []:
                if a.get("image_path"):
                    paths.add(a["image_path"])
        t = q.get("option_table")
        if t and t.get("image_path"):
            paths.add(t["image_path"])
    return paths


def stage_paper(stem: str, stage: Path) -> None:
    src = OUT / "papers" / stem
    dst = stage / "papers" / stem
    (dst / "images").mkdir(parents=True, exist_ok=True)
    (dst / "images_fix").mkdir(parents=True, exist_ok=True)
    shutil.copy2(src / "questions.json", dst / "questions.json")
    for p in (src / "images").glob("*.png"):
        shutil.copy2(p, dst / "images" / p.name)
    fix = src / "images_fix"
    if fix.is_dir():
        for p in fix.glob("*.png"):
            shutil.copy2(p, dst / "images" / p.name)      # overlay
            shutil.copy2(p, dst / "images_fix" / p.name)  # keep originals
    if (src / "claude_log.txt").exists():
        shutil.copy2(src / "claude_log.txt", dst / "claude_log.txt")


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--name", required=True)
    ap.add_argument("--papers", default="")
    ap.add_argument("--reviewed", action="store_true",
                    help="include all papers whose log has === RE-REVIEWED ===")
    args = ap.parse_args(argv)

    if args.reviewed:
        stems = []
        for d in sorted((OUT / "papers").iterdir()):
            log = d / "claude_log.txt"
            if log.is_file() and "=== RE-REVIEWED ===" in log.read_text(encoding="utf-8", errors="replace"):
                stems.append(d.name)
    else:
        stems = [s.strip() for s in args.papers.split(",") if s.strip()]
    if not stems:
        print("no papers selected")
        return 1

    stage = STAGE_ROOT / args.name
    if stage.exists():
        shutil.rmtree(stage)
    stage.mkdir(parents=True)
    for s in stems:
        stage_paper(s, stage)
    (stage / "README.txt").write_text(README, encoding="utf-8")

    # verify references
    tot = miss = 0
    for qj in (stage / "papers").glob("*/questions.json"):
        d = json.loads(qj.read_text(encoding="utf-8"))
        for p in referenced_paths(d):
            tot += 1
            if not (stage / p.replace("/", "\\")).exists() and not (stage / p).exists():
                miss += 1
                print("MISSING", p)
    print(f"{len(stems)} papers, refs {tot}, missing {miss}")
    if miss:
        print("ABORT: unresolved references")
        return 2

    EXPORTS.mkdir(parents=True, exist_ok=True)
    zip_path = EXPORTS / f"{args.name}.zip"
    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for f in stage.rglob("*"):
            if f.is_file():
                zf.write(f, f.relative_to(stage))
    mb = zip_path.stat().st_size / (1024 * 1024)
    print(f"WROTE {zip_path}  ({mb:.1f} MB)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
