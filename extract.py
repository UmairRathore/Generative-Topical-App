"""CLI entry point.

    python extract.py --input input --output output --dpi 200
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from src.extractor import extract_folder


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Extract Cambridge MCQ papers to JSON + images.")
    parser.add_argument("--input", default="input", help="Folder containing source PDFs (default: input).")
    parser.add_argument("--output", default="output", help="Folder for generated output (default: output).")
    parser.add_argument("--dpi", type=int, default=200, help="Render DPI for page PNGs (default: 200).")
    args = parser.parse_args(argv)

    input_dir = Path(args.input).resolve()
    output_dir = Path(args.output).resolve()
    if not input_dir.is_dir():
        print(f"[error] input folder not found: {input_dir}")
        return 2

    manifest = extract_folder(input_dir, output_dir, dpi=args.dpi)
    print(f"[done] processed {len(manifest['papers'])} paper(s) -> {output_dir}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
