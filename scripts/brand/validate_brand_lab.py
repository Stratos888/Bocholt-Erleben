#!/usr/bin/env python3
from pathlib import Path
import json
import re
import struct
import sys

ROOT = Path(__file__).resolve().parents[2]
LAB = ROOT / "docs/brand/lab"
CANDIDATE = LAB / "candidates/champion"

required = [
    ROOT / "docs/brand/AI_BRAND_WORKPACK_A.md",
    ROOT / "docs/brand/AI_BRAND_EXPLORATION_REPORT.md",
    LAB / "index.html",
    LAB / "PROVENANCE.json",
    LAB / "brand-lab.css",
    CANDIDATE / "mark.svg",
    CANDIDATE / "mark-inverse.svg",
    CANDIDATE / "mark-mono.svg",
    CANDIDATE / "app-icon.svg",
    CANDIDATE / "app-icon-mono.svg",
    CANDIDATE / "lockup.svg",
    CANDIDATE / "lockup-mono.svg",
]
required += [CANDIDATE / f"sizes/{size}.png" for size in (16, 24, 32, 48)]
errors = []
for path in required:
    if not path.is_file():
        errors.append(f"missing: {path.relative_to(ROOT)}")

if not errors:
    html = (LAB / "index.html").read_text(encoding="utf-8")
    workpack = (ROOT / "docs/brand/AI_BRAND_WORKPACK_A.md").read_text(encoding="utf-8")
    report = (ROOT / "docs/brand/AI_BRAND_EXPLORATION_REPORT.md").read_text(encoding="utf-8")
    for marker in (
        '<meta name="robots" content="noindex,nofollow">',
        "Ein Champion. Keine künstliche Auswahl.",
        "B-Moment",
        "84/100",
        "Echte App-Raster",
        "App-Masken",
        "Einfarbenbeweis",
        "Unveränderte Produktarchitektur",
        "Champion weiterführen",
        "Konkreter Knock-out",
    ):
        if marker not in html:
            errors.append(f"missing lab marker: {marker}")
    if "Offener Takt" in html or "Direktwort" in html:
        errors.append("withdrawn candidates must not remain in the active lab")
    if "ein Champion, keine künstliche Auswahl" not in workpack:
        errors.append("workpack must expose the calibrated one-champion gate")
    if "ein Champion für das Richtungs-Gate qualifiziert" not in report:
        errors.append("report must expose the calibrated finalist state")
    for pattern in (
        r'<script[^>]+src=',
        r'<link[^>]+href=["\']https?://',
        r'<img[^>]+src=["\']https?://',
    ):
        if re.search(pattern, html, re.IGNORECASE):
            errors.append(f"external dependency: {pattern}")
    for size in (16, 24, 32, 48):
        path = CANDIDATE / f"sizes/{size}.png"
        data = path.read_bytes()
        if data[:8] != b"\x89PNG\r\n\x1a\n" or len(data) < 24:
            errors.append(f"invalid PNG: {path.relative_to(ROOT)}")
            continue
        width, height = struct.unpack(">II", data[16:24])
        if (width, height) != (size, size):
            errors.append(f"wrong PNG size: {path.relative_to(ROOT)}={(width, height)}")
    for path in CANDIDATE.glob("*.svg"):
        text = path.read_text(encoding="utf-8")
        if "<svg" not in text or "viewBox=" not in text:
            errors.append(f"invalid SVG shell: {path.relative_to(ROOT)}")
        if "filter=" in text or "linearGradient" in text or "radialGradient" in text:
            errors.append(f"identity asset must not depend on effects: {path.relative_to(ROOT)}")
    provenance = json.loads((LAB / "PROVENANCE.json").read_text(encoding="utf-8"))
    if provenance.get("external_designer_used") is not False:
        errors.append("external designer flag must be false")
    if provenance.get("image_generation_used_for_final_assets") is not False:
        errors.append("final assets must not use image generation")
    if provenance.get("direction") != "B-Moment":
        errors.append("provenance direction mismatch")

if errors:
    print("Brand Lab validation: FAIL", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print("Brand Lab validation: OK (one calibrated champion, isolated outlined SVGs, true app rasters)")
