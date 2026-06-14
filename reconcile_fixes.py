"""Reconcile manual QA fixes into questions.json.

The manual QA pass (see claude_log.txt in each paper folder) produced
corrected crops in images_fix/. Most fixes share a filename with images/ and
need no JSON change (consumers overlay images_fix by filename). But some fix
files have NO counterpart anywhere in questions.json:

  - never-extracted question diagrams (the extractor missed the figure), and
  - criterion-7 companion diagrams (point-label questions where the full
    diagram was added as qNNN_question_diagram_01.png).

This script links those orphans into questions.json so downstream consumers
can see them, and reclassifies the known diagram-as-table cases (degenerate
option_table whose image is actually a circuit/diagram).

Usage:
  python reconcile_fixes.py                 # dry-run over completed QA papers
  python reconcile_fixes.py --apply         # write changes (one-time .bak kept)
  python reconcile_fixes.py --papers 9702_s25_qp_14 --apply

Idempotent: a second --apply run reports zero changes.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

from src.qa_common import (
    iter_image_assets,
    load_questions,
    locate_crop_on_page,
    log_has_marker,
    paper_dirs,
    parse_fix_provenance,
    px_rect_to_pdf_bbox,
    referenced_filenames,
    save_questions_with_backup,
)

DPI = 200

# (paper_stem, question_number, fix filename) -> reclassification action.
# "attach": null the degenerate option_table and attach the fixed file as an
#           after-text question diagram.
# "options_from_assets": null the degenerate option_table and populate
#           options[*].images from the qNNN_option_X assets already present in
#           assets[] (the diagram itself arrives via the orphan pass).
KNOWN_MISCLASSIFIED = {
    ("9702_s23_qp_12", 37, "q037_table_01.png"): "attach",
    ("9702_s24_qp_12", 36, "q036_table_01.png"): "attach",
    ("9702_s24_qp_13", 32, "q032_table_01.png"): "options_from_assets",
    # Batch 3 (reviewed 2026-06-13): 2x2 resistor-circuit grids wrapped as
    # degenerate option_tables; A/B/C/D are circuit arrangements.
    ("9702_s10_qp_11", 36, "q036_table_01.png"): "options_from_assets",
    ("9702_s10_qp_12", 32, "q032_table_01.png"): "options_from_assets",
    ("9702_s10_qp_13", 34, "q034_table_01.png"): "options_from_assets",
    ("9702_s11_qp_11", 35, "q035_table_01.png"): "attach",
}


def derive_layout_type(q: dict) -> str:
    """Mirror of src.extractor._derive_layout_type over serialized JSON."""
    has_q_diagram = bool(q.get("question_images_between_text") or q.get("question_images_after_text"))
    has_option_images = any(o.get("images") for o in q.get("options") or [])
    has_option_table = q.get("option_table") is not None
    if has_option_table:
        return "option_table"
    if has_q_diagram and has_option_images:
        return "question_diagram_and_option_images"
    if has_option_images:
        return "option_images"
    if has_q_diagram:
        return "question_diagram"
    if any(o.get("text") for o in q.get("options") or []):
        return "text_only"
    return "mixed"


def question_for(qdata: dict, qnum: int) -> dict | None:
    for q in qdata.get("questions", []):
        if q.get("question_number") == qnum:
            return q
    return None


def find_orphan_fixes(paper_dir: Path, qdata: dict) -> list[Path]:
    fix_dir = paper_dir / "images_fix"
    if not fix_dir.is_dir():
        return []
    referenced = referenced_filenames(qdata)
    # Files handled by the reclassification table are never orphans — after
    # their degenerate option_table is nulled they intentionally lose their
    # reference (the question's diagram is covered separately).
    reclass_names = {fname for (stem, _qn, fname) in KNOWN_MISCLASSIFIED if stem == paper_dir.name}
    return sorted(p for p in fix_dir.glob("*.png")
                  if p.name not in referenced and p.name not in reclass_names)


def derive_provenance(paper_dir: Path, fix_path: Path, q: dict, prov: dict):
    """Return (page, px_rect) for a fix file, or (None, None)."""
    hit = prov.get(fix_path.name)
    if hit:
        return hit
    # Template-match against the question's page range (±1 page).
    start = max(1, int(q.get("page_start") or 1) - 1)
    end = int(q.get("page_end") or q.get("page_start") or 1) + 1
    for page in range(start, end + 1):
        page_png = paper_dir / "pages" / f"page_{page:03d}.png"
        if not page_png.exists():
            continue
        match = locate_crop_on_page(fix_path, page_png)
        if match:
            return page, match[0]
    return None, None


OPTION_FILE_RE = re.compile(r"_option_([A-D])(?:_\d+)?\.png$")


def build_asset(stem: str, fix_name: str, page, px_rect) -> dict:
    qid = fix_name.rsplit(".", 1)[0]
    is_option = bool(OPTION_FILE_RE.search(fix_name))
    return {
        "id": qid,
        "image_path": f"papers/{stem}/images_fix/{fix_name}",
        "page": page if page is not None else None,
        "bbox": px_rect_to_pdf_bbox(px_rect, DPI) if px_rect else [],
        "role": "option_image" if is_option else "question_image_after_text",
        "caption": "",
        "ocr_text": "",
        "confidence": 0.5,
        "diagram_labels": [],
        "source": "manual_qa_reconciliation",
    }


def attach_asset(q: dict, asset: dict) -> bool:
    """Attach a reconciled orphan. Option-image files route into the matching
    options[label].images; everything else into question_images_after_text.
    Both also append to the question-level assets[] mirror."""
    existing_ids = {a.get("id") for a in (q.get("assets") or [])}
    if asset["id"] in existing_ids:
        return False
    m = OPTION_FILE_RE.search(asset["id"] + ".png")
    if m:
        label = m.group(1)
        opt = next((o for o in (q.get("options") or []) if o.get("label") == label), None)
        if opt is not None:
            opt.setdefault("images", []).append(asset)
        else:
            # no matching option entry — fall back to after-text so it still renders
            q.setdefault("question_images_after_text", []).append(asset)
    else:
        q.setdefault("question_images_after_text", []).append(asset)
    q.setdefault("assets", []).append(asset)
    return True


def reconcile_paper(paper_dir: Path, apply: bool) -> dict:
    stem = paper_dir.name
    qdata = load_questions(paper_dir)
    log_text = (paper_dir / "claude_log.txt").read_text(encoding="utf-8", errors="replace") \
        if (paper_dir / "claude_log.txt").exists() else ""
    prov = parse_fix_provenance(log_text)

    result = {
        "paper": stem,
        "orphans_attached": [],
        "orphans_no_bbox": [],
        "reclassified": [],
        "skipped_existing": [],
        "layout_changes": [],
        "errors": [],
    }
    changed = False

    # --- orphan fix-only files -------------------------------------------
    for fix_path in find_orphan_fixes(paper_dir, qdata):
        try:
            qnum = int(fix_path.name[1:4])
        except ValueError:
            result["errors"].append(f"{fix_path.name}: cannot parse question number")
            continue
        q = question_for(qdata, qnum)
        if q is None:
            result["errors"].append(f"{fix_path.name}: question {qnum} not in JSON")
            continue
        page, px_rect = derive_provenance(paper_dir, fix_path, q, prov)
        asset = build_asset(stem, fix_path.name, page, px_rect)
        if not attach_asset(q, asset):
            result["skipped_existing"].append(fix_path.name)
            continue
        changed = True
        if page is None:
            q["needs_review"] = True
            q.setdefault("warnings", []).append(
                f"reconciled asset {fix_path.name} has no derivable page/bbox")
            result["orphans_no_bbox"].append(fix_path.name)
        else:
            result["orphans_attached"].append(
                {"file": fix_path.name, "question": qnum, "page": page, "px_rect": list(px_rect)})
        old_layout = q.get("layout_type")
        new_layout = derive_layout_type(q)
        if new_layout != old_layout:
            q["layout_type"] = new_layout
            result["layout_changes"].append(f"q{qnum}: {old_layout} -> {new_layout}")

    # --- known diagram-as-table reclassifications ------------------------
    for (mstem, qnum, fname), action in KNOWN_MISCLASSIFIED.items():
        if mstem != stem:
            continue
        q = question_for(qdata, qnum)
        if q is None:
            result["errors"].append(f"reclass q{qnum}: question not in JSON")
            continue
        if q.get("option_table") is None:
            result["skipped_existing"].append(f"reclass {fname} (already done)")
            continue
        q["option_table"] = None
        changed = True
        note = f"reclassified {fname}: option_table content is a circuit/diagram (manual QA)"
        if action == "attach":
            page, px_rect = derive_provenance(paper_dir, paper_dir / "images_fix" / fname, q, prov)
            asset = build_asset(stem, fname, page, px_rect)
            asset["reclassified_from"] = "option_table"
            attach_asset(q, asset)
            # Criterion-7 convention: A/B/C/D are point-labels on this single
            # diagram, so each option displays the full diagram.
            for opt in q.get("options") or []:
                if not opt.get("text") and not opt.get("images"):
                    opt["images"] = [asset]
        elif action == "options_from_assets":
            by_label = {}
            for a in q.get("assets") or []:
                aid = a.get("id", "")
                if "_option_" in aid:
                    by_label[aid.rsplit("_", 1)[-1]] = a
            for opt in q.get("options") or []:
                label = opt.get("label")
                if label in by_label and not opt.get("images"):
                    opt["images"] = [by_label[label]]
            note += "; options[].images populated from assets[]"
        q["needs_review"] = True
        q.setdefault("warnings", []).append(note)
        old_layout = q.get("layout_type")
        new_layout = derive_layout_type(q)
        if new_layout != old_layout:
            q["layout_type"] = new_layout
            result["layout_changes"].append(f"q{qnum}: {old_layout} -> {new_layout}")
        result["reclassified"].append({"question": qnum, "file": fname, "action": action})

    if changed and apply:
        save_questions_with_backup(paper_dir, qdata)
    result["changed"] = changed
    return result


def write_report(output_root: Path, results: list[dict], apply: bool) -> None:
    out_json = output_root / "reconciliation_report.json"
    payload = {"mode": "apply" if apply else "dry-run", "papers": results}
    out_json.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    lines = [f"# Reconciliation report ({payload['mode']})", ""]
    tot_orphans = tot_reclass = tot_nobbox = 0
    for r in results:
        n_orph = len(r["orphans_attached"]) + len(r["orphans_no_bbox"])
        if not (n_orph or r["reclassified"] or r["skipped_existing"] or r["errors"]):
            continue
        lines.append(f"## {r['paper']}")
        for o in r["orphans_attached"]:
            lines.append(f"- attached {o['file']} -> q{o['question']} (page {o['page']}, px {o['px_rect']})")
        for f in r["orphans_no_bbox"]:
            lines.append(f"- attached {f} (NO bbox derivable - needs_review set)")
        for rc in r["reclassified"]:
            lines.append(f"- reclassified q{rc['question']} {rc['file']} ({rc['action']})")
        for s in r["skipped_existing"]:
            lines.append(f"- skipped (already present): {s}")
        for e in r["errors"]:
            lines.append(f"- ERROR: {e}")
        for lc in r["layout_changes"]:
            lines.append(f"- layout: {lc}")
        lines.append("")
        tot_orphans += n_orph
        tot_reclass += len(r["reclassified"])
        tot_nobbox += len(r["orphans_no_bbox"])
    lines.insert(1, f"\nTotals: {tot_orphans} orphans attached ({tot_nobbox} without bbox), "
                    f"{tot_reclass} reclassified.\n")
    (output_root / "reconciliation_report.md").write_text("\n".join(lines), encoding="utf-8")
    print("\n".join(lines))


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--output-root", default="output", type=Path)
    ap.add_argument("--papers", default="", help="comma-separated paper stems (default: papers with completed QA logs)")
    ap.add_argument("--apply", action="store_true", help="write changes (default is dry-run)")
    args = ap.parse_args(argv)

    if args.papers:
        wanted = {s.strip() for s in args.papers.split(",") if s.strip()}
        dirs = [d for d in paper_dirs(args.output_root) if d.name in wanted]
    else:
        # Default: papers whose log shows a completed QA pass.
        dirs = [d for d in paper_dirs(args.output_root)
                if log_has_marker(d, "Coverage extension") or log_has_marker(d, "Deep re-check")
                or log_has_marker(d, "=== DONE ===")]

    if not dirs:
        print("No papers selected.", file=sys.stderr)
        return 1

    print(f"{'APPLY' if args.apply else 'DRY-RUN'} over {len(dirs)} papers\n")
    results = [reconcile_paper(d, args.apply) for d in dirs]
    write_report(args.output_root, results, args.apply)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
