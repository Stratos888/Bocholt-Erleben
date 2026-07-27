#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
WORKPACK = ROOT / "docs/brand/AI_BRAND_WORKPACK_A.md"
REPORT = ROOT / "docs/brand/AI_BRAND_EXPLORATION_REPORT.md"
LAB = ROOT / "docs/brand/lab/index.html"

errors: list[str] = []

for path in (WORKPACK, REPORT, LAB):
    if not path.is_file():
        errors.append(f"missing required file: {path.relative_to(ROOT)}")

if not errors:
    workpack = WORKPACK.read_text(encoding="utf-8")
    report = REPORT.read_text(encoding="utf-8")
    lab = LAB.read_text(encoding="utf-8")

    required_workpack = [
        "Strategie-Gate geschlossen",
        "Wortmarke-only",
        "Typografisches Icon",
        "Abstraktes Zeichen",
        "Behutsame Bestandsentwicklung",
        "mindestens 78/100",
    ]
    for marker in required_workpack:
        if marker not in workpack:
            errors.append(f"workpack missing marker: {marker}")

    if "Aktuell freigegebene Finalisten: **0**" not in report:
        errors.append("exploration report must state the current finalist count")

    if '<meta name="robots" content="noindex,nofollow">' not in lab:
        errors.append("Brand Lab must remain noindex,nofollow")

    if "0" not in lab or "aktuell freigegebene Finalisten" not in lab:
        errors.append("Brand Lab must expose the current finalist state")

    forbidden = [
        r"<script[^>]+src=",
        r"<link[^>]+href=[\"']https?://",
        r"<img[^>]+src=[\"']https?://",
    ]
    for pattern in forbidden:
        if re.search(pattern, lab, flags=re.IGNORECASE):
            errors.append(f"Brand Lab contains external dependency: {pattern}")

if errors:
    print("Brand Lab validation: FAIL", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print("Brand Lab validation: OK (strategy frozen, isolated lab, 0 qualified finalists)")
