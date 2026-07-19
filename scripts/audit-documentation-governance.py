#!/usr/bin/env python3
"""Validate the canonical documentation contract for Bocholt erleben."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

REQUIRED = {
    "README.md",
    "AI_ENTRYPOINT.md",
    "ENGINEERING.md",
    "MASTER.md",
    "ROADMAP.md",
    "TEST_STATUS.md",
    "Produktvertrag.md",
    "docs/README.md",
    "docs/DOCUMENT_REGISTRY.md",
    "docs/architecture/SYSTEM_MAP.md",
    "docs/external-resource-matrix.md",
    "docs/workpacks/active/CURRENT_WORKPACK.md",
    "docs/workpacks/queued/INDEX.md",
    "scripts/report-documentation-inventory.py",
}

LINE_LIMITS = {
    "README.md": 110,
    "AI_ENTRYPOINT.md": 230,
    "MASTER.md": 150,
    "ROADMAP.md": 190,
    "TEST_STATUS.md": 150,
    "docs/README.md": 230,
    "docs/DOCUMENT_REGISTRY.md": 230,
    "docs/architecture/SYSTEM_MAP.md": 220,
    "docs/external-resource-matrix.md": 170,
    "docs/workpacks/active/CURRENT_WORKPACK.md": 140,
    "docs/workpacks/queued/INDEX.md": 130,
}

NO_APPEND_BLOCKS = {
    "AI_ENTRYPOINT.md",
    "MASTER.md",
    "ROADMAP.md",
    "TEST_STATUS.md",
    "docs/README.md",
    "docs/DOCUMENT_REGISTRY.md",
    "docs/architecture/SYSTEM_MAP.md",
    "docs/external-resource-matrix.md",
    "docs/workpacks/active/CURRENT_WORKPACK.md",
    "docs/workpacks/queued/INDEX.md",
}

REQUIRED_MARKERS = {
    "AI_ENTRYPOINT.md": [
        "Ein primärer Ausführungs-Chat",
        "Einmaliges Arbeitsmandat",
        "R1 lokal und reversibel",
        "Gate A – Verstehen",
        "Gate C – Reale Runtime beweisen",
        "Fehlerbudget und Stop-the-line",
        "Ein grüner PR beweist höchstens E2",
        "dedizierter Ruleset-Bypass",
        "Dokumentations- und Implementierungsvertrag",
        "docs/DOCUMENT_REGISTRY.md",
    ],
    "docs/workpacks/active/CURRENT_WORKPACK.md": [
        "Aktiver Implementierungs-Workpack",
        "Nächster erlaubter Schritt",
        "Ressourcen-Lock",
    ],
    "docs/architecture/SYSTEM_MAP.md": [
        "Datenhoheit",
        "Kritischer Inbox-Übernahmepfad",
        "Evidence-Grenzen",
    ],
    "docs/external-resource-matrix.md": [
        "Keine Testschreibaktion auf Live-Ressourcen",
        "controlled-live-admin",
        "Events_Staging",
    ],
    "MASTER.md": ["Produkt-Nordstern", "Nicht verhandelbare Produktprinzipien"],
    "ROADMAP.md": ["Ausführungsreihenfolge", "Aktivierungsregel"],
    "TEST_STATUS.md": ["Aktueller Proofindex", "Aktuelle harte Evidence-Lücke"],
    "docs/README.md": [
        "Dokumenttypen und Rangfolge",
        "Aufgabenbezogener Lesepfad",
        "Dokumentations-Definition-of-Done",
    ],
    "docs/DOCUMENT_REGISTRY.md": [
        "Kanonische Root-Dokumente",
        "Pfadbasierte Rollen",
        "Änderungsmatrix",
        "Neue Dokumente",
    ],
}

errors: list[str] = []

for relative in sorted(REQUIRED):
    path = ROOT / relative
    if not path.is_file() or path.stat().st_size == 0:
        errors.append(f"missing or empty required file: {relative}")

for relative, limit in LINE_LIMITS.items():
    path = ROOT / relative
    if not path.is_file():
        continue
    count = len(path.read_text(encoding="utf-8").splitlines())
    if count > limit:
        errors.append(f"{relative}: {count} lines exceeds budget {limit}")

append_marker = re.compile(r"<!--\s*===\s*BEGIN BLOCK:", re.IGNORECASE)
for relative in sorted(NO_APPEND_BLOCKS):
    path = ROOT / relative
    if path.is_file() and append_marker.search(path.read_text(encoding="utf-8")):
        errors.append(f"{relative}: append-only BEGIN BLOCK marker is forbidden")

for relative, markers in REQUIRED_MARKERS.items():
    path = ROOT / relative
    if not path.is_file():
        continue
    text = path.read_text(encoding="utf-8")
    for marker in markers:
        if marker not in text:
            errors.append(f"{relative}: required marker missing: {marker!r}")

system_map = ROOT / "docs/architecture/SYSTEM_MAP.md"
if system_map.is_file():
    text = system_map.read_text(encoding="utf-8")
    if re.search(r"\bPR\s*#\d+\b", text):
        errors.append("SYSTEM_MAP.md: dynamic PR references are forbidden")
    if re.search(r"^##\s+Aktueller Status", text, re.MULTILINE):
        errors.append("SYSTEM_MAP.md: current runtime status belongs in CURRENT_WORKPACK.md")

current = ROOT / "docs/workpacks/active/CURRENT_WORKPACK.md"
if current.is_file():
    text = current.read_text(encoding="utf-8")
    if re.search(r"Offene PR.*#\d+", text, re.IGNORECASE):
        errors.append("CURRENT_WORKPACK.md: do not persist an open-PR list; read GitHub directly")

ai = ROOT / "AI_ENTRYPOINT.md"
matrix = ROOT / "docs/external-resource-matrix.md"
if ai.is_file() and matrix.is_file():
    ai_text = ai.read_text(encoding="utf-8")
    matrix_text = matrix.read_text(encoding="utf-8")
    if "Live-Eventpflege" not in ai_text or "controlled-live-admin" not in matrix_text:
        errors.append("live event admin exception is not consistently documented")
    if "technisch gesperrt" not in ai_text:
        errors.append("AI_ENTRYPOINT.md must state that direct main hotfix is technically blocked until bypass setup")

workflow = ROOT / ".github/workflows/project-guardrails.yml"
if workflow.is_file():
    text = workflow.read_text(encoding="utf-8")
    for marker in [
        "scripts/report-documentation-inventory.py --check",
        "documentation-inventory.json",
        "actions/upload-artifact@v4",
    ]:
        if marker not in text:
            errors.append(f"project-guardrails.yml: documentation inventory contract missing: {marker!r}")
    if re.search(r"contents:\s*write", text):
        errors.append("project-guardrails.yml: documentation guardrail must remain read-only")
    if "git push" in text or "force-with-lease" in text:
        errors.append("project-guardrails.yml: self-writing or force-push behavior is forbidden")

if errors:
    print("Documentation governance audit FAILED:")
    for error in errors:
        print(f"- {error}")
    sys.exit(1)

print("Documentation governance audit OK")
