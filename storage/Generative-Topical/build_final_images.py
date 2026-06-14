"""Consolidate each paper's referenced images into a single images_final/ folder
and repoint questions.json at it.

For every image referenced by a paper's questions.json, the AUTHORITATIVE
version (images_fix/<name> if it exists, else images/<name>) is copied into a
new images_final/ folder, and every image_path in questions.json is rewritten
to point at images_final/. This makes questions.json reference exactly ONE
directory, with the QA-corrected image used for every reference.

Non-destructive: images/ and images_fix/ are left intact (provenance).
Idempotent: safe to run twice. Backs up each questions.json once as
.bak.pre_final. Default is DRY-RUN; pass --apply to write.

Usage:
  python build_final_images.py            # dry-run (no writes)
  python build_final_images.py --apply    # build folders + rewrite JSON
"""
from __future__ import annotations

import glob
import json
import os
import shutil
import sys


def authoritative(pdir: str, name: str):
    fx = os.path.join(pdir, "images_fix", name)
    if os.path.exists(fx):
        return fx, "images_fix"
    im = os.path.join(pdir, "images", name)
    if os.path.exists(im):
        return im, "images"
    return None, None


def iter_assets(d: dict):
    """Yield every asset dict that carries an image_path (placement lists,
    options, option_table, and the assets[] mirror)."""
    for q in d["questions"]:
        for lst in (q.get("question_images_between_text"),
                    q.get("question_images_after_text"),
                    q.get("assets")):
            for a in (lst or []):
                if a.get("image_path"):
                    yield a
        for o in (q.get("options") or []):
            for a in (o.get("images") or []):
                if a.get("image_path"):
                    yield a
        t = q.get("option_table")
        if t and t.get("image_path"):
            yield t


def main(argv=None) -> int:
    apply = "--apply" in (argv if argv is not None else sys.argv[1:])
    papers = sorted(glob.glob("output/papers/*/questions.json"))

    tot_papers = tot_files = tot_repoint = miss = from_fix = from_img = 0
    for f in papers:
        pdir = os.path.dirname(f)
        stem = os.path.basename(pdir)
        finaldir = os.path.join(pdir, "images_final")
        d = json.load(open(f, encoding="utf-8"))

        pending = {}     # name -> source path (authoritative)
        repoint = 0
        for a in iter_assets(d):
            p = a["image_path"]
            name = p.replace("\\", "/").split("/")[-1]
            src, where = authoritative(pdir, name)
            if src is None:
                miss += 1
                print(f"  MISSING authoritative for {stem}/{name}")
                continue
            pending[name] = src
            new = f"papers/{stem}/images_final/{name}"
            if p != new:
                a["image_path"] = new
                repoint += 1

        for name, src in pending.items():
            if os.path.normpath(os.path.dirname(src)).endswith("images_fix"):
                from_fix += 1
            else:
                from_img += 1

        if apply:
            os.makedirs(finaldir, exist_ok=True)
            for name, src in pending.items():
                shutil.copy2(src, os.path.join(finaldir, name))
            bak = f + ".bak.pre_final"
            if not os.path.exists(bak):
                shutil.copy2(f, bak)
            json.dump(d, open(f, "w", encoding="utf-8"), indent=2, ensure_ascii=False)

        tot_papers += 1
        tot_files += len(pending)
        tot_repoint += repoint

    mode = "APPLIED" if apply else "DRY-RUN (no writes)"
    print(f"\n{mode}")
    print(f"  papers:               {tot_papers}")
    print(f"  files -> images_final: {tot_files} unique refs "
          f"({from_fix} from images_fix, {from_img} from images)")
    print(f"  image_path rewrites:  {tot_repoint}")
    print(f"  missing authoritative: {miss}")
    return 1 if miss else 0


if __name__ == "__main__":
    raise SystemExit(main())
