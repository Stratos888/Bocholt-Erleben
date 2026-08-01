#!/usr/bin/env python3
from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
LAB = ROOT / "docs/brand/lab-v2"

required = [
    LAB / "README.md",
    LAB / "SOURCE.md",
    LAB / "PROVENANCE.json",
    LAB / "index.html",
]
errors = [f"missing: {path.relative_to(ROOT)}" for path in required if not path.is_file()]

if not errors:
    html = (LAB / "index.html").read_text(encoding="utf-8")
    readme = (LAB / "README.md").read_text(encoding="utf-8")
    source = (LAB / "SOURCE.md").read_text(encoding="utf-8")

    for marker in ("ZURÜCKGEWIESEN", "91/100 ist ungültig", "NICHT FREIGEGEBEN"):
        if marker not in html:
            errors.append(f"archive page missing rejection marker: {marker}")

    for marker in ("Status: **ZURÜCKGEWIESEN**", "Premium-Gate: nicht bestanden", "Weiterentwicklung dieser Familie: beendet"):
        if marker not in readme:
            errors.append(f"archive README missing marker: {marker}")

    if "Der Kandidat ist zurückgewiesen" not in source:
        errors.append("archive source does not state rejection")

    forbidden_approval_markers = (
        "Eine Richtung hat das interne Gate erreicht.",
        "Freigabe nur als Grundrichtung.",
        "intern qualifizierter Richtungs-Kandidat",
    )
    for marker in forbidden_approval_markers:
        if marker in html or marker in readme:
            errors.append(f"stale approval marker remains: {marker}")

    if re.search(r'(?:src|href|url\()[^>\n]*https?://', html, re.IGNORECASE):
        errors.append("archive page contains external dependency")

    try:
        provenance = json.loads((LAB / "PROVENANCE.json").read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        errors.append(f"invalid provenance JSON: {exc}")
        provenance = {}

    expected = {
        "archive_status": "rejected",
        "score_claim_valid": False,
        "premium_gate_passed": False,
        "microdrawing_authorized": False,
        "public_cutover_authorized": False,
        "further_development_authorized": False,
        "legal_clearance_claimed": False,
    }
    for key, value in expected.items():
        if provenance.get(key) != value:
            errors.append(f"provenance {key} must equal {value!r}")

if errors:
    print("Brand Lab v2 archive validation: FAIL", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print("Brand Lab v2 archive validation: OK (rejected, invalid score claim, no approval or cutover)")
