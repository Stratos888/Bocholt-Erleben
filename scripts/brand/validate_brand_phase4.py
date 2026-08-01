#!/usr/bin/env python3
from pathlib import Path
import json, re, sys, xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "docs/brand/phase4"
IDS = [f"candidate-{i:03d}" for i in range(1, 13)]
PAGES = ["producer-board.html", "critic-a.html", "critic-b.html", "red-team.html"]
PROMPTS = ["critic-a.md", "critic-b.md", "red-team.md", "consolidation.md"]
errors = []

def need(path):
    if not path.is_file():
        errors.append(f"missing: {path.relative_to(ROOT)}")

for name in ["README.md","producer-manifest.json","blind-systems.json",*PAGES]:
    need(BASE/name)
for name in PROMPTS:
    need(BASE/"prompts"/name)

if not errors:
    producer = json.loads((BASE/"producer-manifest.json").read_text())
    blind = json.loads((BASE/"blind-systems.json").read_text())
    pitems = producer.get("candidates", [])
    systems = blind.get("systems", [])
    if producer.get("candidate_count") != 12 or len(pitems) != 12 or len(systems) != 12:
        errors.append("exactly 12 systems are required")
    if producer.get("producer_assigns_final_score") is not False:
        errors.append("producer final score flag must be false")
    if producer.get("producer_assigns_ranking") is not False:
        errors.append("producer ranking flag must be false")
    if [x.get("candidate_id") for x in pitems] != IDS:
        errors.append("producer IDs must be candidate-001 through candidate-012")
    if [x.get("candidate_id") for x in systems] != IDS:
        errors.append("blind IDs must be candidate-001 through candidate-012")
    if len({x.get("architecture_class") for x in pitems}) < 5:
        errors.append("at least five architecture classes are required")
    blind_raw = json.dumps(blind).lower()
    for leak in ("architecture_class","construction_family","construction_notes","final_design_score","ranking_assigned"):
        if leak in blind_raw:
            errors.append(f"blind asset leaks producer field: {leak}")
    for system in systems:
        for key in ("identity_svg","mark_svg","ablation_svg"):
            raw = system.get(key, "")
            try:
                node = ET.fromstring(raw)
                if not node.tag.endswith("svg") or "viewBox" not in node.attrib:
                    errors.append(f"invalid {key}: {system.get('candidate_id')}")
            except ET.ParseError as exc:
                errors.append(f"invalid SVG XML {system.get('candidate_id')} {key}: {exc}")
    producer_raw = json.dumps(producer).lower()
    for forbidden in ("finalist","winner","recommended","approved"):
        if forbidden in producer_raw:
            errors.append(f"producer manifest contains forbidden claim: {forbidden}")
    if re.search(r'"(?:score|rank)"\s*:', producer_raw):
        errors.append("producer manifest contains score or rank field")

    for name in ("critic-a.html","critic-b.html","red-team.html"):
        text=(BASE/name).read_text().lower()
        if "blind-systems.json" not in text:
            errors.append(f"{name} must load blind systems")
        for leak in ("producer-manifest.json","architecture_class","construction_family"):
            if leak in text:
                errors.append(f"{name} leaks producer information: {leak}")
        if re.search(r'https?://',text):
            errors.append(f"{name} contains external dependency")
    if "producer-manifest.json" not in (BASE/"producer-board.html").read_text():
        errors.append("producer board must load producer manifest")

    prompts={n:(BASE/"prompts"/n).read_text().lower() for n in PROMPTS}
    for name, marker in {"critic-a.md":"kritiker a","critic-b.md":"kritiker b","red-team.md":"red team","consolidation.md":"konsolidator"}.items():
        if marker not in prompts[name]:
            errors.append(f"prompt role marker missing: {name}")
    if "producer-board.html" not in prompts["critic-a.md"] or "nicht lesen" not in prompts["critic-a.md"]:
        errors.append("critic A must forbid producer board access")
    if "producer-board.html" not in prompts["critic-b.md"] or "nicht lesen" not in prompts["critic-b.md"]:
        errors.append("critic B must forbid producer board access")
    if "keine ästhetischen scores" not in prompts["red-team.md"]:
        errors.append("red team must forbid aesthetic scores")
    if "0 qualifizierte richtungen" not in prompts["consolidation.md"]:
        errors.append("consolidation must allow zero qualified directions")

if errors:
    print("Brand phase 4 validation: FAIL", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print("Brand phase 4 validation: OK (12 anonymous systems, 5+ architecture classes, separated review packages, no producer score)")
