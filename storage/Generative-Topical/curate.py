"""Curation CLI — runs AFTER extract.py.

    python curate.py --output output --provider mock           # all papers
    python curate.py --paper output/papers/9702_m24_qp_12       # one paper
    python curate.py --output output --provider mock --force    # overwrite even approved questions

The deterministic extractor is never re-run by this script. It only reads
``questions.json`` and writes ``curated_questions.review.json`` next to it.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from src.curation.provider import get_provider
from src.curation.runner import curate_all, curate_paper


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Run AI curation (validation + enrichment) over extracted papers."
    )
    parser.add_argument(
        "--output",
        default="output",
        help="Root output folder produced by extract.py (default: output).",
    )
    parser.add_argument(
        "--paper",
        default=None,
        help="Curate just this one paper folder (containing questions.json). Optional.",
    )
    parser.add_argument(
        "--provider",
        default="mock",
        help="LLM provider name registered in src/curation/provider.py (default: mock).",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Re-curate even questions previously marked admin_approved/rejected.",
    )
    args = parser.parse_args(argv)

    output_root = Path(args.output).resolve()
    if not output_root.is_dir():
        print(f"[error] output folder not found: {output_root}")
        return 2

    if args.paper:
        paper_dir = Path(args.paper).resolve()
        qjson = paper_dir / "questions.json"
        if not qjson.exists():
            print(f"[error] questions.json not found in {paper_dir}")
            return 2
        provider = get_provider(args.provider)
        out = curate_paper(qjson, output_root, provider, force=args.force)
        print(f"[done] wrote {out}")
        return 0

    written = curate_all(output_root, provider_name=args.provider, force=args.force)
    for p in written:
        print(f"[done] {p.relative_to(output_root).as_posix()}")
    if not written:
        print("[warn] no papers found to curate")
    return 0


if __name__ == "__main__":
    sys.exit(main())
