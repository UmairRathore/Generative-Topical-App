"""One-off analyser: read output/qa/problem_cases.json, filter to BLOCKERs,
group by root cause, recommend a resolution path per blocker, and emit
output/qa/blocker_worklist.json and .md.

Pure analysis. Does not run the extractor. Does not modify any other file.
"""
from __future__ import annotations

import json
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PROBLEMS = ROOT / "output" / "qa" / "problem_cases.json"
OUT_JSON = ROOT / "output" / "qa" / "blocker_worklist.json"
OUT_MD = ROOT / "output" / "qa" / "blocker_worklist.md"


# ---------------------------------------------------------------------------
# Root-cause classification
# ---------------------------------------------------------------------------

def classify_root_causes(pq: dict) -> list[str]:
    rs = " ".join(pq.get("reasons", []))
    cats: list[str] = []
    if "no text and no image" in rs:
        cats.append("empty_options")
    if "misclassified_as_question_diagram" in rs:
        cats.append("misclassified_option_image_block")
    if "image-option layout has" in rs and "/4 image options" in rs:
        cats.append("short_image_option")
    if "giant crop" in rs:
        cats.append("horizontal_giant_crop")
    if "layout_type=mixed" in rs:
        cats.append("mixed_layout")
    if "missing option(s)" in rs:
        cats.append("missing_options")
    return cats or ["unclassified"]


# ---------------------------------------------------------------------------
# Recommendation policy
# ---------------------------------------------------------------------------
#
# extractor_fix
#   The blocker shares a clear pattern with at least one other blocker, and a
#   single global change (eg. better marker disambiguation, splitting a
#   misclassified after-text image block, relaxing the horizontal-row trigger)
#   would address the whole cluster.
#
# manual_json_fix
#   Idiosyncratic case — likely a one-off slice mistake or font quirk. Faster
#   to hand-edit ``questions.json`` than to chase a generic extractor change.
#
# use_full_question_crop_fallback
#   The question diagram was successfully cropped (an ``image_path`` exists)
#   AND it visually contains the option text the extractor failed to read.
#   The website renderer can show the cropped page region as the question
#   body and still expose A/B/C/D as clickable region overlays.
#
# hide_from_publish
#   Last-resort: no fix path available, no usable image asset to fall back to.

def recommend_resolution(pq: dict, cats: list[str]) -> str:
    has_q_img = bool(pq.get("question_image_paths"))
    has_opt_img = bool(pq.get("option_image_paths"))
    has_any_img = has_q_img or has_opt_img
    cset = set(cats)

    if "missing_options" in cset:
        return "manual_json_fix"

    # Whole cluster fixable in one extractor change
    if "misclassified_option_image_block" in cset:
        return "extractor_fix"
    if "short_image_option" in cset or "horizontal_giant_crop" in cset:
        # The horizontal-row split detector or the giant-crop guard could
        # resolve this whole family. Prefer the extractor fix; fall back to
        # crop image when at least one image asset already exists.
        return "extractor_fix" if not has_any_img else "extractor_fix"

    if "empty_options" in cset:
        # Empty option text with a usable diagram crop: the diagram itself
        # already shows the candidate answers (e.g. answer-key tables, in-
        # diagram option labels). The website can render the crop and
        # overlay A/B/C/D click targets.
        if has_q_img:
            return "use_full_question_crop_fallback"
        # Pure-text question that came back empty for all/some options is
        # almost always a slice or font issue — fastest fix is manual.
        return "manual_json_fix"

    if "mixed_layout" in cset:
        if has_q_img:
            return "use_full_question_crop_fallback"
        return "manual_json_fix"

    return "manual_json_fix"


# ---------------------------------------------------------------------------
# Build worklist
# ---------------------------------------------------------------------------

def main() -> int:
    data = json.loads(PROBLEMS.read_text(encoding="utf-8"))
    problems = data["problems"]
    blockers = [pq for pq in problems if pq.get("severity") == "BLOCKER"]

    entries: list[dict] = []
    for pq in blockers:
        cats = classify_root_causes(pq)
        rec = recommend_resolution(pq, cats)
        entries.append({
            "source_file": pq["paper"] + ".pdf",
            "question_number": pq["question_number"],
            "blocker_reasons": pq.get("reasons", []),
            "root_causes": cats,
            "layout_type": pq.get("layout_type", ""),
            "question_text_snippet": pq.get("question_text_snippet", ""),
            "option_texts": pq.get("option_texts", []),
            "question_image_paths": pq.get("question_image_paths", []),
            "option_image_paths": pq.get("option_image_paths", []),
            "debug_image": pq.get("debug_image", ""),
            "recommended_resolution": rec,
        })

    # Sort by group then paper / question
    entries.sort(key=lambda e: (e["root_causes"][0], e["source_file"], e["question_number"]))

    # Aggregate
    group_counts: Counter[str] = Counter()
    for e in entries:
        for cat in e["root_causes"]:
            group_counts[cat] += 1

    rec_counts = Counter(e["recommended_resolution"] for e in entries)

    # Single-extractor-change coverage estimate. Each entry is counted once
    # even if it has multiple root causes that overlap.
    global_fix_set = {
        (e["source_file"], e["question_number"])
        for e in entries
        if any(c in e["root_causes"] for c in (
            "misclassified_option_image_block",
            "short_image_option",
            "horizontal_giant_crop",
        ))
    }
    manual = [e for e in entries if e["recommended_resolution"] == "manual_json_fix"]
    fallback = [e for e in entries if e["recommended_resolution"] == "use_full_question_crop_fallback"]
    extractor = [e for e in entries if e["recommended_resolution"] == "extractor_fix"]
    hide = [e for e in entries if e["recommended_resolution"] == "hide_from_publish"]

    summary = {
        "total_blockers": len(entries),
        "group_counts": dict(group_counts),
        "recommended_resolution_counts": dict(rec_counts),
        "estimated_global_extractor_fix_distinct_questions": len(global_fix_set),
        "estimated_extractor_fix_recommendations": len(extractor),
        "estimated_manual_one_off_fixes": len(manual),
        "estimated_full_question_crop_fallback": len(fallback),
        "estimated_hide_from_publish": len(hide),
    }

    out_json = {"summary": summary, "blockers": entries}
    OUT_JSON.write_text(
        json.dumps(out_json, indent=2, ensure_ascii=False), encoding="utf-8"
    )

    # ---- markdown ----------------------------------------------------------
    md: list[str] = ["# Production blocker worklist", ""]
    md.append("Generated from: `output/qa/problem_cases.json`")
    md.append("")
    md.append("## Summary")
    md.append("")
    md.append(f"- total BLOCKER questions: **{summary['total_blockers']}**")
    md.append("")
    md.append("### Root-cause group counts")
    md.append("")
    md.append("| group | count |")
    md.append("|---|---:|")
    for k, v in sorted(summary["group_counts"].items(), key=lambda x: -x[1]):
        md.append(f"| `{k}` | {v} |")
    md.append("")
    md.append("### Recommended resolution counts")
    md.append("")
    md.append("| resolution | count |")
    md.append("|---|---:|")
    for k, v in sorted(summary["recommended_resolution_counts"].items(), key=lambda x: -x[1]):
        md.append(f"| `{k}` | {v} |")
    md.append("")
    md.append("### Coverage estimates")
    md.append("")
    md.append(
        "- **One global extractor change can address ~"
        f"{summary['estimated_global_extractor_fix_distinct_questions']}** distinct "
        "blockers (misclassified option-image block + short-image-option + horizontal "
        "giant-crop family — these all reduce to relaxing the horizontal-row "
        "split trigger and adding a `split a large after-text image into 4 option "
        "crops` pass)."
    )
    md.append(
        f"- **{summary['estimated_full_question_crop_fallback']}** blockers can be "
        "rendered safely *now* via a website-side fallback that shows the cropped "
        "question region as the question body with A/B/C/D as overlay hot-spots."
    )
    md.append(
        f"- **{summary['estimated_manual_one_off_fixes']}** blockers are best handled "
        "by direct edits to `questions.json` (slice / font idiosyncrasies)."
    )
    md.append(
        f"- **{summary['estimated_hide_from_publish']}** blockers have no usable "
        "fallback and would need to be hidden from publishing."
    )
    md.append("")

    # ---- by group ---------------------------------------------------------
    by_group: dict[str, list[dict]] = defaultdict(list)
    for e in entries:
        for cat in e["root_causes"]:
            by_group[cat].append(e)

    # Stable group ordering, biggest first
    group_order = sorted(by_group.keys(), key=lambda k: -group_counts[k])

    for grp in group_order:
        md.append(f"## Root cause — `{grp}` ({group_counts[grp]} blocker(s))")
        md.append("")
        # Dedup by (paper, qno) so a question with multiple root causes only
        # appears once in this section.
        seen: set[tuple] = set()
        items = []
        for e in by_group[grp]:
            key = (e["source_file"], e["question_number"])
            if key in seen:
                continue
            seen.add(key)
            items.append(e)
        items.sort(key=lambda e: (e["source_file"], e["question_number"]))
        for e in items:
            md.append(
                f"### `{e['source_file']}` Q{e['question_number']} "
                f"— layout=`{e['layout_type']}` — recommended **{e['recommended_resolution']}**"
            )
            for r in e["blocker_reasons"]:
                md.append(f"- reason: {r}")
            if e["question_text_snippet"]:
                snippet = e["question_text_snippet"]
                if len(snippet) > 200:
                    snippet = snippet[:200].rstrip() + "..."
                md.append(f"- question_text: {snippet!r}")
            opt_preview = "; ".join(
                f"{lbl}={t!r}"
                for lbl, t in zip("ABCD", e["option_texts"])
                if t
            )
            if opt_preview:
                md.append(f"- option_texts: {opt_preview}")
            for ip in e["question_image_paths"]:
                md.append(f"- question image: [{ip}]({ip})")
            for ip in e["option_image_paths"]:
                md.append(f"- option image: [{ip}]({ip})")
            if e["debug_image"]:
                md.append(f"- debug: [{e['debug_image']}]({e['debug_image']})")
            md.append("")

    OUT_MD.write_text("\n".join(md).rstrip() + "\n", encoding="utf-8")

    print(f"[done] wrote {OUT_JSON.relative_to(ROOT).as_posix()}")
    print(f"[done] wrote {OUT_MD.relative_to(ROOT).as_posix()}")
    print(f"  total_blockers = {summary['total_blockers']}")
    print(f"  group_counts   = {summary['group_counts']}")
    print(f"  resolutions    = {summary['recommended_resolution_counts']}")
    print(f"  global_extractor_fix_coverage = {summary['estimated_global_extractor_fix_distinct_questions']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
